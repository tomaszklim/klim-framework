<?php
/**
 * Klasa zarządzająca adapterami i sterownikami do baz danych
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


class KlimDatabaseHandler
{
	protected static $adapters = array (
		"csv"           => "KlimDatabaseAdapterCsv",
		"sheet"         => "KlimDatabaseAdapterSheet",
		"nntp.group"    => "KlimDatabaseAdapterNntpGroup",
		"nntp.articles" => "KlimDatabaseAdapterNntpArticles",
	);

	protected static $drivers = array (
		"oracle"    => array( "KlimDatabaseDriverOracle",    "oci_new_connect" ),
		"sqlite"    => array( "KlimDatabaseDriverSqlite",    "sqlite_open"     ),
		"mysqli"    => array( "KlimDatabaseDriverMysqli",    "mysqli_connect"  ),
		"mysql"     => array( "KlimDatabaseDriverMysql",     "mysql_connect"   ),
		"mssql"     => array( "KlimDatabaseDriverMssql",     "mssql_connect"   ),
		"ibase"     => array( "KlimDatabaseDriverIbase",     "ibase_connect"   ),
		"odbc"      => array( "KlimDatabaseDriverOdbc",      "odbc_connect"    ),
		"access"    => array( "KlimDatabaseDriverAccess",    "odbc_connect"    ),
		"pervasive" => array( "KlimDatabaseDriverPervasive", "odbc_connect"    ),
		"postgres"  => array( "KlimDatabaseDriverPostgres",  "pg_connect"      ),
		"imap"      => array( "KlimDatabaseDriverImap",      "imap_open"       ),
		"nntp"      => array( "KlimDatabaseDriverNntp",      false             ),
	);

	public static function registerAdapterClass( $type, $class )
	{
		if ( !isset(self::$adapters[$type]) ) {
			self::$adapters[$type] = $class;
		}
	}

	public static function registerDriverClass( $type, $class, $function = false )
	{
		if ( !isset(self::$drivers[$type]) ) {
			self::$drivers[$type] = array( $class, $function );
		}
	}

	public static function getAdapterClass( $type )
	{
		if ( isset(self::$adapters[$type]) ) {
			return self::$adapters[$type];
		} else {
			return false;
		}
	}

	public static function getDriverClass( $db, $type )
	{
		if ( !isset(self::$drivers[$type]) ) {
			throw new KlimApplicationException( "unknown database $db type" );
		}

		$class    = self::$drivers[$type][0];
		$function = self::$drivers[$type][1];

		if ( $function && !function_exists($function) ) {
			throw new KlimApplicationException( "cannot find native driver for database $db" );
		}

		return $class;
	}
}

