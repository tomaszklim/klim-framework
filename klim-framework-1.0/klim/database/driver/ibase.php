<?php
/**
 * Sterownik do baz danych InterBase/Firebird
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


class KlimDatabaseDriverIbase extends KlimDatabaseDriver
{
	private $insert_id = 0;

	public function getEncoding()
	{
		$trans = array (
			"ISO8859_1" => "ISO-8859-1",
			"ISO8859_2" => "ISO-8859-2",
			"WIN1250"   => "Windows-1250",
			"UTF8"      => "UTF-8",
		);

		return isset($trans[$this->charset]) ? $trans[$this->charset] : "UTF-8";
	}

	public function getVendor()
	{
		return "ibase";
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

			$cs = "$host:$this->dbname";

			if ( $this->persistent ) {
				$this->connection = @ibase_pconnect( $cs, $this->dbuser, $this->dbpass, $this->charset );
			} else {
				$this->connection = @ibase_connect( $cs, $this->dbuser, $this->dbpass, $this->charset );
			}

			if ( $this->connection ) {
				return true;
			} else {
				KlimLogger::error( "db", "cannot connect to database server $host", $this->lastError() );
			}
		}

		return false;
	}

	/**
	 * Sterownik natywny do baz InterBase/Firebird nie umożliwia
	 * wykrycia utraty połączenia z serwerem w sposób pewny.
	 */
	public function isDisconnect()
	{
		return true;
	}

	// http://www.firebirdsql.org/doc/contrib/fb_2_1_errorcodes.pdf
	public function isDuplicate()
	{
		return ( $this->errno == -803 || strpos($this->error, "Cannot insert duplicate key object") !== false ? true : false );
	}

	public function lastErrno()
	{
		return ibase_errcode();
	}

	public function lastError()
	{
		return ibase_errmsg();
	}

	/**
	 * Baza InterBase/Firebird zamiast sekwencji używa generatorów, które
	 * działają w podobny sposób, jednak wartość jest z nich pobierana
	 * procedurą składowaną GEN_ID().
	 *
	 * Przykładowe operacje na generatorach:
	 *
	 *   CREATE GENERATOR S_TABLE_NAME;
	 *   DELETE FROM RDB$GENERATORS WHERE RDB$GENERATOR_NAME = 'S_TABLE_NAME';
	 *   SET GENERATOR S_TABLE_NAME TO 100;
	 */
	public function nextSequenceValue( $seq_name )
	{
		if ( !$this->connection ) {
			return false;
		}

		$this->insert_id = ibase_gen_id( $seq_name, 1, $this->connection );
		return $this->insert_id;
	}

	public function insertId()
	{
		return $this->insert_id;
	}

	public function affectedRows()
	{
		return $this->connection ? ibase_affected_rows($this->connection) : 0;
	}

	public function ping()
	{
		return false;
	}

	protected function doClose()
	{
		$this->insert_id = 0;
		return @ibase_close( $this->connection );
	}

	protected function doQuery( $sql, $variables = false )
	{
		if ( !strcasecmp($sql, "BEGIN") ) {
			return ibase_trans( IBASE_DEFAULT, $this->connection );
		} else if ( !strcasecmp($sql, "COMMIT") ) {
			return ibase_commit( $this->connection );
		} else if ( !strcasecmp($sql, "ROLLBACK") ) {
			return ibase_rollback( $this->connection );
		}

		$result = @ibase_query( $this->connection, $sql );

		if ( is_resource($result) ) {
			return $this->getResult( $result );
		} else {
			return $result;
		}
	}

	protected function getResult( $result )
	{
		$rows = array();
		$fields = array();
		$nfields = ibase_num_fields( $result );

		for ( $i = 0; $i < $nfields; $i++ ) {
			$info = ibase_field_info( $result, $i );
			$fields[$i] = strtolower( $info["name"] );
		}

		while ( true ) {
			$row = ibase_fetch_row( $result, IBASE_TEXT );
			if ( !$row ) break;

			$ret = array();
			foreach ( $row as $key => $value ) {
				$lc = $fields[$key];
				$ret[$key] = $value;
				if ( !isset($ret[$lc]) ) {
					$ret[$lc] = $value;
				}
			}

			$rows[] = $ret;
		}

		ibase_free_result( $result );
		return new KlimDatabaseResultArray( false, $rows );
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

