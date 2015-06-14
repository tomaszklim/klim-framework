<?php
/**
 * Klasa implementująca zapytanie INSERT
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
 * Metoda wykonująca zapytanie INSERT. Składnia:
 *
 *   $db = new KlimDatabase();
 *   $db->insert( "users", array (
 *       "login" => "tomek",
 *       "password" => "1234"
 *   ) );
 *
 * Zwraca wartość klucza głównego dla ostatnio wstawionego
 * do tabeli wiersza lub 0, jeśli ta kolumna nie jest
 * skojarzona z sekwencją, nie jest polem auto_increment itp.
 */
class KlimDatabaseOperationInsert extends KlimDatabaseOperation
{
	public function execute( $schema, $fields, $die = DB_DIE )
	{
		$definition = KlimDatabaseSchema::getDefinition( $schema );

		if ( $definition["class"] != "database" ) {
			throw new KlimApplicationException( "invalid schema $schema class" );
		}

		$obj = $this->controller->getConnection( $definition["db"], $die );

		if ( $obj === false ) {
			return false;
		}

		$insert = new KlimDatabaseQueryInsert( $fields, $definition, $obj );

		$query = $insert->getQuery();
		$binds = $insert->getBinds();

		$ret = $obj->query( $query, $binds );

		if ( $ret === false ) {
			return $this->isQueryFatal($obj, $die, true) ? $this->errorHandler($obj, $die) : false;
		}

		$last_id = $insert->getLastId();

		if ( !$last_id ) {
			$last_id = $obj->insertId();
		}

		if ( !$last_id && $obj->isReadOnly() ) {
			return !is_set_bin_value($die, DB_NO_DIE_IF_READONLY) ? $this->errorHandler($obj, $die) : false;
		}

		return $last_id;
	}
}

