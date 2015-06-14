<?php
/**
 * Walidator sprawdzający siłę podanego hasła
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


class ValidatorPasswordStrength extends ValidatorBase
{
	public function check( $data )
	{
		$password = $data[0];
		$username = @$data[1];
		$fullname = @$data[2];

		if ( empty($password) ) {
			return "password-empty";

		} else if ( strlen($password) < 8 ) {
			return "password-too-short";

		} else if ( strlen($password) > 40 ) {
			return "password-too-long";

		// TODO: tutaj można dodać różne techniki walidacji siły hasła
		} else if ( strtolower($password) == strtolower($username) || strtolower($password) == strtolower($fullname) ) {
			return "password-too-weak";

		} else {
			return false;
		}
	}
}

