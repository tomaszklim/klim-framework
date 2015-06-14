<?php
/**
 * Walidator sprawdzający poprawność nazwy lokalnego pliku
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


class ValidatorBasicFsFilename extends ValidatorBase
{
	public function check( $data )
	{
		$mask = "/[^A-Za-z0-9-_+=.\/\(\) ]/";  // na razie nie dopuszczamy ścieżek Windows z literami dysków

		$checker = new ValidatorBasicExpression();
		return $checker->check( array($data[0], "filename", 2, 255, $mask, true) );
	}
}

