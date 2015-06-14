<?php
/**
 * Sterownik do serwerów IMAP i POP3
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
 * Ta klasa implementuje obsługę połączenia z serwerami IMAP i POP3, zamiast
 * z bazą danych. Jest ona wykorzystywana przez adapter do obsługi protokołu
 * IMAP i zarazem jest demonstracją możliwości rozbudowy sterownika
 * bazodanowego o źródła danych inne niż baza danych.
 *
 * Klasa zawiera również metody modyfikujące zawartość skrzynki na serwerze.
 *
 * Dokumentacja protokołu IMAP:
 *
 * http://tools.ietf.org/html/rfc3501
 */
class KlimDatabaseDriverImap
{
	private $connection = false;
	private $trxactive = false;
	private $trxdatabase = false;
	private $trxinstance = 0;
	private $trxmove;
	private $trxdelete;
	private $error = false;
	private $dbhost;
	private $dbuser;
	private $dbpass;
	private $dbname;

	public function __construct( $host, $user, $pass, $name, $charset, $persistent )
	{
		$this->dbhost = "{".$host."}";
		$this->dbuser = $user;
		$this->dbpass = $pass;
		$this->dbname = $name;
	}

	public function __destruct()
	{
		$this->close();
	}

	public function getEncoding()
	{
		return "UTF-8";
	}

	public function getVendor()
	{
		return "imap";
	}

	/**
	 * W przypadku, gdy funkcja imap_open() próbuje nawiązać połączenie
	 * szyfrowane (ssl), certyfikat jest nieprawidłowy lub self-signed,
	 * a w definicji hosta nie podano "/novalidate-cert", to połączenia
	 * nie uda się nawiązać, a dodatkowo funkcja imap_errors() nie zwróci
	 * żadnego błędu.
	 *
	 * TODO: Rozważyć użycie funkcji set_error_handler() i odpowiedniej
	 * metody przechwytującej, podobnie jak w sterowniku dla Postgresa.
	 */
	public function connect()
	{
		if ( $this->connection ) {
			return true;
		}

		$cstr = $this->dbhost . mb_convert_encoding( $this->dbname, "UTF7-IMAP", "UTF8" );

		$this->error = false;
		$this->connection = @imap_open( $cstr, $this->dbuser, $this->dbpass );

		if ( !$this->connection ) {
			$host = trim( $this->dbhost, "{}" );
			KlimLogger::error( "imap", "cannot connect to imap server $host", $this->lastError() );
			return false;
		}

		imap_errors();  // flush "Mailbox is empty" error
		return true;
	}

	public function isDisconnect()
	{
		return imap_ping($this->connection) ? false : true;
	}

	public function isDuplicate()
	{
		return false;
	}

	public function isFetchError()
	{
		return false;
	}

	public function isReadOnly()
	{
		return $this->trxactive ? false : true;
	}

	public function isOpen()
	{
		return $this->connection ? true : false;
	}

	public function lastErrno()
	{
		return strlen( $this->lastError() );
	}

	public function lastError()
	{
		if ( !$this->error ) {
			$errors = imap_errors();
			if ( !empty($errors) ) {
				$this->error = implode( "; ", $errors );
			}
		}
		return $this->error;
	}

	public function close()
	{
		if ( $this->connection ) {
			$this->doClose();
		}
		return false;
	}

	private function doClose()
	{
		imap_close( $this->connection );
		$this->connection = false;
		$this->error = false;
		$this->trxactive = false;
		$this->trxdatabase = false;
		$this->trxinstance = 0;
	}

	public function addQuotes( $arg )
	{
		return '"' . $arg . '"';
	}

	public function query( $query )
	{
		$this->error = false;
		$result = imap_search( $this->connection, $query );

		if ( is_array($result) ) {
			return new KlimDatabaseResultImap( $this, $result );
		} else if ( !$this->isDisconnect() ) {
			return new KlimDatabaseResultImap( $this, array() );
		} else {
			return $result;
		}
	}

	public function begin( $db, $instance )
	{
		if ( !$this->connection ) {
			throw new KlimApplicationException( "not connected to server" );
		}

		if ( $this->trxactive ) {
			throw new KlimApplicationException( "transaction already started for connection $db" );
		}

		$this->trxactive = true;
		$this->trxdatabase = $db;
		$this->trxinstance = $instance;
		$this->trxmove = array();
		$this->trxdelete = array();
		return true;
	}

	public function commit()
	{
		if ( !$this->trxactive ) {
			throw new KlimApplicationException( "no transaction in progress" );
		}

		foreach ( $this->trxmove as $folder => $ids ) {
			$range = implode( ",", $ids );
			$folder = mb_convert_encoding( $folder, "UTF7-IMAP", "UTF8" );
			imap_mail_move( $this->connection, $range, $folder );
		}

		if ( !empty($this->trxdelete) ) {
			$range = implode( ",", $this->trxdelete );
			imap_delete( $this->connection, $range );
		}

		if ( !empty($this->trxmove) || !empty($this->trxdelete) ) {
			imap_expunge( $this->connection );
		}

		$this->trxactive = false;
		$this->trxdatabase = false;
		$this->trxinstance = 0;
		$this->trxmove = false;
		$this->trxdelete = false;
		return true;
	}

	public function rollback()
	{
		if ( !$this->trxactive ) {
			throw new KlimApplicationException( "no transaction in progress" );
		}

		$this->trxactive = false;
		$this->trxdatabase = false;
		$this->trxinstance = 0;
		$this->trxmove = false;
		$this->trxdelete = false;
		return true;
	}

	public function isActiveTransaction()
	{
		return $this->trxactive;
	}

	public function isBrokenTransaction()
	{
		return false;
	}

	public function getTransactionDatabase()
	{
		return $this->trxdatabase;
	}

	public function getTransactionInstance()
	{
		return $this->trxinstance;
	}

	/**
	 *
	 * Poniższe metody są specyficzne dla protokołu IMAP.
	 *
	 */

	public function getConnection()
	{
		return $this->connection;
	}

	public function getFolders()
	{
		$list = imap_list( $this->connection, $this->dbhost, "*" );
		$out = array();

		foreach ( $list as $folder ) {
			$tmp = str_replace( $this->dbhost, "", $folder );
			$out[] = mb_convert_encoding( $tmp, "UTF8", "UTF7-IMAP" );
		}

		return $out;
	}

	public function insertMessage( $raw )
	{
		$cstr = $this->dbhost . $this->dbname;
		return imap_append( $this->connection, $cstr, $raw );
	}

	public function queueMove( $id, $folder )
	{
		if ( !$this->trxactive ) {
			throw new KlimApplicationException( "no transaction in progress" );
		}

		$this->trxmove[$folder][] = $id;
	}

	public function queueDelete( $id )
	{
		if ( !$this->trxactive ) {
			throw new KlimApplicationException( "no transaction in progress" );
		}

		$this->trxdelete[] = $id;
	}
}

