<?php
/**
 * Generyczny sterownik ODBC
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


abstract class KlimDatabaseDriverOdbc extends KlimDatabaseDriver
{
	protected $affected_rows = 0;

	/**
	 * Ten sterownik obsługuje tylko UTF-8, ustawienie "charset"
	 * z konfiguracji segmentu jest ignorowane, a baza danych musi
	 * być skonfigurowana do obsługi UTF-8.
	 */
	public function getEncoding()
	{
		return "UTF-8";
	}

	public function getVendor()
	{
		return "unknown";
	}

	public function connect()
	{
		if ( $this->connection ) {
			return true;
		}

		if ( strpos($this->dbhost, ";") !== false ) {
			$entries = explode( ";", $this->dbhost );
		} else {
			$entries = array( $this->dbhost );
		}

		foreach ( $entries as $entry ) {

			if ( strpos($entry, ":") !== false ) {
				$parts = explode( ":", $entry );
				$dsn = $parts[0];
			} else {
				$dsn = $entry;
			}

			if ( $this->persistent ) {
				$this->connection = @odbc_pconnect( $dsn, $this->dbuser, $this->dbpass );
			} else {
				$this->connection = @odbc_connect( $dsn, $this->dbuser, $this->dbpass );
			}

			if ( $this->connection ) {
				return true;
			} else {
				KlimLogger::error( "db", "cannot connect to odbc database dsn $dsn", $this->lastError() );
			}
		}

		return false;
	}

	/**
	 * Większość implementacji mechanizmu ODBC nie umożliwia
	 * wykrycia utraty połączenia z serwerem w sposób pewny.
	 */
	public function isDisconnect()
	{
		return true;
	}

	public function isDuplicate()
	{
		return false;
	}

	public function lastErrno()
	{
		return $this->connection ? odbc_error($this->connection) : odbc_error();
	}

	public function lastError()
	{
		return $this->connection ? odbc_errormsg($this->connection) : odbc_errormsg();
	}

	public function nextSequenceValue( $seq_name )
	{
		return false;
	}

	public function insertId()
	{
		KlimLogger::error( "db", "insertId method called in plain odbc driver, 0 returned" );
		return 0;
	}

	public function affectedRows()
	{
		return $this->affected_rows;
	}

	public function ping()
	{
		return false;
	}

	protected function doClose()
	{
		$this->affected_rows = 0;
		return @odbc_close( $this->connection );
	}

	protected function doQuery( $sql, $variables = false )
	{
		$this->affected_rows = 0;

		if ( !strcasecmp( $sql, "BEGIN" ) ) {

			return odbc_autocommit( $this->connection, false );

		} else if ( !strcasecmp( $sql, "COMMIT" ) ) {

			$ret = odbc_commit( $this->connection );
			odbc_autocommit( $this->connection, true );
			return $ret;

		} else if ( !strcasecmp( $sql, "ROLLBACK" ) ) {

			$ret = odbc_rollback( $this->connection );
			odbc_autocommit( $this->connection, true );
			return $ret;
		}

		$result = @odbc_exec( $this->connection, $sql );

		if ( !is_resource($result) ) {
			return $result;
		}

		if ( preg_match("/^\s*(SELECT|DESCRIBE|SHOW)/i", $sql) ) {
			return new KlimDatabaseResultOdbc( $this, $result );
		} else {
			$this->affected_rows = odbc_num_rows( $result );
			return $result;
		}
	}

	public function convertDate( $date )
	{
		return KlimTime::getTimestamp( GMT_DB, $date );
	}

	public function strencode( $arg )
	{
		return str_replace( "'", "\'", $arg );
	}
}

