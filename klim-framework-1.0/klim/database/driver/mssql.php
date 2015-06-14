<?php
/**
 * Sterownik do bazy danych Microsoft SQL Server
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


class KlimDatabaseDriverMssql extends KlimDatabaseDriver
{
	/**
	 * Ten sterownik obsługuje tylko Windows-1250, ustawienie "charset"
	 * z konfiguracji segmentu jest ignorowane, a baza danych musi być
	 * skonfigurowana do obsługi Windows-1250.
	 */
	public function getEncoding()
	{
		return "Windows-1250";
	}

	public function getVendor()
	{
		return "mssql";
	}

	// http://support.microsoft.com/default.aspx?scid=kb;en-us;325022#10
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
				$host = $parts[0];
			} else {
				$host = $entry;
			}

			if ( $this->persistent ) {
				$this->connection = @mssql_pconnect( $host, $this->dbuser, $this->dbpass );
			} else {
				$this->connection = @mssql_connect( $host, $this->dbuser, $this->dbpass, true );
			}

			if ( !$this->connection ) {

				KlimLogger::error( "db", "cannot connect to database server $host", $this->lastError() );
				continue;

			} else if ( @mssql_select_db($this->dbname, $this->connection) === false ) {

				KlimLogger::error( "db", "cannot choose database $this->dbname on server $host", $this->lastError() );
				$this->doClose();
				$this->connection = false;
				continue;

			} else {
				return true;
			}
		}

		return false;
	}

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
		return strlen( mssql_get_last_message() );
	}

	public function lastError()
	{
		$error = mssql_get_last_message();
		return empty($error) ? false : $error;
	}

	// http://bytes.com/forum/thread503110.html
	public function nextSequenceValue( $seq_name )
	{
		return false;
	}

	public function insertId()
	{
		if ( !$this->connection ) {
			return 0;
		}

		$sql = "SELECT @@identity";
		$res = $this->query( $sql );
		return (int)$res[0][0];
	}

	public function affectedRows()
	{
		if ( !$this->connection ) {
			return 0;
		}

		if ( function_exists("mssql_rows_affected") ) {
			return mssql_rows_affected( $this->connection );
		} else {
			$sql = "SELECT @@rowcount";
			$res = $this->query( $sql );
			return (int)$res[0][0];
		}
	}

	public function ping()
	{
		return false;
	}

	protected function doClose()
	{
		return @mssql_close( $this->connection );
	}

	protected function doQuery( $sql, $variables = false )
	{
		if ( !strcasecmp($sql, "BEGIN") ) {
			$sql = "BEGIN TRANSACTION";
		}

		$result = @mssql_query( $sql, $this->connection );

		if ( is_resource($result) ) {
			return new KlimDatabaseResultMssql( $this, $result );
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
		return str_replace( "'", "''", $arg );
	}
}

