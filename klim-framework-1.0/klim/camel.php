<?php
/**
 * Obsługa kodowania CamelCase
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


class KlimCamel
{
	public static function encode( $value, $capitalise_first_char = true )
	{
		if ( $capitalise_first_char ) {
			$value[0] = strtoupper( $value[0] );
		}

		return preg_replace_callback( "/_([a-z])/", array("KlimCamel", "camelize"), $value );
	}

	protected static function camelize( $input )
	{
		return strtoupper( $input[1] );
	}

	public static function decode( $value )
	{
		return strtolower( preg_replace("/(?<=[a-z])(?=[A-Z])/", "_", $value) );
	}
}

