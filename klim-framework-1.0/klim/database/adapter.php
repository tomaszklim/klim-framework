<?php
/**
 * Klasa bazowa dla implementacji adapterów do ładowania danych
 * ze źródeł innych niż baza danych
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


abstract class KlimDatabaseAdapter
{
	protected $controller;

	public function __construct( $controller )
	{
		$this->controller = $controller;
	}

	/**
	 * Metoda podmienia nazwy kolumn ze źródłowych na docelowe wg zapisanego
	 * w konfiguracji mapowania nazw, oraz wycina z wyniku wiersze oznaczone
	 * w konfiguracji jako zawierające nagłówki zamiast danych.
	 */
	protected function mapRows( $rows, $map, $header_rows = array() )
	{
		$out = array();
		$cnt = 0;

		foreach ( $rows as $row ) {
			$tmp = array();

			if ( in_array($cnt++, $header_rows, true) )
				continue;

			foreach ( $map as $index => $field ) {
				$value = $row[$index];

				if ( preg_match("/^([0-9.-]+) �$/", $value, $ret) ) {
					$tmp[$field] = $ret[1];
				} else {
					$tmp[$field] = $value;
				}
			}

			$out[] = $tmp;
		}

		return $out;
	}

	/**
	 * Metoda realizująca prostą projekcję danych na wzór tej realizowanej
	 * przez bazę danych w ramach zapytania SELECT. Nie obsługuje póki co
	 * klauzul GROUP BY i HAVING, oraz funkcji i aliasów na liście pól.
	 */
	protected function simpleProjection( $rows, $fields, $where, $clauses )
	{
		if ( !empty($where) ) {
			$rows = $this->controller->where( $rows, $where );
		}

		if ( isset($clauses["order by"]) ) {
			$rows = $this->controller->order( $rows, $clauses["order by"] );
		}

		if ( isset($clauses["limit"]) ) {
			$rows = $this->controller->limit( $rows, $clauses["limit"], @$clauses["offset"] );
		}

		if ( !empty($fields) ) {
			$rows = $this->controller->getFields( $rows, $fields );
		}

		return $rows;
	}

	/**
	 * Tą metodę należy implementować w adapterach. Argumenty otrzymywane
	 * przez nią mają identyczną postać, jak argumenty otrzymywane przez
	 * metodę KlimDatabase::select().
	 */
	abstract public function execute( $schema, $fields, $where, $clauses, $die );
}

