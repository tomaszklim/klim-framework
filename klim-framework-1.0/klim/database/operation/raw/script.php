<?php
/**
 * Klasa implementująca obsługę surowych skryptów sql
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
 * Metoda wykonująca kolejno zapytania z podanego skryptu. Powinna być
 * stosowana przede wszystkim do wykonywania skryptów DDL, operujących
 * na strukturach podanej bazy.
 */
class KlimDatabaseOperationRawScript extends KlimDatabaseOperation
{
	public function execute( $db, $file, $variables = array(), $use_transaction = false )
	{
		$obj = $this->controller->getConnection( $db, DB_DIE );

		if ( $obj === false ) {
			return false;
		}

		$script = new KlimDatabaseQueryScript( $file );
		$queries = $script->getQueries( $variables );

		if ( $use_transaction )
		{
			$ret = $obj->begin( $db, $this->controller->getInstance() );

			if ( $ret === false ) {
				throw new KlimRuntimeDatabaseException( "database runtime error", $obj->lastError() );
			}
		}

		foreach ( $queries as $query )
		{
			$ret = $obj->query( $query );

			if ( $ret === false ) {
				$error = $obj->lastError();
				$obj->close();
				throw new KlimRuntimeDatabaseException( "database runtime error", $error );
			}
		}

		if ( $use_transaction )
		{
			$ret = $obj->commit();

			if ( $ret === false ) {
				$error = $obj->lastError();
				$obj->close();
				throw new KlimRuntimeDatabaseException( "database runtime error", $error );
			}
		}

		return $ret;
	}
}

