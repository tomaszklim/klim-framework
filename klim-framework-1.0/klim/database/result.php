<?php
/**
 * Klasa bazowa wrappera do wyniku zapytania select
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
 * Sterownik do baz danych jest podzielony na 2 części: sterownik właściwy,
 * oraz wrapper do obsługi wyników zapytań select. Klasy do obsługi wyniku
 * zapewniają możliwość iterowania po obiekcie, jak po zwykłym arrayu z
 * kompletem danych, podczas gdy w tle obiekt dociąga wymagane wiersze
 * wyniku dopiero w momencie, gdy są rzeczywiście potrzebne.
 */
abstract class KlimDatabaseResult implements Countable, Iterator, ArrayAccess
{
	private $current_row = false;
	private $sync = false;
	protected $caching = false;
	protected $encoding = false;
	protected $cursor = 0;
	protected $rows = array();
	protected $nrows = 0;
	protected $nfields = 0;

	public function __destruct()
	{
		$this->free();
		unset( $this->rows );
		unset( $this->current_row );
	}

	/**
	 * Ustawia metodę kodowania znaków w wyniku. Ta metoda
	 * powinna być używana tylko i wyłącznie przez klasę KlimProxy.
	 */
	public function setProxyEncoding( $encoding )
	{
		$this->encoding = $encoding;
	}

	/**
	 * Podmienia kodowanie znaków w zwracanym wierszu wyniku. Używane
	 * zamiast transparentnej konwersji w klasie KlimProxy, gdyż ta
	 * klasa uniemożliwia implementację interfejsów, dzięki którym
	 * wynik jest widziany jako array.
	 */
	protected function convert( $data )
	{
		if ( !$this->encoding ) {
			return $data;
		}

		if ( !is_array($data) ) {
			return iconv( $this->encoding, "utf-8//TRANSLIT", $data );
		}

		foreach ( $data as $key => $value ) {
			$data[$key] = $this->convert( $value );
		}

		return $data;
	}

	/**
	 * Przesuwa wskaźnik dostępu do danych na podany wiersz wyniku. Tak
	 * naprawdę stara się tylko przesunąć wskaźnik w pamięci, a operację
	 * na poziomie bazy danych opóźnić do momentu, gdy metoda fetch nie
	 * znajdzie w pamięci cache'owanych danych dla danego wiersza.
	 */
	protected function seek( $row )
	{
		if ( $this->cursor !== $row ) {
			$this->cursor = $row;

			if ( isset($this->rows[$row]) ) {
				$this->sync = true;
			} else {
				$this->seekRow( $row );
			}
		}
	}

	/**
	 * Zwraca bieżący wiersz wyniku zapytania. Jeśli znajduje go w cache,
	 * zwraca z cache - jeśli nie, pobiera z bazy i wstawia do cache do
	 * przyszłego użycia.
	 */
	protected function fetch()
	{
		if ( $this->cursor < 0 || $this->cursor >= $this->nrows ) {
			return false;
		}

		if ( isset($this->rows[$this->cursor]) ) {
			return $this->convert( $this->rows[$this->cursor++] );
		}

		if ( $this->sync ) {
			$this->seekRow( $this->cursor );
			$this->sync = false;
		}

		$row = $this->fetchRow();

		if ( $this->caching ) {
			$this->rows[$this->cursor] = $row;
		}

		$this->cursor++;
		return $this->convert( $row );
	}

	/**
	 * Metody abstrakcyjne do implementacji przez sterowniki
	 */

	/**
	 * Zwalnia pamięć zajmowaną przez wynik zapytania, usuwając go.
	 */
	abstract protected function free();

	/**
	 * Ustawia numer wiersza, od którego będzie się odbywał dalszy
	 * sekwencyjny dostęp do danych.
	 */
	abstract protected function seekRow( $row );

	/**
	 * Pobiera z serwera pojedynczy wiersz wyniku zapytania i zwraca go
	 * w formie arraya, z kluczami zarówno numerycznymi, jak i wg nazw
	 * kolumn.
	 */
	abstract protected function fetchRow();

	/**
	 * Standardowe metody publiczne
	 */

	public function numRows()
	{
		return $this->nrows;
	}

	public function numFields()
	{
		return $this->nfields;
	}

	/**
	 * Metody implementujące interfejs Countable
	 */

	public function count()
	{
		return $this->nrows;
	}

	/**
	 * Metody implementujące interfejs Iterator
	 */

	public function rewind() {
		$this->seek( 0 );
		$this->current_row = false;
	}

	public function current() {
		if ( !$this->current_row ) {
			$this->next();
		}
		return $this->current_row;
	}

	public function key() {
		return $this->cursor - 1;
	}

	public function next() {
		$this->current_row = $this->fetch();
	}

	public function valid() {
		return $this->current() !== false;
	}

	/**
	 * Metody implementujące interfejs ArrayAccess
	 */

	public function offsetSet( $row, $value ) {
		throw new KlimApplicationException( "tried to overwrite result row $row" );
	}

	public function offsetUnset( $row ) {
		throw new KlimApplicationException( "tried to unset result row $row" );
	}

	public function offsetExists( $row ) {
		return ( $row >= 0 && $row < $this->nrows );
	}

	public function offsetGet( $row ) {
		if ( $this->offsetExists($row) ) {
			$this->seek( $row );
			return $this->fetch();
		} else {
			return false;
		}
	}
}

