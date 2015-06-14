<?php
/**
 * Walidator sprawdzający poprawność podanego ciągu tekstowego
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


class ValidatorBasicExpression extends ValidatorBase
{
	/**
	 * Ten walidator jest uniwersalnym walidatorem do różnego typu ciągów
	 * tekstowych, które muszą spełniac wymagania odpowiedniej długości,
	 * zawartych znaków itp. Sposób użycia w innych walidatorach:
	 *
	 *  $checker = new ValidatorBasicExpression();
	 *  return $checker->check( array($data[0], "database-name", 2, 60, "/[^a-z0-9_]/") );
	 *
	 * Oznacza to, że tekst musi albo być pusty, albo mieć od 2 do 60
	 * znaków, oraz _nie_ spełniać podanego wyrażenia regularnego.
	 * Jeśli którykolwiek z tych warunków nie jest spełniony, generowany
	 * będzie odpowiedni komunikat błędu, zaczynający się zawsze od
	 * "database-name".
	 *
	 * Podanie true w kolejnym parametrze dodaje sprawdzanie, czy tekst
	 * nie jest pusty (domyślnie pusty tekst jest traktowany jako
	 * spełnienie wszystkich warunków, parametr true to zmienia).
	 */
	public function check( $data )
	{
		$msg = false;
		$required = (bool)@$data[5];

		if ( !$required && empty($data[0]) ) {
			return false;

		} else if ( empty($data[0]) ) {
			$msg = "missing-$1-field";

		} else if ( !is_string($data[0]) ) {
			$msg = "$1-has-invalid-format";

		} else if ( is_numeric($data[2]) && $data[2] > 0 && strlen($data[0]) < $data[2] ) {
			$msg = "$1-too-short";

		} else if ( is_numeric($data[3]) && $data[3] > 0 && strlen($data[0]) > $data[3] ) {
			$msg = "$1-too-long";

		} else if ( !empty($data[4]) && preg_match($data[4], $data[0]) ) {
			$msg = "$1-has-invalid-characters";

		} else {
			return false;
		}

		return array( $msg, strtolower($data[1]) );
	}
}

