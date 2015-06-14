<?php
/**
 * Walidator do polskiego kodu PESEL
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


class ValidatorOfficialPolishPesel extends ValidatorBase
{
	public function check( $data )
	{
		$pesel = $data[0];

		if ( strlen($pesel) != 11 || !is_numeric($pesel) ) {
			return "invalid-pesel-number";
		}

		$steps = array( 1, 3, 7, 9, 1, 3, 7, 9, 1, 3 );
		$sum = 0;

		for ( $i = 0; $i < 10; $i++ ) {
			$sum += $steps[$i] * $pesel[$i];
		}

		$int = 10 - $sum % 10;
		$control_nr = ($int == 10) ? 0 : $int;

		if ( $control_nr == $pesel[10] ) {
			return false;
		} else {
			return "invalid-pesel-number";
		}
	}
}

