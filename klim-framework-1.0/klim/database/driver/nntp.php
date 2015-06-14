<?php
/**
 * Sterownik do serwerów NNTP
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
 * @author Terence Yim <chtyim@gmail.com>
 * @author Tomasz Klim <framework@tomaszklim.com>
 * @license http://www.gnu.org/copyleft/gpl.html
 */


/**
 * Ta klasa implementuje obsługę połączenia z serwerem NNTP, zamiast z bazą
 * danych. Jest ona wykorzystywana przez adaptery do obsługi protokołu NNTP
 * i zarazem jest demonstracją możliwości rozbudowy sterownika bazodanowego
 * o źródła danych inne niż baza danych.
 *
 * Metoda query() w tym sterowniku nie zwraca wyniku w postaci obiektu klasy
 * pochodnej KlimDatabaseResult, zamiast tego udostępnia metody getLine() i
 * getBody().
 *
 * Sterownik nie obsługuje transakcji, co upraszcza jego konstrukcję.
 *
 * Protokół NNTP umożliwia jednak ustawianie aktywnej grupy dla połączenia,
 * do której można (i trzeba) się następnie odwoływać poprzez sam numer
 * artykułu. Z tego względu sterownik udostępnia metody getContext() i
 * setContext(), którymi aplikacja powinna ustawić aktywną grupę.
 *
 * Dokumentacja protokołu NNTP:
 *
 * http://tools.ietf.org/html/rfc977 - Network News Transfer Protocol
 * http://tools.ietf.org/html/rfc2980 - Common NNTP Extensions
 * http://tools.ietf.org/html/rfc3977 - Network News Transfer Protocol 2
 * http://tools.ietf.org/html/rfc6048 - Additions to LIST Command
 */
class KlimDatabaseDriverNntp
{
	private $connection = false;
	private $context = array();
	private $errno = 0;
	private $error = false;
	private $dbhost;
	private $dbuser;
	private $dbpass;

	const SERVER_READY = 200;
	const SERVER_READY_NO_POST = 201;
	const GROUP_SELECTED = 211;
	const AUTH_ACCEPT = 281;
	const MORE_AUTH_INFO = 381;
	const AUTH_REQUIRED = 480;
	const AUTH_REJECTED = 482;

	public function __construct( $host, $user, $pass, $name, $charset, $persistent )
	{
		$this->dbhost = $host;
		$this->dbuser = $user;
		$this->dbpass = $pass;
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
		return "nntp";
	}

	public function connect()
	{
		if ( $this->connection ) {
			return true;
		}

		if ( strpos($this->dbhost, ":") !== false ) {
			list( $host, $port ) = explode( ":", $this->dbhost );
		} else {
			$host = $this->dbhost;
			$port = 119;
		}

		$this->errno = 0;
		$this->error = false;
		$this->connection = @fsockopen( $host, $port, $this->errno, $this->error );

		if ( !$this->connection ) {
			KlimLogger::error( "nntp", "cannot connect to nntp server $host:$port [$this->errno]", $this->error );
			return false;
		}

		$response = $this->parse( $this->getLine() );

		if ( $response["status"] == self::SERVER_READY || $response["status"] == self::SERVER_READY_NO_POST ) {
			$this->send( "mode reader" );
			$this->getLine();

			if ( empty($this->dbuser) ) {
				return true;
			}

			$response = $this->query( "authinfo user $this->dbuser" );

			if ( $response["status"] == self::MORE_AUTH_INFO ) {
				$response = $this->query( "authinfo pass $this->dbpass" );

				if ( $response["status"] == self::AUTH_ACCEPT ) {
					return true;
				}
			}
		}

		$this->doClose();

		$this->errno = $response["status"];
		$this->error = $response["message"];

		KlimLogger::error( "nntp", "nntp server error [$this->errno]", $this->error );
		return false;
	}

	public function isReadOnly()
	{
		return true;
	}

	public function isOpen()
	{
		return $this->connection ? true : false;
	}

	public function lastErrno()
	{
		return $this->errno;
	}

	public function lastError()
	{
		return $this->error;
	}

	public function close()
	{
		if ( $this->connection ) {
			fputs( $this->connection, "quit\r\n" );
			$this->doClose();
		}
		return false;
	}

	private function doClose()
	{
		fclose( $this->connection );
		$this->connection = false;
		$this->context = array();
		$this->errno = 0;
		$this->error = false;
	}

	public function addQuotes( $arg )
	{
		return $arg;
	}

	public function query( $command )
	{
		$this->errno = 0;
		$this->error = false;
		$this->send( $command );
		return $this->parse( $this->getLine() );
	}

	public function begin( $db, $instance )
	{
		return false;
	}

	public function commit()
	{
		return false;
	}

	public function rollback()
	{
		return false;
	}

	public function isActiveTransaction()
	{
		return false;
	}

	public function isBrokenTransaction()
	{
		return false;
	}

	public function getTransactionDatabase()
	{
		return false;
	}

	public function getTransactionInstance()
	{
		return 0;
	}

	/**
	 *
	 * Poniższe metody są specyficzne dla protokołu NNTP.
	 *
	 */

	/**
	 * Ustawienie grupy, z jaką ma być skojarzone obecne połączenie.
	 * Wszystkie polecenia, które nie zawierają nazwy grupy, będą
	 * wykonywane przez serwer w kontekście ustawionej tutaj grupy.
	 */
	public function setContext( $context )
	{
		if ( !isset($this->context["group"]) || $this->context["group"] != $context )
		{
			$response = $this->query( "group $context" );
			$status = $response["status"];

			if ( $status != self::GROUP_SELECTED ) {
				$this->doClose();
				$this->errno = $status;
				$this->error = $response["message"];
				return false;
			}

			$result = preg_split( "/\s/", $response["message"] );

			$this->context["group"] = $context;
			$this->context["min"] = $result[1];
			$this->context["max"] = $result[2];
		}

		return true;
	}

	/**
	 * Pobranie nazwy grupy, z jaką jest skojarzone połączenie.
	 */
	public function getContext()
	{
		return isset($this->context["group"]) ? $this->context["group"] : false;
	}

	/**
	 * Pobranie najmniejszego identyfikatora artykułu na grupie.
	 */
	public function getMinId()
	{
		return isset($this->context["min"]) ? $this->context["min"] : false;
	}

	/**
	 * Pobranie największego identyfikatora artykułu na grupie.
	 *
	 * Uwaga: jeśli metoda jest używana przez mechanizm działający w tle,
	 * o długim okresie życia (od kilku sekund wzwyż), zachodzi ryzyko, że
	 * na grupie pojawią się nowe artykuły, wskutek czego wartość zwracana
	 * przez tą metodę przestanie być aktualna.
	 */
	public function getMaxId()
	{
		return isset($this->context["max"]) ? $this->context["max"] : false;
	}

	/**
	 * Wysłanie polecenia do serwera.
	 */
	private function send( $request )
	{
		fputs( $this->connection, $request."\r\n" );
		fflush( $this->connection );
		// LogUtils::save( "nntp-debug", "<- $request\n" );
	}

	/**
	 * Pobranie pojedynczej linii z serwera.
	 */
	public function getLine()
	{
		$ret = fgets( $this->connection, 4096 );
		// LogUtils::save( "nntp-debug", "-> $ret" );
		return $ret;
	}

	/**
	 * Pobranie odpowiedzi multi-line z serwera.
	 */
	public function getBody()
	{
		$result = "";
		$buf = $this->getLine();
		while ( !preg_match("/^\.\s*$/", $buf) ) {
			$result .= $buf;
			$buf = $this->getLine();
		}

		return $result;
	}

	/**
	 * Podział linii z odpowiedzią od serwera na kod odpowiedzi
	 * i jej opis z parametrami lub treścią błędu.
	 */
	private function parse( $response )
	{
		$status = substr( $response, 0, 3 );
		$message = str_replace( "\r\n", "", substr($response, 4) );
		return array( "status" => intval($status), "message" => $message );
	}
}

