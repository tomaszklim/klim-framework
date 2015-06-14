<?php
/**
 * Klasa ładująca definicje formularzy używanych przez API
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


class KlimApiConfig
{
	protected static $forms = array();

	/**
	 * Metoda ładująca definicję podanego formularza.
	 */
	public static function getForm( $provider, $method, $version, $id )
	{
		if ( isset(self::$forms[$provider][$method][$version][$id]) ) {
			return self::$forms[$provider][$method][$version][$id];
		}

		if ( !@include("config/api/forms/$provider/$method/v$version/$id.php") ) {
			throw new KlimApplicationException( "cannot load form definition file for $provider:$method:$version:$id" );
		}

		if ( !isset($config) || !is_array($config) ) {
			throw new KlimApplicationException( "invalid form definition file for $provider:$method:$version:$id" );
		}

		self::$forms[$provider][$method][$version][$id] = $config;
		return $config;
	}
}

