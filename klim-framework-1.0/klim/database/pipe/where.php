<?php
/**
 * Klasa implementująca filtr do danych
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


/**
 * Filtr implementujący funkcjonalność klauzuli WHERE.
 */
class KlimDatabasePipeWhere extends KlimDatabasePipe
{
	protected $terms = array();

	public function execute( $rows, $params )
	{
		$this->parseParams( $params );
		$this->normalize();
		return $this->filterRows( $rows );
	}

	/**
	 * Metoda parsująca tablicę where - przekształca uproszczony
	 * format tablicy na format łatwiejszy do dalszej obróbki.
	 */
	protected function parseParams( $params )
	{
		if ( is_array($params) && !empty($params) ) {
			foreach ( $params as $field => $value ) {

				$arr = $this->parseValue( $value );
				$operator = $arr[0];
				$vals = $arr[1];

				foreach ( $vals as $val ) {
					$this->terms[$field][$operator][] = $val;
				}
			}
		}
	}

	/**
	 * Metoda parsująca pojedynczy warunek z tablicy where.
	 */
	protected function parseValue( $value )
	{
		$operator = "=";
		$allowed = array( "=", "!=", "join", "not join", "<", "<=", ">=", ">", "between", "not between", "bitand", "not bitand" );

		if ( !is_array($value) ) {
			return array( $operator, array($value) );
		}

		if ( !empty($value[0]) && in_array($value[0], $allowed, true) ) {
			$operator = $value[0];
			array_shift( $value );
		}

		if ( $operator === "not join" ) {
			$operator = "!=";
			$value = $this->controller->getValues( $value[1], $value[0] );
		} else if ( $operator === "join" ) {
			$operator = "=";
			$value = $this->controller->getValues( $value[1], $value[0] );
		}

		return array( $operator, $value );
	}

	/**
	 * Metoda normalizująca warunki - usuwa zduplikowane
	 * warunki dla operatorów równości i zaprzeczenia.
	 */
	protected function normalize()
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

					case "between":
					case "not between":
						if ( count($values) != 2 || (float)$values[0] > (float)$values[1] ) {
							unset( $this->terms[$field][$operator] );
							throw new KlimRuntimeDatabaseException( "invalid between operands for field $field" );
						}
						break;
				}
			}
		}
	}

	/**
	 * Metoda aplikująca zbudowaną wcześniej tabelę warunków
	 * do przekazanego wyniku.
	 */
	protected function filterRows( $rows )
	{
		foreach ( $this->terms as $field => $term ) {
			foreach ( $term as $operator => $values ) {
				$tmp = array();

				foreach ( $rows as $row )
					if ( $this->checkField($operator, $row[$field], $values) )
						$tmp[] = $row;

				$rows = $tmp;
			}
		}

		return $rows;
	}

	/**
	 * Metoda sprawdzająca pojedynczy warunek na pojedynczej
	 * kolumnie pojedynczego wiersza.
	 */
	protected function checkField( $operator, $value, $values )
	{
		$all = array( "=", "!=", "in", "not in" );

		if ( !is_numeric($value) && !in_array($operator, $all, true) ) {
			throw new KlimRuntimeDatabaseException( "invalid operator used on non-numeric field" );
		}

		switch ( $operator )
		{
			case "=":
				return ( $value == $values[0] );
			case "!=":
				return ( $value != $values[0] );

			case "in":
				return in_array( $value, $values );
			case "not in":
				return !in_array( $value, $values );

			case "<":
				return ( $value < $values[0] );
			case "<=":
				return ( $value <= $values[0] );
			case ">":
				return ( $value > $values[0] );
			case ">=":
				return ( $value >= $values[0] );

			case "bitand":
				return is_set_bin_value( $value, $values[0] );
			case "not bitand":
				return !is_set_bin_value( $value, $values[0] );

			case "between":
				return ( $values[0] <= $value && $value <= $values[1] );
			case "not between":
				return ( $values[0] > $value || $value > $values[1] );
		}
	}
}

