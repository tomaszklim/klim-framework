<?php
/**
 * Klasa zarządzająca transparentnym buforowaniem danych w warstwie http
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


class KlimHttpResponseCache
{
	/**
	 * Metoda analizująca nagłówki http otrzymane od zdalnego serwera
	 * i określająca maksymalny dopuszczalny czas cache'owania danych.
	 * Kolejność analizy nagłówków jest zgodna z RFC 2616.
	 */
	public static function computePeriod( $url, $headers, $cacheControl, $defaultPeriod )
	{
		if ( isset($cacheControl["private"]) || isset($cacheControl["no-store"]) || isset($cacheControl["no-cache"]) ) {
			KlimLogger::debug( "curl-cache", "disabling cache for $url [Cache-Control]" );
			return 0;
		}

		$pragma = array();
		if ( isset($headers["Pragma"]) ) {
			$directives = explode( ",", $headers["Pragma"] );
			foreach ( $directives as $directive ) {
				@list($name, $value) = explode( "=", trim($directive) );
				$pragma[$name] = $value;
			}
		}

		if ( isset($pragma["no-cache"]) ) {
			KlimLogger::debug( "curl-cache", "disabling cache for $url [Pragma]" );
			return 0;
		}

		$period = $defaultPeriod;

		if ( isset($headers["Expires"]) ) {
			if ( is_numeric($headers["Expires"]) ) {
				$period = (int)$headers["Expires"];
			} else {
				$period = KlimTime::getTimestamp(GMT_UNIX, $headers["Expires"]) - time();
			}
		}

		if ( isset($cacheControl["max-age"]) && is_numeric($cacheControl["max-age"]) ) {
			$age = (int)$cacheControl["max-age"];
			if ( $age > 500000000 ) {
				$period = $age - time();
			} else {
				$period = $age;
			}
		}

		if ( isset($cacheControl["s-maxage"]) && is_numeric($cacheControl["s-maxage"]) ) {
			$age = (int)$cacheControl["s-maxage"];
			if ( $age > 500000000 ) {
				$period = $age - time();
			} else {
				$period = $age;
			}
		}

		if ( $period < 0 ) {
			$period = 0;
		}

		return min( $defaultPeriod, $period );
	}
}

