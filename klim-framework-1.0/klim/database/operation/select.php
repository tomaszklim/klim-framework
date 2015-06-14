<?php
/**
 * Klasa implementująca zapytanie SELECT
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
 * Metoda wykonująca zapytanie SELECT. Składnia:
 *
 *   $db = new KlimDatabase();
 *   $db->select( "users", "login, password, country_id", array (
 *       "country_id" => array( 1, 56 ),
 *       "login" => array( "like", "t%" )
 *       "registered" => array( ">", "2009-01-01" )
 *   ), array (
 *       "order by" => "login",
 *       "limit" => 100,
 *       "offset" => 1200,
 *   ) );
 *
 * Zwraca wynik w postaci klasy implementującej wzorce Countable,
 * Iterator i ArrayAccess, a zatem która "udaje" arraya. W przypadku
 * obsłużonego błędu zwraca false.
 *
 * Dopuszczalne klauzule do czwartego parametru:
 *   group by
 *   order by
 *   having
 *   limit
 *   offset
 */
class KlimDatabaseOperationSelect extends KlimDatabaseOperation
{
	protected function loadAdapter( $class, $schema, $fields, $where, $clauses, $die )
	{
		$adapter = KlimDatabaseHandler::getAdapterClass( $class );

		if ( !$adapter ) {
			throw new KlimApplicationException( "invalid schema $schema class" );
		}

		$obj = new $adapter( $this->controller );
		return $obj->execute( $schema, $fields, $where, $clauses, $die );
	}

	public function execute( $schema, $fields, $where = false, $clauses = array(), $die = DB_DIE )
	{
		$definition = KlimDatabaseSchema::getDefinition( $schema );

		if ( $definition["class"] != "database" ) {
			return $this->loadAdapter( $definition["class"], $schema, $fields, $where, $clauses, $die );
		}

		$obj = $this->controller->getConnection( $definition["db"], $die );

		if ( $obj === false ) {
			return false;
		}

		$where = new KlimDatabaseQueryWhere( $where, $definition, $obj );

		$clauses["fields"] = $fields;
		$clauses["from"  ] = $definition["table"];
		$clauses["where" ] = $where->getClause();

		$select = new KlimDatabaseQuerySelect( $clauses, $obj );

		$query = $select->getQuery();
		$binds = $where->getBinds();

		$ret = $obj->query( $query, $binds );

		if ( $ret === false ) {
			return $this->isQueryFatal($obj, $die, false) ? $this->errorHandler($obj, $die) : false;
		}

		return $ret;
	}
}

