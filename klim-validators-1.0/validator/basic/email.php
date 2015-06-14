<?php
/**
 * Walidator sprawdzający poprawność adresu email
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


class ValidatorBasicEmail extends ValidatorBase
{
	public function check( $data )
	{
		$address = $data[0];
		$mask = "/^([a-z0-9+-_.]+)@(([a-z0-9-_]+\.)+[a-z]{2,6})\$/i";

		if ( strlen($address) < 256 && strpos($address, "@") && strpos($address, ".") && preg_match($mask, $address) ) {
			return false;
		} else {
			return "invalid-email-address";
		}
	}
}

