<?php
/**
 * Klasa do ładowania haseł z plików lokalnych (spoza repozytorium)
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


class KlimPassword
{
	private static $database = array();

	public static function getFromFile( $type, $key )
	{
		if ( !isset(self::$database[$type]) ) {
			/**
			 * TODO: dodać sprawdzanie czy Windows i ładowanie pliku z
			 * alternatywnej ścieżki - tylko najpierw trzeba wymyśleć
			 * dobrą ścieżkę, w której będzie można swobodnie aktualizować
			 * plik z hasłami bez naruszenia bezpieczeństwa całego systemu.
			 */
			$path = Bootstrap::getApplicationRoot() . "/passwords/$type";
			$lines = @file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

			if ( !empty($lines) ) {
				foreach ( $lines as $line ) {
					$parts = explode( "\t", $line, 2 );
					self::$database[$type][$parts[0]] = $parts[1];
				}
			}
		}

		if ( empty(self::$database[$type][$key]) ) {
			throw new KlimRuntimeException( "password not found, type $type, key $key" );
		} else {
			return self::$database[$type][$key];
		}
	}
}

