<?php
/**
 * Klasa implementująca zapytanie DELETE
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
 * Metoda wykonująca zapytanie DELETE. Składnia:
 *
 *   $db = new KlimDatabase();
 *   $db->delete( "users", array (
 *       "login" => "tomek"
 *   ) );
 *
 * Zwraca ilość wierszy usuniętych z tabeli lub 0 w przypadku
 * obsłużonego błędu.
 */
class KlimDatabaseOperationDelete extends KlimDatabaseOperation
{
	public function execute( $schema, $where, $die = DB_DIE )
	{
		$definition = KlimDatabaseSchema::getDefinition( $schema );

		if ( $definition["class"] != "database" ) {
			throw new KlimApplicationException( "invalid schema $schema class" );
		}

		$obj = $this->controller->getConnection( $definition["db"], $die );

		if ( $obj === false ) {
			return false;
		}

		$where = new KlimDatabaseQueryWhere( $where, $definition, $obj );

		$clause = $where->getClause();
		$binds = $where->getBinds();
		$table = $definition["table"];

		if ( empty($clause) ) {
			KlimLogger::info( "db", "executing delete query with no where clause (deleting all rows from table $table)" );
			$query = "delete from $table";
		} else {
			$query = "delete from $table where $clause";
		}

		$ret = $obj->query( $query, $binds );

		if ( $ret === false ) {
			return $this->isQueryFatal($obj, $die, true) ? $this->errorHandler($obj, $die) : false;
		}

		$affected = $obj->affectedRows();

		if ( !$affected && $obj->isReadOnly() ) {
			return !is_set_bin_value($die, DB_NO_DIE_IF_READONLY) ? $this->errorHandler($obj, $die) : false;
		}

		return $affected;
	}
}

