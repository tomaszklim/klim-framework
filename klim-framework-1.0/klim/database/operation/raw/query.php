<?php
/**
 * Klasa implementująca obsługę surowych zapytań
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
 * Metoda wykonująca podane bezpośrednio zapytanie na podanej bazie.
 */
class KlimDatabaseOperationRawQuery extends KlimDatabaseOperation
{
	public function execute( $db, $query, $binds = array(), $die = DB_DIE )
	{
		$obj = $this->controller->getConnection( $db, $die );

		if ( $obj === false ) {
			return false;
		}

		$ret = $obj->query( $query, $binds );

		if ( $ret === false ) {
			return $this->isQueryFatal($obj, $die, false) ? $this->errorHandler($obj, $die) : false;
		}

		return $ret;
	}
}

