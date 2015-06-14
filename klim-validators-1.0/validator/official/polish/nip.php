<?php
/**
 * Walidator do polskiego kodu NIP
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


class ValidatorOfficialPolishNip extends ValidatorBase
{
	public function check( $data )
	{
		$nip = $data[0];
		$nip = str_replace( "-", "", $nip );
		$nip = str_replace( " ", "", $nip );

		if ( strlen($nip) != 10 ) {
			return "invalid-nip-number";
		}

		$steps = array( 6, 5, 7, 2, 3, 4, 5, 6, 7 );
		$sum_nb = 0;

		for ( $x = 0; $x < 9; $x++ ) {
			$sum_nb += $steps[$x] * $nip[$x];
		}

		$sum_m = $sum_nb % 11;

		if ( $sum_m == 10 ) {
			$sum_m = 0;
		}

		if ( $sum_m == $nip[9] ) {
			return false;
		} else {
			return "invalid-nip-number";
		}
	}
}

