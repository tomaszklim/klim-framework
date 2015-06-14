<?php
/**
 * Klasa do składania zapytań update
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * @author Tomasz Klim <framework@tomaszklim.com>
 * @license http://www.gnu.org/copyleft/gpl.html
 */


class KlimDatabaseQueryUpdate
{
	private $binds = array();
	private $query = "";
	private $definition;
	private $table;

	public function __construct( $fields, $where, $definition, $dbf )
	{
		$escape = new KlimDatabaseQueryEscape( $dbf );
		$filter = array();
		$this->definition = $definition;
		$this->table = $definition["table"];

		/**
		 * Najpierw sprawdzamy, czy wszystkie podane pola występują
		 * w definicji tabeli, spełniają nałożone reguły itp.
		 */
		foreach ( $fields as $field => $value ) {

			if ( !isset($definition["fields"][$field]) ) {
				throw new KlimApplicationException( "unknown field $field" );
			}

			$desc = $definition["fields"][$field];
			$type = $desc[0];
			$size = $desc[1];
			$notnull = $desc[3];

			if ( $notnull && $value === null ) {
				throw new KlimRuntimeDatabaseException( "null value supplied for not-null field $field" );
			}

			$escaped = $escape->parse( $field, $value, $type, $size );

			if ( $notnull && ($escaped === "null" || $escaped === false) ) {
				throw new KlimRuntimeDatabaseException( "null value supplied for not-null field $field" );
			}

			$filter[$field] = $escaped;
		}

		/**
		 * Teraz sprawdzamy wszystkie pola, które mogłyby być podane,
		 * sprawdzając, czy rzeczywiście występują, oraz sprawdzając
		 * operacje na kluczu podstawowym.
		 */
		foreach ( $definition["fields"] as $field => $desc ) {

			$type = $desc[0];
			$primary = $desc[5];

			if ( $primary && isset($filter[$field]) ) {
				if ( empty($where) ) {
					unset( $filter[$field] );
					throw new KlimRuntimeDatabaseException( "tried to update $this->table primary key for all rows" );
				} else if ( $type === "int" && $filter[$field] < 1 ) {
					unset( $filter[$field] );
					throw new KlimRuntimeDatabaseException( "tried to update $this->table to invalid value" );
				} else {
					KlimLogger::debug( "db", "update $this->table primary key value is deprecated" );
				}
			}
		}

		if ( $dbf->getVendor() === "oracle" ) {
			$this->generateQueryOracle( $filter, $where );
		} else {
			$this->generateQueryGeneric( $filter, $where );
		}
	}

	/**
	 * Zwraca gotową treść zapytania.
	 */
	public function getQuery()
	{
		return $this->query;
	}

	/**
	 * Zwraca tablicę zmiennych bindowanych, tj. parametrów dla "prepared
	 * query" i odpowiadających im wartości.
	 */
	public function getBinds()
	{
		return $this->binds;
	}

	/**
	 * Metoda generująca treść zapytania - wersja generyczna, dla baz
	 * danych innych, niż Oracle.  Generuje zapytanie z danymi wstawionymi
	 * bezpośrednio bezpośrednio do treści.
	 */
	private function generateQueryGeneric( $filter, $where )
	{
		$tmp = array();
		foreach ( $filter as $field => $value ) {
			$tmp[] = "$field = $value";
		}
		$fields = implode( ",", $tmp );

		$this->query = "update $this->table set $fields" . ( empty($where) ? "" : " where $where" );
	}

	/**
	 * Metoda generująca treść zapytania - wersja dla bazy Oracle.
	 * Generuje zapytanie z tzw. zmiennymi bindowanymi, oraz mapowanie
	 * zmiennych bindowanych na docelowe dane. Umożliwia to pominięcie
	 * etapu "hard parse" przy wykonywaniu każdego zapytania, co mocno
	 * zwiększa wydajność Oracle.
	 */
	private function generateQueryOracle( $filter, $where )
	{
		$tmp = array();
		foreach ( $filter as $field => $value ) {
			$name = $this->getOracleBindName( $field );
			$tmp[] = "$field = $name";
			$this->binds[$name] = $value;
		}
		$fields = implode( ",", $tmp );

		$this->query = "update $this->table set $fields" . ( empty($where) ? "" : " where $where" );
	}

	/**
	 * Metoda sprawdzająca w definicji tabeli typ podanego
	 * pola i generująca dla niego nazwę zmiennej bindowanej.
	 */
	private function getOracleBindName( $field )
	{
		$type = $this->definition["fields"][$field][0];

		$prefixes = array (
			"int"   => "INT",
			"float" => "FLOAT",
			"date"  => "DATE",
			"char"  => "STR",
			"bool"  => "INT",
		);

		return ":db" . $prefixes[$type] . "_" . $field . "_up";
	}
}

