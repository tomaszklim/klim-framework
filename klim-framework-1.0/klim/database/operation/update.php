<?php
/**
 * Klasa implementująca zapytanie UPDATE
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
 * Metoda wykonująca zapytanie UPDATE. Składnia:
 *
 *   $db = new KlimDatabase();
 *   $db->update( "users", array (
 *       "password" => "1234"
 *   ), array (
 *       "login" => "tomek"
 *   ) );
 *
 * Zwraca ilość wierszy zmodyfikowanych, bądź zakwalifikowanych
 * do modyfikacji (zależnie od typu bazy danych). W przypadku
 * obsłużonego błędu zwraca 0.
 */
class KlimDatabaseOperationUpdate extends KlimDatabaseOperation
{
	public function execute( $schema, $fields, $where, $die = DB_DIE )
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
		$update = new KlimDatabaseQueryUpdate( $fields, $clause, $definition, $obj );

		$query = $update->getQuery();
		$binds = array_merge( $where->getBinds(), $update->getBinds() );

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

