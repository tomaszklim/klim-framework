<?php
/**
 * Klasa implementująca specjalną wersję zapytania UPDATE
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
 * Metoda wykonująca specjalną wersję zapytania UPDATE, w której
 * konkretna ma wartość zwiększaną o podaną liczbę. Składnia:
 *
 *   $db = new KlimDatabase();
 *   $db->updateField( "users", "us_version", 1, array (
 *       "login" => "tomek"
 *   ) );
 *
 * Zwraca ilość wierszy zmodyfikowanych, bądź zakwalifikowanych
 * do modyfikacji (zależnie od typu bazy danych). W przypadku
 * obsłużonego błędu zwraca 0.
 */
class KlimDatabaseOperationUpdateField extends KlimDatabaseOperation
{
	public function execute( $schema, $field, $increment, $where, $die = DB_DIE )
	{
		$definition = KlimDatabaseSchema::getDefinition( $schema );

		if ( $definition["class"] != "database" ) {
			throw new KlimApplicationException( "invalid schema $schema class" );
		}

		if ( !isset($definition["fields"][$field]) ) {
			throw new KlimApplicationException( "unknown field $field" );
		}

		$type = $definition["fields"][$field][0];

		if ( $type != "int" ) {
			throw new KlimApplicationException( "invalid field $field type $type" );
		}

		if ( !is_numeric($increment) ) {
			throw new KlimRuntimeDatabaseException( "invalid field $field increment value" );
		}

		if ( (int)$increment == 0 ) {
			KlimLogger::debug( "db", "trying to increment field $field by 0, skipping query" );
			return 0;
		}

		$obj = $this->controller->getConnection( $definition["db"], $die );

		if ( $obj === false ) {
			return false;
		}

		$where = new KlimDatabaseQueryWhere( $where, $definition, $obj );

		$clause = $where->getClause();
		$binds = $where->getBinds();
		$table = $definition["table"];

		$sign = ( $increment > 0 ? "+" : "-" );
		$value = abs( $increment );

		$query = "update $table set $field = $field $sign $value" . ( empty($clause) ? "" : " where $clause" );

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

