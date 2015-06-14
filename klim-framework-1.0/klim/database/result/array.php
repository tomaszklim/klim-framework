<?php
/**
 * Wrapper do wyniku zapytania dostarczonego w postaci gotowego arraya
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
 * Wymagania odnośnie danych przekazywanych do tej klasy:
 *
 *  - klucze tablicy $result muszą być numeryczne,
 *    numerowane od 0 wzwyż, ciągłe (bez dziur w numeracji)
 *
 *  - zamiast tablicy może być dostarczony obiekt, przy czym
 *    musi on implementować interfejsy Countable i ArrayAccess
 *
 *  - wartości dla poszczególnych kluczy muszą być arrayami
 *    zawierającymi klucze tekstowe (nazwy kolumn)
 *
 *  - w wartościach dodatkowe klucze numeryczne nie są wymagane
 *
 *  - wiersz o numerze 0 nie może zawierać nagłówka, musi
 *    zawierać już pierwszy wiersz właściwych danych
 *
 *  - kod nadrzędny powinien wywołać na obiekcie metodę
 *    setProxyEncoding(), przekazując mu nazwę kodowania
 *    znaków w ramach tablicy $result
 */
class KlimDatabaseResultArray extends KlimDatabaseResult
{
	public function __construct( $db, $result )
	{
		$this->rows = $result;
		$this->nrows = count( $result );
		$this->nfields = count( $result[0] );
	}

	protected function free()
	{
	}

	protected function seekRow( $row )
	{
	}

	protected function fetchRow()
	{
		return false;
	}
}

