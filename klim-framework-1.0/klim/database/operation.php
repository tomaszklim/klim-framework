<?php
/**
 * Klasa bazowa dla implementacji kompletnych zapytań
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
 * W klasie pochodnej należy zaimplementować metodę execute() z listą
 * argumentów dokładnie taką, jaką ma odbierać metoda __call w klasie
 * KlimDatabase. Poniżej brak definicji metody abstrakcyjnej execute()
 * właśnie z uwagi na zmienną listę argumentów.
 */
abstract class KlimDatabaseOperation
{
	protected $controller;

	public function __construct( $controller )
	{
		$this->controller = $controller;
	}

	protected function errorHandler( $obj, $die )
	{
		if ( is_set_bin_value($die, DB_NO_DIE_ELSE) ) {
			return false;
		}

		throw new KlimRuntimeDatabaseException( "runtime error: ".$obj->lastError() );
	}

	protected function isQueryFatal( $obj, $die, $write )
	{
		if ( $write && $obj->isReadOnly() && is_set_bin_value($die, DB_NO_DIE_IF_READONLY) ) {
			return false;

		} else if ( $obj->isDuplicate() && is_set_bin_value($die, DB_NO_DIE_IF_DUPLICATE) ) {
			return false;

		} else if ( $obj->isFetchError() && is_set_bin_value($die, DB_NO_DIE_IF_DISCONNECT) ) {
			return false;

		} else if ( $obj->isDisconnect() && is_set_bin_value($die, DB_NO_DIE_IF_DISCONNECT) ) {
			return false;

		} else {
			return true;
		}
	}
}

