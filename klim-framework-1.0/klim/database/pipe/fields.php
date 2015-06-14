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
 * Filtr ograniczający zwracane kolumny w kolejnych wierszach
 * do kolumn z podanej listy.
 *
 * TODO: można dodać obsługę stringów zawierających listę pól
 *       oddzieloną przecinkami (jak w zapytaniu sql), oraz
 *       rozpoznawanie aliasów (AS) i funkcji (min, max, avg itp.),
 *       po czym rzucać wyjątkiem dopiero jeśli naprawdę nie można
 *       już nic zrobić ze zmienną $fields.
 *       można nawet spróbować emulować proste funkcje typu
 *       count(*), count(1), min(nazwapola), max(nazwapola) itp.
 */
class KlimDatabasePipeFields extends KlimDatabasePipe
{
	public function execute( $rows, $fields )
	{
		if ( !is_array($fields) && $fields != "*" ) {
			throw new KlimApplicationException( "invalid input data" );
		}

		if ( $fields == "*" || in_array("*", $fields, true) ) {
			return $rows;
		}

		$out = array();

		foreach ( $rows as $row ) {
			$tmp = array();

			foreach ( $row as $key => $value )
				if ( in_array($key, $fields, true) )
					$tmp[$key] = $value;

			$out[] = $tmp;
		}

		return $out;
	}
}

