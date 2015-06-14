<?php
/**
 * Sterownik do bazy danych MySQL, korzystający z modułu php5-mysqli
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
 * Kody błędów wiążących się z utratą połączenia z serwerem:
 *
 * 2013: Lost connection to MySQL server during query
 * 2006: MySQL server has gone away
 */
class KlimDatabaseDriverMysqli extends KlimDatabaseDriver
{
	public function getEncoding()
	{
		$trans = array (
			"latin1" => "ISO-8859-1",
			"latin2" => "ISO-8859-2",
			"utf8"   => "UTF-8",
		);

		return isset($trans[$this->charset]) ? $trans[$this->charset] : "UTF-8";
	}

	public function getVendor()
	{
		return "mysql";
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
				list( $host, $port ) = explode( ":", $entry );
			} else {
				$host = $entry;
				$port = 3306;
			}

			$this->connection = @mysqli_connect( $host, $this->dbuser, $this->dbpass, $this->dbname, $port );

			if ( !$this->connection ) {
				KlimLogger::error( "db", "cannot connect to database server $host:$port", $this->lastError() );
				continue;
			}

			$server_version = mysqli_get_server_info( $this->connection );
			$charset_support = version_compare( $server_version, "4.1.1", ">=" );

			if ( $charset_support ) {
				$this->doQuery( "SET NAMES $this->charset" );
				$this->doQuery( "SET sql_mode = ''" );  // wyłączenie strict mode
			}

			return true;
		}

		return false;
	}

	public function isDisconnect()
	{
		return ( $this->errno == 2013 || $this->errno == 2006 ? true : false );
	}

	// http://dev.mysql.com/doc/refman/5.0/en/error-messages-server.html
	public function isDuplicate()
	{
		return ( $this->errno == 1062 ? true : false );
	}

	public function isReadOnly()
	{
		return ( $this->errno == 1223 || ($this->errno == 1290 && strpos($this->error, "--read-only") !== false) ? true : false );
	}

	public function lastErrno()
	{
		return $this->connection ? mysqli_errno($this->connection) : mysqli_connect_errno();
	}

	public function lastError()
	{
		return $this->connection ? mysqli_error($this->connection) : mysqli_connect_error();
	}

	public function nextSequenceValue( $seq_name )
	{
		return false;
	}

	public function insertId()
	{
		return $this->connection ? mysqli_insert_id($this->connection) : 0;
	}

	public function affectedRows()
	{
		return $this->connection ? mysqli_affected_rows($this->connection) : 0;
	}

	// Od wersji MySQL 5.0.13 ponawianie połączenia jest wyłączone.
	public function ping()
	{
		return $this->connection ? mysqli_ping($this->connection) : false;
	}

	protected function doClose()
	{
		return @mysqli_close( $this->connection );
	}

	protected function doQuery( $sql, $variables = false )
	{
		$result = mysqli_query( $this->connection, $sql );

		if ( is_object($result) ) {
			return new KlimDatabaseResultMysqli( $this, $result );
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
		return $this->connection ? mysqli_real_escape_string($this->connection, $arg) : str_replace("'", "\'", $arg);
	}
}

