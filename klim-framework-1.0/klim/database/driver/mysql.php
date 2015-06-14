<?php
/**
 * Sterownik do bazy danych MySQL, korzystający z modułu php5-mysql
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
class KlimDatabaseDriverMysql extends KlimDatabaseDriver
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
				$parts = explode( ":", $entry );
				$host = $parts[0];
			} else {
				$host = $entry;
			}

			if ( $this->persistent ) {
				$this->connection = @mysql_pconnect( $host, $this->dbuser, $this->dbpass );
			} else {
				$this->connection = @mysql_connect( $host, $this->dbuser, $this->dbpass, true );
			}

			if ( !$this->connection ) {

				KlimLogger::error( "db", "cannot connect to database server $host", $this->lastError() );
				continue;

			} else if ( @mysql_select_db($this->dbname, $this->connection) === false ) {

				KlimLogger::error( "db", "cannot choose database $this->dbname on server $host", $this->lastError() );
				$this->doClose();
				$this->connection = false;
				continue;
			}

			$server_version = mysql_get_server_info( $this->connection );
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
		return $this->connection ? mysql_errno($this->connection) : mysql_errno();
	}

	public function lastError()
	{
		return $this->connection ? mysql_error($this->connection) : mysql_error();
	}

	public function nextSequenceValue( $seq_name )
	{
		return false;
	}

	public function insertId()
	{
		return $this->connection ? mysql_insert_id($this->connection) : 0;
	}

	public function affectedRows()
	{
		return $this->connection ? mysql_affected_rows($this->connection) : 0;
	}

	// Od wersji MySQL 5.0.13 ponawianie połączenia jest wyłączone.
	public function ping()
	{
		return $this->connection ? mysql_ping($this->connection) : false;
	}

	protected function doClose()
	{
		return @mysql_close( $this->connection );
	}

	protected function doQuery( $sql, $variables = false )
	{
		$result = mysql_query( $sql, $this->connection );

		if ( is_resource($result) ) {
			return new KlimDatabaseResultMysql( $this, $result );
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
		return $this->connection ? mysql_real_escape_string($arg, $this->connection) : mysql_real_escape_string($arg);
	}
}

