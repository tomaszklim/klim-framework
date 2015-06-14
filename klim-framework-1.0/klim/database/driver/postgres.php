<?php
/**
 * Sterownik do bazy danych PostgreSQL
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


// http://www.postgresql.org.pl/index.php?option=com_staticxt&staticfile=usenet-postgresql-faq.html&Itemid=34
class KlimDatabaseDriverPostgres extends KlimDatabaseDriver
{
	private $insert_id = 0;
	private $affected_rows = 0;
	private $cached_error = false;

	public function getEncoding()
	{
		if ( !$this->connection ) {
			return "Windows-1250";
		}

		$charset = pg_client_encoding( $this->connection );

		$trans = array (
			"SQL_ASCII" => "ISO-8859-1",
			"LATIN1"    => "ISO-8859-1",
			"LATIN2"    => "ISO-8859-2",
			"WIN1250"   => "Windows-1250",
			"UTF8"      => "UTF-8",
		);

		return isset($trans[$charset]) ? $trans[$charset] : "Windows-1250";
	}

	public function getVendor()
	{
		return "postgres";
	}

	// TODO: dodać obsługę: set datestyle='ISO'
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
				$port = 5432;
			}

			$string = "host='$host' port='$port' dbname='$this->dbname' user='$this->dbuser' password='$this->dbpass'";

			set_error_handler( array($this, "errorHandler") );

			if ( $this->persistent ) {
				$this->connection = pg_pconnect( $string );
			} else {
				$this->connection = pg_connect( $string, PGSQL_CONNECT_FORCE_NEW );
			}

			restore_error_handler();

			if ( $this->connection ) {
				pg_set_client_encoding( $this->connection, $this->charset );
				pg_set_error_verbosity( $this->connection, PGSQL_ERRORS_VERBOSE );
				return true;
			} else {
				KlimLogger::error( "db", "cannot connect to database server $host:$port", $this->lastError() );
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
		return ( strpos($this->error, "duplicate key violates unique constraint") !== false ? true : false );
	}

	public function lastErrno()
	{
		return strlen( $this->lastError() );
	}

	public function lastError()
	{
		return $this->cached_error ? $this->cached_error : preg_replace( "/\s+/", " ", pg_last_error($this->connection) );
	}

	public function nextSequenceValue( $seq_name )
	{
		if ( !$this->connection ) {
			return false;
		}

		$sql = "SELECT nextval('$seq_name')";
		$res = $this->query( $sql );
		$this->insert_id = (int)$res[0][0];
		return $this->insert_id;
	}

	public function insertId()
	{
		return $this->insert_id;
	}

	public function affectedRows()
	{
		return $this->affected_rows;
	}

	public function ping()
	{
		if ( !$this->connection ) {
			return false;
		}

		$result = pg_ping( $this->connection );

		return $result ? true : pg_connection_reset($this->connection);
	}

	protected function doClose()
	{
		$this->insert_id = 0;
		$this->affected_rows = 0;
		$this->cached_error = false;
		return @pg_close( $this->connection );
	}

	protected function doQuery( $sql, $variables = false )
	{
		$this->affected_rows = 0;
		$this->cached_error = false;

		$result = @pg_query( $this->connection, $sql );

		if ( $result === false ) {
			return false;
		}

		switch ( pg_result_status($result) )
		{
			case PGSQL_TUPLES_OK:
				return new KlimDatabaseResultPostgres( $this, $result );

			case PGSQL_COMMAND_OK:
				$this->affected_rows = pg_affected_rows( $result );
				pg_free_result( $result );
				return true;

			default:
				pg_free_result( $result );
				return false;
		}
	}

	public function convertDate( $date )
	{
		return KlimTime::getTimestamp( GMT_DB, $date );
	}

	public function strencode( $arg )
	{
		return pg_escape_string( $arg );
	}

	/**
	 * Metoda pomocnicza do łapania błędów. Sterownik natywny do tej bazy
	 * danych nie umożliwia przechwytywania informacji o błędzie w żaden
	 * inny sposób, niż przechwycenie Warninga wyświetlanego standardowo
	 * na wyjściowej stronie.
	 */
	private function errorHandler( $errno, $errstr )
	{
		$this->cached_error = preg_replace( "/\s+/", " ", $errstr );
		return true;  // ta metoda musi zawsze zwracać true
	}
}

