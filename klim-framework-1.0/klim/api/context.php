<?php
/**
 * Klasa do przechowywania kontekstu (sesji) API
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


class KlimApiContext
{
	public $http = false;
	public $db = false;
	public $lastUrl = false;
	private $soap = array();
	private $sessions = array();
	private $credentials = array();

	public function __construct( $db = false )
	{
		$this->db = $db ? $db : new KlimDatabase();
	}

	public function addCredentials( $class, $data )
	{
		$this->credentials[$class] = $data;
	}

	public function getCredentials( $class )
	{
		if ( empty($this->credentials[$class]) ) {
			throw new KlimApplicationException( "no login credentials provided for class $class" );
		}

		return $this->credentials[$class];
	}

	/**
	 * Handler http obsługuje tylko typowe requesty http(s), bez ficzerów
	 * typu autoryzacja http-auth, cache itp. - jeśli będzie to potrzebne,
	 * będzie można pomyśleć o przeróbkach, a najlepiej o osobnym handlerze.
	 *
	 * Każda instancja ApiContext ma własny kontener z cookiesami - nie jest
	 * możliwe odziedziczenie cookiesów po poprzedniej/innej instancji.
	 */
	public function initHttp()
	{
		if ( !$this->http ) {
			$pid = getmypid();
			$time = time();
			$rand = mt_rand( 1000000, 9999999 );

			$this->http = new KlimHttp();
			$this->http->setFollowLocation( 1 );
			$this->http->setReturnHeader( 0 );
			$this->http->setAgent( "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.2; SV1; .NET CLR 1.1.4322)" );
			$this->http->setTimeout( 20 );
			$this->http->setCookieJar( Bootstrap::getApplicationRoot() . "/cache/api_cookies/http_{$pid}_{$time}_{$rand}.txt" );
		}
	}

	public function initSoap( $url )
	{
		Bootstrap::addLibrary("nusoap-0.7.3-patched");
		require_once "nusoap.php";

		$md5 = md5( $url );
		if ( !isset($this->soap[$md5]) ) {

			$cache = new nusoap_wsdlcache( Bootstrap::getApplicationRoot() . "/cache/api_soap", 86400 );
			$obj = $cache->get( $url );

			if ( is_null($obj) ) {
				$obj = new wsdl( $url );
				$cache->put( $obj );
			}

			$client = new nusoap_client( $obj, true );
			$client->soap_defencoding = "UTF-8";
			$client->decode_utf8 = false;

			$this->soap[$md5] = $client;
		}

		return $this->soap[$md5];
	}

	public function attachSession( $class )
	{
		if ( !isset($this->sessions[$class]) ) {
			$this->sessions[$class] = new $class( $this );
		}

		return $this->sessions[$class];
	}
}

