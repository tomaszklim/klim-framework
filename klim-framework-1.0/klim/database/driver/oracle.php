<?php
/**
 * Sterownik do bazy danych Oracle
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
 * Sterownik do bazy danych Oracle, korzystający z modułu php5-oci8.
 *
 * Kody błędów OCI wiążących się z utratą połączenia z serwerem:
 *
 * ORA-00020: maximum number of processes (string) exceeded
 * ORA-00028: your session has been killed
 * ORA-01033: ORACLE initialization or shutdown in progress
 * ORA-01034: ORACLE not available
 * ORA-01089: immediate shutdown in progress - no operations are permitted
 * ORA-01092: ORACLE instance terminated. Disconnection forced
 * ORA-03113: end-of-file on communication channel
 * ORA-03114: not connected to Oracle
 * ORA-03135: connection lost contact
 * ORA-07445: exception encountered: core dump [string] [string] [string] [string] [string] [string]
 * ORA-12170: TNS: Connect timeout occurred.
 * ORA-12500: TNS:listener failed to start a dedicated server process
 * ORA-12535: TNS: Operation timed out
 * ORA-12537: TNS: Operation timed out
 * ORA-12547: TNS:lost contact
 * ORA-12560: TNS: Protocol adapter error
 * ORA-12571: TNS:packet writer failure
 * ORA-12606: TNS: Application timeout occurred
 *
 * ORA-00600: internal error code, arguments: [string], [string], [string], [string], [string], [string], [string], [string]
 * ORA-01012: not logged on
 * ORA-12154: TNS:could not resolve the connect identifier specified
 * ORA-12514: TNS:listener does not currently know of service requested in connect descriptor
 * ORA-12518: TNS:listener could not hand off client connection
 * ORA-12520: TNS:listener could not find available handler for requested type of server
 * ORA-12521: TNS:listener does not currently know of instance requested in connect descriptor
 * ORA-12528: TNS:listener: all appropriate instances are blocking new connections
 * ORA-12541: TNS:no listener
 * ORA-21500: internal error code, arguments: [string], [string], [string], [string], [string], [string], [string], [string]
 * ORA-24324: service handle not initialized
 * ORA-25408: can not safely replay call
 */
class KlimDatabaseDriverOracle extends KlimDatabaseDriver
{
	private $insert_id = 0;
	private $affected_rows = 0;
	private $stmt_error = false;
	private $cached_error = false;
	private $env_error = false;

	/**
	 * Ten sterownik obsługuje na razie tylko UTF-8, przy czym ustawienie
	 * "charset" w konfiguracji segmentu musi być puste. Docelowo będzie
	 * do niego dodana obsługa innych metod kodowania znaków.
	 */
	public function getEncoding()
	{
		return "UTF-8";
	}

	public function getVendor()
	{
		return "oracle";
	}

	public function connect()
	{
		if ( $this->connection ) {
			return true;
		}

		// http://www.oracle.com/technology/tech/globalization/htdocs/nls_lang%20faq.htm
		if ( empty($this->charset) ) {
			putenv( "NLS_LANG=AMERICAN_AMERICA.AL32UTF8" );
		} else {
			putenv( "NLS_LANG=$this->charset" );
		}

		// putenv( "NLS_NUMERIC_CHARACTERS=. " );
		// putenv( "NLS_DATE_FORMAT=YYYY-MM-DD HH24:MI:SS" );
		// putenv( "NLS_COMP=ANSI" );
		// putenv( "NLS_SORT=BINARY_CI" );

		$this->env_error = false;
		set_error_handler( array($this, "errorHandler") );

		if ( $this->persistent ) {
			$this->connection = oci_pconnect( $this->dbuser, $this->dbpass, $this->dbname );
		} else {
			$this->connection = oci_new_connect( $this->dbuser, $this->dbpass, $this->dbname );
		}

		restore_error_handler();

		if ( !$this->connection ) {
			$error = $this->lastError();
			if ( !$error )
				$error = $this->env_error;
			KlimLogger::error( "db", "cannot connect to database $this->dbname", $error );
			return false;
		} else {
			return true;
		}
	}

	public function isDisconnect()
	{
		$codes = array (
			/* 1 */ 20, 28, 1033, 1034, 1089, 1092, 3113, 3114, 3135, 7445, 12170, 12500, 12535, 12537, 12547, 12560, 12571, 12606,
			/* 2 */ 600, 1012, 12154, 12514, 12518, 12520, 12521, 12528, 12541, 21500, 24324, 25408
		);

		if ( in_array($this->errno, $codes) ) {
			KlimLogger::info( "db", "oracle disconnect $this->errno" );
			return true;
		} else {
			return false;
		}
	}

	public function isDuplicate()
	{
		return ( $this->errno == 1 ? true : false );
	}

	private function checkError()
	{
		if ( !$this->cached_error ) {
			if ( $this->stmt_error ) {
				$this->cached_error = oci_error( $this->stmt_error );
			} else if ( $this->connection ) {
				$this->cached_error = oci_error( $this->connection );
			} else {
				$this->cached_error = oci_error();
			}
		}
	}

	public function lastErrno()
	{
		$this->checkError();
		return $this->cached_error ? $this->cached_error["code"] : 0;
	}

	public function lastError()
	{
		$this->checkError();
		return $this->cached_error ? $this->cached_error["message"] : false;
	}

	public function nextSequenceValue( $seq_name )
	{
		if ( !$this->connection ) {
			return false;
		}

		$sql = "SELECT $seq_name.nextval FROM dual";
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
		$ping = false;

		if ( $this->connection && !$this->trxbroken ) {
			$sql = "SELECT 1 FROM dual";
			if ( $this->doQuery($sql) ) {
				$ping = true;
			}
		}

		return $ping;
	}

	protected function doClose()
	{
		$this->insert_id = 0;
		$this->affected_rows = 0;
		$this->stmt_error = false;
		$this->cached_error = false;
		return @oci_close( $this->connection );
	}

	// TODO: dodać korekcję zbyt długich nazw zmiennych (ponad 30 znaków)
	protected function doQuery( $sql, $variables = false )
	{
		$this->affected_rows = 0;
		$this->stmt_error = false;
		$this->cached_error = false;

		if ( !strcasecmp($sql, "BEGIN") ) {
			return true;
		} else if ( !strcasecmp($sql, "COMMIT") ) {
			return oci_commit( $this->connection );
		} else if ( !strcasecmp($sql, "ROLLBACK") ) {
			return oci_rollback( $this->connection );
		}

		if ( preg_match("/^\s*SELECT/i", $sql) && !preg_match("/\sFROM\s/i", $sql) ) {
			$sql .= " FROM dual";
		}

		// zapobiega zakleszczeniom transakcji
		if ( preg_match("/FOR UPDATE$/i", $sql) ) {
			$sql .= " WAIT 10";
		}

		if ( $variables && is_array($variables) ) {
			foreach ( $variables as $variable => $value ) {
				if ( strpos($variable, ":dbARR_") !== false && preg_match("/^:dbARR_(STR|LOB|DATE|FLOAT\d*|INT)_(.*)$/", $variable, $matches) ) {

					$filtered = array();
					$cnt = 0;
					foreach ( $value as $subvalue ) {
						$subname = ":db" . $matches[1] . "_" . $matches[2] . "__" . $cnt++;
						$filtered[] = $subname;
						$variables[$subname] = $subvalue;
					}
					$target = implode( ",", $filtered );

					$sql = str_replace( $variable, $target, $sql );
					unset( $variables[$variable] );
				}
			}
		}

		// TODO: dodać korekcję zbyt długich nazw zmiennych (ponad 30 znaków)
		$stmt = oci_parse( $this->connection, $sql );

		if ( $stmt === false ) {
			return false;
		}

		if ( $variables && is_array($variables) ) {
			$escape = new KlimDatabaseQueryEscape( $this );
			foreach ( $variables as $variable => $value ) {

				if ( strpos($variable, ":dbINT_") !== false ) {
					$type = "int";
				} else if ( strpos($variable, ":dbFLOAT") !== false ) {
					$type = "float";
				} else if ( strpos($variable, ":dbDATE_") !== false ) {
					$type = "date";
				} else {
					$type = "char";
				}

				if ( strpos($variable, ":dbLOB_") !== false && strlen($value) > 65535 ) {
					$value = substr( $value, 0, 65535 );
				}

				if ( $value !== "null" ) {
					$target = $escape->parse( $variable, $value, $type, 0 );
					oci_bind_by_name( $stmt, $variable, $target, -1 );
				} else {
					$null = null;
					oci_bind_by_name( $stmt, $variable, $null, -1 );
				}
			}
		}

		oci_set_prefetch( $stmt, 50 );

		$flags = $this->trxactive ? OCI_DEFAULT : OCI_COMMIT_ON_SUCCESS;

		if ( @oci_execute($stmt, $flags) == false ) {
			$this->stmt_error = $stmt;
			return false;
		}

		if ( oci_statement_type($stmt) == "SELECT" ) {
			return $this->getResult( $stmt );
		} else {
			$this->affected_rows = oci_num_rows( $stmt );
			return true;
		}
	}

	protected function getResult( $stmt )
	{
		$rows = false;
		$rows2 = array();
		$fields = array();
		$nfields = oci_num_fields( $stmt );

		for ( $i = 1; $i <= $nfields; $i++ ) {
			$fields[$i] = strtolower( oci_field_name($stmt, $i) );
		}

		$nrows = oci_fetch_all( $stmt, $rows, 0, -1, OCI_FETCHSTATEMENT_BY_ROW | OCI_NUM );

		if ( $nrows === false ) {
			$error = oci_error( $stmt );
			$this->error = $error["message"];
			$this->errno = $error["code"];
			$this->fetch_error = true;
			return false;
		}

		foreach ( $rows as $num => $row ) {
			$ret = array();
			foreach ( $row as $key => $value ) {
				$lc = $fields[$key + 1];
				$ret[$key] = $value;
				if ( !isset($ret[$lc]) ) {
					$ret[$lc] = $value;
				}
			}
			$rows2[$num] = $ret;
		}

		oci_free_statement( $stmt );
		return new KlimDatabaseResultArray( false, $rows2 );
	}

	public function convertDate( $date )
	{
		return KlimTime::getTimestamp( GMT_ORACLE, $date );
	}

	public function strencode( $arg )
	{
		return str_replace( "'", "''", $arg );
	}

	/**
	 * Metoda pomocnicza do łapania błędów. Sterownik natywny do tej bazy
	 * danych nie umożliwia przechwytywania niektórych typów błędów, np.
	 * dotyczących zmiennych środowiskowych po stronie klienta.
	 */
	private function errorHandler( $errno, $errstr )
	{
		$this->env_error = preg_replace( "/\s+/", " ", $errstr );
		return true;  // ta metoda musi zawsze zwracać true
	}
}

