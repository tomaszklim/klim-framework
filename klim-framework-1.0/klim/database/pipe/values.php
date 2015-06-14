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
 * Filtr zwraca wszystkie wartości kolumny o podanej nazwie z podanego
 * wyniku, który może być albo statycznym arrayem, albo obiektem.
 */
class KlimDatabasePipeValues extends KlimDatabasePipe
{
	public function execute( $result, $field )
	{
		$values = array();

		if ( is_array($result) || is_object($result) ) {
			foreach ( $result as $row ) {
				if ( !is_null($row[$field]) ) {
					$values[] = $row[$field];
				}
			}
		}

		return $values;
	}
}

