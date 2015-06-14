<?php
/**
 * Klasa do składania zapytań insert
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


class KlimDatabaseQueryInsert
{
	private $binds = array();
	private $query = "";
	private $last_id = 0;
	private $definition;
	private $table;

	public function __construct( $fields, $definition, $dbf )
	{
		$escape = new KlimDatabaseQueryEscape( $dbf );
		$filter = array();
		$this->definition = $definition;
		$this->table = $definition["table"];

		/**
		 * Najpierw sprawdzamy wszystkie podane pola - czy występują
		 * w definicji tabeli, czy spełniają nałożone reguły itp.
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
		 * sprawdzając, czy rzeczywiście występują, oraz weryfikując
		 * lub uzupełniając klucz podstawowy.
		 */
		foreach ( $definition["fields"] as $field => $desc ) {

			$type = $desc[0];
			$default = $desc[2];
			$notnull = $desc[3];
			$primary = $desc[5];

			if ( $primary && $type === "int" ) {

				if ( isset($filter[$field]) && $filter[$field] > 0 ) {
					KlimLogger::info( "db", "insert into $this->table using explicit primary key value is deprecated" );
					$id = $filter[$field];
				} else {

					if ( isset($filter[$field]) && $filter[$field] < 1 ) {
						unset( $filter[$field] );
						throw new KlimRuntimeDatabaseException( "insert into $this->table with invalid primary key value" );
					}

					$id = $dbf->nextSequenceValue( "s_$this->table" );
				}

				if ( $id !== false ) {
					$filter[$field] = $id;
					$this->last_id = $id;
				}
			}

			if ( !isset($fields[$field]) && $notnull && !$default && !$primary ) {
				throw new KlimRuntimeDatabaseException( "missing not-null field $field without default value" );
			}
		}

		if ( $dbf->getVendor() === "oracle" ) {
			$this->generateQueryOracle( $filter );
		} else {
			$this->generateQueryGeneric( $filter );
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
	 * Zwraca wartość klucza głównego pobraną z sekwencji. Jeśli dla
	 * danego typu bazy danych wartość może być pobrana dopiero po
	 * wykonaniu zapytania, tutaj jest zwracane 0, a za pobranie tej
	 * wartości odpowiedzialna jest metoda KlimDatabase::insert.
	 */
	public function getLastId()
	{
		return $this->last_id;
	}

	/**
	 * Metoda generująca treść zapytania - wersja generyczna, dla baz
	 * danych innych, niż Oracle.  Generuje zapytanie z danymi wstawionymi
	 * bezpośrednio bezpośrednio do treści.
	 */
	private function generateQueryGeneric( $filter )
	{
		$keys = implode( ",", array_keys($filter) );
		$vals = implode( ",", array_values($filter) );

		$this->query = "insert into $this->table ($keys) values ($vals)";
	}

	/**
	 * Metoda generująca treść zapytania - wersja dla bazy Oracle.
	 * Generuje zapytanie z tzw. zmiennymi bindowanymi, oraz mapowanie
	 * zmiennych bindowanych na docelowe dane. Umożliwia to pominięcie
	 * etapu "hard parse" przy wykonywaniu każdego zapytania, co mocno
	 * zwiększa wydajność Oracle.
	 */
	private function generateQueryOracle( $filter )
	{
		$keys = array();
		$vals = array();

		foreach ( $filter as $field => $value ) {
			$name = $this->getOracleBindName( $field );
			$keys[] = $field;
			$vals[] = $name;
			$this->binds[$name] = $value;
		}

		$keys = implode( ",", $keys );
		$vals = implode( ",", $vals );

		$this->query = "insert into $this->table ($keys) values ($vals)";
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

		return ":db" . $prefixes[$type] . "_" . $field;
	}
}

