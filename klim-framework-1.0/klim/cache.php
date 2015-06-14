<?php
/**
 * Uniwersalny cache do danych
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


/**
 * To jest klasa bazowa dla poszczególnych implementacji mechanizmów
 * cache'ujących dane. Implementacje dostępne obecnie to m.in.:
 *
 *  a) lokalne:
 *
 *   - eAccelerator
 *   - APC
 *   - XCache
 *   - Turck MM Cache
 *   - Zend Server SHM Cache
 *   - Zend Server Disk Cache
 *   - Microsoft Windows Cache
 *   - własny silnik plikowy
 *
 *  b) sieciowe, współdzielące dane:
 *
 *   - memcached
 *   - Redis
 *   - Tokyo Tyrant
 *   - baza danych
 *
 * Klasa udostępnia zestaw prostych metod do operowania na danych, podobnych
 * składniowo do funkcji udostępnianych przez każdy z silników.
 *
 * Własny silnik plikowy nie posiada żadnej akceleracji typu zaawansowane
 * algorytmy, moduł w C, pamięć SHM itp., a zatem jest mniej wydajny od
 * pozostałych silników, w szczególności przy dużej ilości małych porcji
 * danych, jest jednak dobrym rozwiązaniem do cache'owania danych w dużych
 * porcjach, a stosunkowo rzadko odczytywanych i/lub długo trzymanych.
 * Przy takim zastosowaniu koszt gigabajta cache jest znacznie niższy, niż
 * dla pozostałych silników. Rozwiązanie to jest także przydatne na tanich
 * hostingach, które nie udostępniają żadnego silnika cache'ującego.
 *
 * Wszystkie silniki są obsługiwane w ten sam sposób, tymi samymi metodami,
 * należy jednak pamiętać o tym, że każdy z silników ma całkiem różną
 * charakterystykę wydajności od pozostałych, zatem wybierając cache należy
 * kierować się przede wszystkim sposobem użycia cache'owanych danych przez
 * aplikację.
 */
abstract class KlimCache
{
	public static function getInstance( $id )
	{
		return KlimCacheLoader::getInstance( $id );
	}

	abstract protected function rawGet( $key );
	abstract protected function rawSet( $key, $value, $period );
	abstract protected function rawDelete( $key );

	/**
	 * Przekształca podany przez aplikację klucz do danych na najlepszy
	 * możliwy klucz fizyczny, zgodnie z ograniczeniami danego cache'a.
	 */
	protected function key( $key )
	{
		return md5( $key ) . "_" . substr( urlencode($key), 0, 110 );
	}

	public function get( $key )
	{
		$data = $this->rawGet( $this->key($key) );
		return is_string($data) ? unserialize($data) : $data;
	}

	public function set( $key, $value, $period )
	{
		return $this->rawSet( $this->key($key), serialize($value), $period );
	}

	public function delete( $key )
	{
		return $this->rawDelete( $this->key($key) );
	}

	/**
	 * Oczyszcza cache ze starych, przeterminowanych danych.
	 */
	public function clean()
	{
		return true;
	}
}

