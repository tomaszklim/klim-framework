<?php
/**
 * Sterownik do bazy danych SQLite
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


class KlimDatabaseDriverSqlite extends KlimDatabaseDriver
{
	private $cached_error = false;

	public function getEncoding()
	{
		return "UTF-8";
	}

	public function getVendor()
	{
		return "sqlite";
	}

	public function connect()
	{
		if ( $this->connection ) {
			return true;
		}

		$error = false;

		if ( $this->persistent ) {
			$this->connection = @sqlite_popen( $this->dbname, 0666, $error );
		} else {
			$this->connection = @sqlite_open( $this->dbname, 0666, $error );
		}

		if ( !$this->connection ) {
			$this->cached_error = array( 500, $error );
			KlimLogger::error( "db", "cannot open sqlite database $this->dbname", $error );
			return false;
		}

		// $this->doQuery( "PRAGMA encoding=\"$this->charset\"" );
		return true;
	}

	public function isDisconnect()
	{
		return false;
	}

	// TODO: wykrywanie próby wstawienia duplikatu PK
	public function isDuplicate()
	{
		return false;
	}

	public function isReadOnly()
	{
		return is_writable($this->dbname) ? false : true;
	}

	private function checkError()
	{
		if ( !$this->cached_error && $this->connection ) {
			$errno = sqlite_last_error( $this->connection );
			$error = sqlite_error_string( $errno );
			$this->cached_error = array( $errno, $error );
		}
	}

	public function lastErrno()
	{
		$this->checkError();
		return $this->cached_error ? $this->cached_error[0] : 0;
	}

	public function lastError()
	{
		$this->checkError();
		return $this->cached_error ? $this->cached_error[1] : false;
	}

	public function nextSequenceValue( $seq_name )
	{
		return false;
	}

	public function insertId()
	{
		return $this->connection ? sqlite_last_insert_rowid($this->connection) : 0;
	}

	public function affectedRows()
	{
		return $this->connection ? sqlite_changes($this->connection) : 0;
	}

	public function ping()
	{
		return file_exists( $this->dbname );
	}

	protected function doClose()
	{
		$this->cached_error = false;
		return @sqlite_close( $this->connection );
	}

	protected function doQuery( $sql, $variables = false )
	{
		$this->cached_error = false;

		if ( !strcasecmp($sql, "BEGIN") ) {
			$sql = "BEGIN TRANSACTION";
		}

		$error = false;
		$result = @sqlite_query( $this->connection, $sql, SQLITE_BOTH, $error );

		if ( $error ) {
			$this->cached_error = array( 400, $error );
		}

		if ( is_resource($result) ) {
			return new KlimDatabaseResultSqlite( $this, $result );
		} else {
			return $result;
		}
	}

	public function convertDate( $date )
	{
		return KlimTime::getTimestamp( GMT_DB, $date );
	}

	public function strencode( $arg )
	{
		return sqlite_escape_string( $arg );
	}
}

