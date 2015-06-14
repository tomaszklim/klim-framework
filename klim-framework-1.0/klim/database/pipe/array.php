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
 * Filtr zamieniający wynik zapytania w postaci obiektu klasy
 * KlimDatabaseResult, udającego arraya, na prawdziwego arraya.
 */
class KlimDatabasePipeArray extends KlimDatabasePipe
{
	public function execute( $result, $strip_numeric_keys = false )
	{
		if ( is_array($result) && !$strip_numeric_keys ) {
			return $result;
		}

		if ( $strip_numeric_keys )
			return $this->rewriteStrip( $result );
		else
			return $this->rewriteSimple( $result );
	}

	protected function rewriteSimple( $result )
	{
		$rows = array();

		foreach ( $result as $row ) {
			$rows[] = $row;
		}

		return $rows;
	}

	protected function rewriteStrip( $result )
	{
		$rows = array();

		foreach ( $result as $row ) {
			foreach ( $row as $key => $value ) {
				if ( is_numeric($key) ) {
					unset( $row[$key] );
				}
			}

			$rows[] = $row;
		}

		return $rows;
	}
}

