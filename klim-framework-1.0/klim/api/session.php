<?php
/**
 * Klasa bazowa dla implementacji obsługi sesji dla klientów API
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


abstract class KlimApiSession
{
	protected $context;
	protected $reason = false;          // powód ostatniego nieudanego logowania
	protected $wsdl = false;            // url do pliku WSDL, na którym operuje klient (false -> protokół http)
	protected $soapClient = false;
	protected $loginClass = false;      // klasa dostępu do danych potrzebnych do zalogowania
	protected $loginToken = false;      // token zwrócony przez metodę logującą
	protected $lastLogin = false;       // czas ostatniego zalogowania
	protected $lastRequest = false;     // czas ostatniego requesta
	protected $periodSinceLogin = 0;    // liczba sekund od ostatniego zalogowania do konieczności ponownego logowania
	protected $periodSinceRequest = 0;  // liczba sekund od ostatniego requesta do konieczności ponownego logowania
	protected $cacheAvailable = true;   // znacznik, czy możliwe jest wykorzystane informacji o logowaniu z cache
	protected $cacheWriteLogin = true;  // znacznik, czy w ogóle zapisywać do cache informację o zalogowaniu
	protected $cacheId = "api";

	public function __construct( $context )
	{
		$this->context = $context;

		if ( $this->wsdl ) {
			$this->soapClient = $this->context->initSoap( $this->wsdl );
		} else {
			$this->context->initHttp();
			$this->cacheAvailable = false;
			$this->cacheWriteLogin = false;
		}
	}

	public function getSoapClient()
	{
		return $this->soapClient;
	}

	public function getReason()
	{
		return $this->reason;
	}

	/**
	 * Metoda, której może użyć kod korzystający z API do otrzymania
	 * tokena zwracanego przez metodę logującą - ten token to zwykle
	 * wartość true, jednak w przypadku niektórych klientów może być
	 * nim np. jakiś klucz wymagany do podawania przy kolejnych
	 * requestach.
	 */
	public function getLoginToken()
	{
		return $this->loginToken;
	}

	/**
	 * Metoda powiadamiająca, że nastąpiło wylogowanie, błąd itp., wskutek
	 * czego konieczne jest ponowne zalogowanie.
	 */
	public function notifyLogout()
	{
		$this->loginToken = false;
		$this->lastLogin = false;
	}

	/**
	 * Metoda powiadamiająca, że klient wykonał kolejnego requesta.
	 */
	public function notifyRequest()
	{
		$this->lastRequest = KlimTime::getNow();
	}

	/**
	 * Metoda, której może użyć kod korzystający z API do wymuszenia
	 * zalogowania. Powinna też być ona używana przy każdym kolejnym
	 * requeście, który wymaga logowania - z poziomu kodu tego requesta.
	 *
	 * Sprawdza ona, czy klient jest nadal zalogowany (zgodnie ze
	 * zdefiniowanymi regułami) i jeśli nie, wykonuje ponowne logowanie.
	 */
	public function checkLogin()
	{
		if ( $this->isLogged() ) {
			return true;
		} else if ( !$this->loginClass ) {
			return false;
		}

		$credentials = $this->context->getCredentials( $this->loginClass );

		if ( $this->cacheAvailable || $this->cacheWriteLogin ) {
			$key = $this->getCacheKey( $credentials );
			$cache = KlimCache::getInstance( $this->cacheId );
		}

		if ( $this->cacheAvailable ) {
			$this->cacheAvailable = false;

			if ( $loginInfo = $cache->get($key) ) {
				$this->loginToken = $loginInfo[0];
				$this->lastLogin = $loginInfo[1];

				if ( $this->isLogged() ) {
					return true;
				}
			}
		}

		$token = $this->autoLogin( $credentials );
		$this->lastRequest = KlimTime::getNow();

		if ( $token === false ) {
			return false;
		}

		if ( $this->cacheWriteLogin ) {
			$loginInfo = array( $token, (int)$this->lastRequest );
			$cache->set( $key, $loginInfo, $this->getCachePeriod() );
		}

		$this->loginToken = $token;
		$this->lastLogin = $this->lastRequest;
		$this->reason = false;
		return true;
	}

	/**
	 * Metoda wykonująca właściwe sprawdzenie, czy zgodnie z regułami
	 * klient jest nadal zalogowany.
	 */
	protected function isLogged()
	{
		if ( $this->loginToken === false ) {
			return false;
		}

		if ( $this->periodSinceRequest ) {
			$now = KlimTime::getNow();
			$diff = (int)( $now - $this->lastRequest );

			if ( $diff + 10 > $this->periodSinceRequest ) {
				$this->notifyLogout();
				return false;
			}
		}

		if ( $this->periodSinceLogin ) {
			$now = KlimTime::getNow();
			$diff = (int)( $now - $this->lastLogin );

			if ( $diff + 30 > $this->periodSinceLogin ) {
				$this->notifyLogout();
				return false;
			}
		}

		return true;
	}

	/**
	 * Metoda zwracająca fragment klucza, po którym cache'ujemy dane
	 * o logowaniu.
	 */
	protected function getCacheKey( $credentials )
	{
		$cache = "api:";
		$cache .= get_class( $this );

		foreach ( $credentials as $key => $value ) {
			if ( stripos($key, "passw") === false ) {
				$cache .= ":$value";
			}
		}

		return $cache;
	}

	/**
	 * Metoda wyliczająca optymalny okres cache'owania danych o logowaniu.
	 */
	protected function getCachePeriod()
	{
		if ( $this->periodSinceLogin ) {
			return $this->periodSinceLogin;
		}

		if ( $this->periodSinceRequest ) {
			return 3600 * 2;  // 2 godziny, czysto umownie
		}

		return 86400;
	}

	/**
	 * Szkielet metody wykonującej automatyczne logowanie. Metoda musi
	 * zwracać wartość false, jeśli logowanie się nie powiodło, lub token
	 * otrzymany od właściwej metody logującej, jeśli się powiodło.
	 */
	protected function autoLogin( $credentials )
	{
		return false;
	}
}

