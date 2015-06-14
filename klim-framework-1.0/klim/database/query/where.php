<?php
/**
 * Klasa do składania klauzul where z tablic
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


class KlimDatabaseQueryWhere
{
	private $binds = array();
	private $terms = array();
	private $clause = "";
	private $definition;
	private $dbf;

	public function __construct( $params, $definition, $dbf )
	{
		$this->dbf = $dbf;
		$this->definition = $definition;

		$this->parseParams( $params );
		$this->normalize();
		$this->checkIndexes();

		if ( $dbf->getVendor() === "oracle" ) {
			$this->generateClauseOracle();
		} else {
			$this->generateClauseGeneric();
		}
	}

	/**
	 * Zwraca gotową do użycia treść klauzuli where.
	 */
	public function getClause()
	{
		return $this->clause;
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
	 * Zwraca tablicę zakresów do zwrócenia dla podanego pola. Metoda
	 * na potrzeby źródeł danych innych niż baza danych i język SQL.
	 */
	public function getTerm( $field )
	{
		return isset($this->terms[$field]) ? $this->terms[$field] : false;
	}

	/**
	 * Metoda parsująca tablicę where - przekształca uproszczony
	 * format tablicy na format łatwiejszy do dalszej obróbki
	 * przez sterownik do bazy danych.
	 */
	private function parseParams( $params )
	{
		$escape = new KlimDatabaseQueryEscape( $this->dbf );
		if ( is_array($params) && !empty($params) ) {
			foreach ( $params as $field => $value ) {

				if ( !isset($this->definition["fields"][$field]) ) {
					throw new KlimApplicationException( "unknown field $field" );
				}

				$type = $this->definition["fields"][$field][0];

				$arr = $this->parseValue( $value, $type );
				$operator = $arr[0];
				$vals = $arr[1];

				foreach ( $vals as $val ) {

					if ( $operator === "=" && $val === null ) {
						$this->terms[$field]["is"][] = "null";
					} else if ( $operator === "!=" && $val === null ) {
						$this->terms[$field]["is not"][] = "null";
					} else {
						$this->terms[$field][$operator][] = $escape->parse( $field, $val, $type, 0 );
					}
				}
			}
		}
	}

	/**
	 * Metoda parsująca pojedynczy warunek z tablicy where.
	 */
	private function parseValue( $value, $type )
	{
		$operator = "=";
		$allowed = array (
			"int"   => array( "=", "!=", "join", "not join", "<", "<=", ">=", ">", "between", "not between", "bitand", "not bitand" ),
			"float" => array( "=", "!=", "join", "not join", "<", "<=", ">=", ">", "between", "not between" ),
			"date"  => array( "=", "!=", "join", "not join", "<", "<=", ">=", ">", "between", "not between" ),
			"char"  => array( "=", "!=", "join", "not join", "like", "not like" ),
			"bool"  => array( "=", "!=" ),
		);

		if ( !is_array($value) ) {
			return array( $operator, array($value) );
		}

		if ( !empty($value[0]) && in_array($value[0], $allowed[$type], true) ) {
			$operator = $value[0];
			array_shift( $value );
		}

		if ( $operator === "not join" ) {
			$operator = "!=";
			$pipe = new KlimDatabasePipeValues( false );
			$value = $pipe->execute( $value[1], $value[0] );
		} else if ( $operator === "join" ) {
			$operator = "=";
			$pipe = new KlimDatabasePipeValues( false );
			$value = $pipe->execute( $value[1], $value[0] );
		}

		return array( $operator, $value );
	}

	/**
	 * Metoda normalizująca warunki - usuwa zduplikowane warunki dla
	 * operatorów równości i zaprzeczenia, ustala max/min i usuwa
	 * niepotrzebne warunki nierówności, sprawdza parametry klauzuli
	 * between.
	 */
	private function normalize()
	{
		foreach ( $this->terms as $field => $term ) {
			foreach ( $term as $operator => $values ) {
				switch ( $operator ) {

					case "=":
						$tmp = array_unique( $values );
						if ( count($tmp) < 2 ) {
							$this->terms[$field][$operator] = $tmp;
						} else {
							$this->terms[$field]["in"] = $tmp;
							unset( $this->terms[$field][$operator] );
						}
						break;

					case "!=":
						$tmp = array_unique( $values );
						if ( count($tmp) < 2 ) {
							$this->terms[$field][$operator] = $tmp;
						} else {
							$this->terms[$field]["not in"] = $tmp;
							unset( $this->terms[$field][$operator] );
						}
						break;

					case "like":
					case "not like":
						$this->terms[$field][$operator] = array_unique( $values );
						break;

					case "<":
					case "<=":
						if ( count($values) > 1 ) {
							$this->terms[$field][$operator] = array( min($values) );
						}
						break;

					case ">":
					case ">=":
						if ( count($values) > 1 ) {
							$this->terms[$field][$operator] = array( max($values) );
						}
						break;

					case "bitand":
					case "not bitand":
						if ( count($values) > 1 ) {
							$sum = 0;
							foreach ( $values as $bit ) {
								$sum |= $bit;
							}
							$this->terms[$field][$operator] = array( $sum );
						}
						break;

					case "is":
					case "is not":
						if ( count($values) > 1 ) {
							$this->terms[$field][$operator] = array( "null" );
							throw new KlimRuntimeDatabaseException( "multiple $operator values not allowed for field $field" );
						}
						break;

					case "between":
					case "not between":
						// TODO: to będzie działać tylko dla kolumn typu date, poprawić
						if ( count($values) != 2 || strtotime($values[0]) > strtotime($values[1]) ) {
							unset( $this->terms[$field][$operator] );
							throw new KlimRuntimeDatabaseException( "invalid between operands for field $field" );
						}
						break;
				}
			}
		}
	}

	/**
	 * Metoda sprawdza, czy zapytanie jest optymalne wydajnościowo - jeśli
	 * są podane jakiekolwiek parametry do klauzuli where, to przynajmniej
	 * jeden z nich powinien być oparty na indeksie - inaczej baza danych
	 * będzie musiała wykonać full scana, a tutaj zostanie wyrzucone
	 * ostrzeżenie do logu.
	 */
	private function checkIndexes()
	{
		$used = 0;
		foreach ( $this->terms as $field => $term ) {
			if ( $this->definition["fields"][$field][4] ) {
				$used++;
			}
		}

		if ( !empty($this->terms) && $used < 1 ) {
			$db = $this->definition["db"];
			KlimLogger::debug( "db", "no indexes were used for query to database $db" );
		}
	}

	/**
	 * Metoda generująca treść klauzuli where - wersja generyczna, dla baz
	 * danych innych, niż Oracle. Generuje klauzulę z wartościami wyrażeń
	 * bezpośrednio wstawionymi do treści klauzuli.
	 */
	private function generateClauseGeneric()
	{
		$out = array();

		foreach ( $this->terms as $field => $term ) {
			foreach ( $term as $operator => $values ) {
				switch ( $operator ) {

					case "=":
					case "!=":
					case "<":
					case "<=":
					case ">":
					case ">=":
					case "is":
					case "is not":
					case "like":
					case "not like":
						$out[] = "$field $operator $values[0]";
						break;

					case "in":
					case "not in":
						$out[] = "$field $operator (" . implode( ",", $values ) . ")";
						break;

					case "bitand":
						$out[] = "($field & $values[0]) <> 0";
						break;

					case "not bitand":
						$out[] = "($field & $values[0]) = 0";
						break;

					case "between":
					case "not between":
						$out[] = "$field $operator $values[0] and $values[1]";
						break;
				}
			}
		}

		if ( !empty($out) ) {
			$this->clause = implode( " and ", $out );
		}
	}

	/**
	 * Metoda generująca treść klauzuli where - wersja dla bazy Oracle.
	 * Generuje klauzulę z tzw. zmiennymi bindowanymi, oraz mapowanie
	 * zmiennych bindowanych na gotowe wartości. Umożliwia to pominięcie
	 * etapu "hard parse" przy wykonywaniu każdego zapytania, co mocno
	 * zwiększa wydajność Oracle.
	 */
	private function generateClauseOracle()
	{
		$out = array();
		$binds = array();
		foreach ( $this->terms as $field => $term ) {
			foreach ( $term as $operator => $values ) {
				switch ( $operator ) {

					case "=":
					case "!=":
					case "<":
					case "<=":
					case ">":
					case ">=":
					case "like":
					case "not like":
						$name = $this->getOracleBindName( $field, $values );
						$out[] = "$field $operator $name";
						$binds[$name] = $values[0];
						break;

					case "is":
					case "is not":
						$out[] = "$field $operator $values[0]";
						break;

					case "in":
					case "not in":
						$name = $this->getOracleBindName( $field, $values );
						$out[] = "$field $operator ($name)";
						$binds[$name] = $values;
						break;

					case "bitand":
						$name = $this->getOracleBindName( $field, $values );
						$out[] = "bitand($field, $name) <> 0";
						$binds[$name] = $values[0];
						break;

					case "not bitand":
						$name = $this->getOracleBindName( $field, $values );
						$out[] = "bitand($field, $name) = 0";
						$binds[$name] = $values[0];
						break;

					case "between":
					case "not between":
						$name = $this->getOracleBindName( $field, $values[0] );
						$name0 = "{$name}_0";
						$name1 = "{$name}_1";
						$out[] = "$field $operator $name0 and $name1";
						$binds[$name0] = $values[0];
						$binds[$name1] = $values[1];
						break;
				}
			}
		}

		if ( !empty($out) ) {
			$this->clause = implode( " and ", $out );
			$this->binds = $binds;
		}
	}

	/**
	 * Metoda sprawdzająca w definicji tabeli typ podanego
	 * pola i generująca dla niego nazwę zmiennej bindowanej.
	 */
	private function getOracleBindName( $field, $values )
	{
		$type = $this->definition["fields"][$field][0];
		$list = ( is_array($values) && count($values) > 1 );

		$prefixes = array (
			"int"   => "INT",
			"float" => "FLOAT",
			"date"  => "DATE",
			"char"  => "STR",
			"bool"  => "INT",
		);

		return ( $list ? ":dbARR_" : ":db" ) . $prefixes[$type] . "_" . $field;
	}
}

