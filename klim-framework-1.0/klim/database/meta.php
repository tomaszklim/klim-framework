<?php
/**
 * Klasa bazowa do dekodowania schematów baz danych
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


abstract class KlimDatabaseMeta
{
	protected $db;
	protected $dbname;

	protected static $vendors = array (
		"access",
		"ibase",
		"mdb",
		"mssql",
		"mysql",
		"oracle",
		"pervasive",
		"postgres",
		"sqlite",
	);

	public static function getInstance( $db, $database_name )
	{
		$vendor = $db->getVendor( $database_name );

		if ( !in_array($vendor, self::$vendors, true) ) {
			throw new KlimApplicationException( "unsupported database vendor: $vendor" );  // TODO: ibm-db2
		}

		$raw = str_replace( ".", "_", "klim.database.meta.$vendor" );
		$class = KlimCamel::encode( $raw, true );

		return new $class( $db, $database_name );
	}

	protected function __construct( $db, $database_name )
	{
		$this->db = $db;
		$this->dbname = $database_name;
	}

	protected function query( $sql )
	{
		return $this->db->rawQuery( $this->dbname, $sql );
	}

	/**
	 * Przetestowane dla MySQL i PostgreSQL, dla innych baz (szczególnie
	 * ODBC) struktura namiarów bazy danych może być całkiem różna.
	 */
	protected function getRawName()
	{
		$config = KlimDatabasePool::getConfig();
		return $config[$this->dbname]["name"];
	}

	protected function listSimple( $sql, $lower = false )
	{
		$rows = $this->query( $sql );
		$out = array();

		foreach ( $rows as $row ) {
			$out[] = $lower ? strtolower($row[0]) : $row[0];
		}

		return $out;
	}

	/**
	 * Metoda zwracająca listę tabel.
	 */
	abstract public function getTables();

	/**
	 * Metoda zwracająca listę procedur składowanych.
	 */
	abstract public function getProcedures();

	/**
	 * Metoda zwracająca listę kolumn i opisujących je atrybutów.
	 */
	abstract public function getFields( $table );
}

