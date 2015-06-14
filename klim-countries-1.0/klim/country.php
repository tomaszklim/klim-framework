<?php
/**
 * Klasa do zarządzania nazwami krajów i ich kodami ISO-3166
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


class KlimCountry
{
	protected static $iso = array();
	protected static $names = array();

	protected static function loadIso()
	{
		if ( !empty(self::$iso) ) {
			return self::$iso;
		}

		if ( !include("countries/countries.iso.php") ) {
			throw new KlimApplicationException( "cannot load country iso codes" );
		}

		if ( !isset($iso) || !is_array($iso) ) {
			throw new KlimApplicationException( "invalid country iso codes table" );
		}

		self::$iso = $iso;
		return $iso;
	}

	protected static function loadTranslations( $language_id )
	{
		if ( !empty(self::$names[$language_id]) ) {
			return self::$names[$language_id];
		}

		if ( !include("countries/countries.$language_id.php") ) {
			throw new KlimApplicationException( "cannot load country name translations" );
		}

		if ( !isset($countries) || !is_array($countries) ) {
			throw new KlimApplicationException( "invalid country name translations table" );
		}

		self::$names[$language_id] = $countries;
		return $countries;
	}

	/**
	 * Metoda zwracająca identyfikator numeryczny kraju (z powyższej listy)
	 * dla podanego kodu ISO 3166. Dla nieznanego kraju zwraca false.
	 */
	public static function getCountryIdFromIso( $iso )
	{
		self::loadIso();
		$iso2 = strtolower( $iso );

		if ( $iso2 == "en" ) {
			return 0;
		}

		foreach ( self::$iso as $id => $code ) {
			if ( $iso2 == $code ) {
				return $id;
			}
		}

		return false;
	}

	/**
	 * Zwraca listę identyfikatorów numerycznych dla wszystkich krajów
	 * znanych aplikacji (przy czym niekoniecznie każdy kraj musi być
	 * w jakikolwiek sposób obsługiwany).
	 */
	public static function getSupportedCountryIds()
	{
		self::loadIso();
		return array_keys( self::$iso );
	}

	/**
	 * Zwraca nazwę kraju o podanym numerze w podanym języku.
	 * Jeśli w tablicach konfiguracyjnych nie ma nazwy kraju w tym języku,
	 * zwraca nazwę kraju w języku o tym samym numerze, co numer kraju.
	 */
	public static function getCountryName( $country_id, $language_id )
	{
		if ( !is_numeric($country_id) ) {
			throw new KlimApplicationException( "invalid country_id" );
		}

		if ( !is_numeric($language_id) ) {
			throw new KlimApplicationException( "invalid language_id" );
		}

		self::loadTranslations( $language_id );

		if ( isset(self::$names[$language_id][$country_id]) ) {
			return self::$names[$language_id][$country_id];
		}

		self::loadTranslations( $country_id );

		if ( isset(self::$names[$country_id][$country_id]) ) {
			return self::$names[$country_id][$country_id];
		} else {
			return false;
		}
	}
}

