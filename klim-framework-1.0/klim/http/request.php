<?php
/**
 * Klasa pomocnicza do budowania requestów http
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


class KlimHttpRequest
{
	/**
	 * Rekurencyjnie tworzy query string na podstawie tablicy parametrów.
	 */
	public static function buildQuery( $vars, $pattern = false )
	{
		$query = array();
		foreach ( $vars as $key => $value ) {

			$enckey = urlencode( $key );

			if ( $pattern ) {
				$newkey = $pattern . "[" . $enckey . "]";
			} else {
				$newkey = $enckey;
			}

			if ( is_array($value) ) {
				$query[] = self::buildQuery( $value, $newkey );
			} else {
				$query[] = $newkey . "=" . urlencode( $value );
			}
		}

		return ( count($query) > 0 ? join("&", $query) : "" );
	}

	/**
	 * Tworzy docelowy url na podstawie bazowego url i tablicy parametrów.
	 *
	 * Bazowy url może być albo rzeczywistym urlem, albo urlem wyciągniętym
	 * ze znacznika "base href". Istotne tylko, aby był bezwzględny.
	 */
	public static function buildUrl( $url, $vars )
	{
		$query = ( !empty($vars) && is_array($vars) ? self::buildQuery($vars) : $vars );

		/** Strip section part - it's ignored by http protocol anyway. */
		if ( $pos = strpos($url, "#") ) {
			$url = substr( $url, 0, $pos );
		}

		if ( !empty($query) ) {

			/**
			 * Prepend proper delimiter, instead of just ?, which may be incorrect,
			 * if there are additional query parameters passed directly in url...
			 */
			$start = ( strpos($url, "?") === false ? "?" : "&" );

			/** ...but insert it only if there are any query parameters to pass. */
			return $url . $start . $query;
		} else {
			return $url;
		}
	}
}

