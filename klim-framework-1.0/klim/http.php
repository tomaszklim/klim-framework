<?php
/**
 * Klient http/https oparty na bibliotece cURL
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
 * Niniejsza klasa udostępnia funkcjonalność klienta http[s]
 * w oparciu o bibliotekę cURL. Będąc wrapperem, dostarcza
 * kilka nowych funkcjonalności:
 *
 * - transparentny cache plikowy
 * - dekompresję w locie skompresowanych plików
 * - ograniczanie zużycia łącza
 * - zarządzanie nagłówkiem Referer
 * - statystyki ruchowe
 * - transmisję raw post body
 * - budowanie zagnieżdżonych konstrukcji get/post
 */
class KlimHttp
{
	protected $connection;              /** internal connection resource */
	protected $referer = false;         /** current referer url */
	protected $cache = false;           /** KlimCache class instance, if enabled */
	protected $period = 0;              /** caching period, if cache enabled */
	protected $totalTime = 0;           /** total network activity time */
	protected $totalSize = 0;           /** total size of retrieved data */
	protected $lastTime = 0;            /** start time of last request */
	protected $delay = 0;               /** delay between requests */
	protected $returnheader = false;    /** return http headers with data flag */
	protected $position = 0;            /** raw post data transmission pointer */
	protected $raw_data = false;        /** raw post data (string or file handler) */
	protected $raw_length = 0;          /** raw post data total length */
	protected $followLocation = false;  /** status podążania za przekierowaniami http */
	protected $newLocation = false;     /** nowy url, jeśli podążanie jest wyłączone, a wykryto nagłówek Location */
	protected $lastLocation = false;    /** efektywny url ostatniego requesta */
	protected $lastCode = 0;            /** kod http odpowiedzi na ostatni request */
	protected $lastHeaders = array();   /** zestaw nagłówków http z odpowiedzi */
	protected $lastCache = array();     /** zestaw dyrektyw Cache-Control z odpowiedzi */

	const GET = 0;
	const POST_FORM = 1;
	const POST_BINARY = 2;
	const POST_RAW = 3;
	const POST_RAWFILE = 4;

	/**
	 * Inicjalizacja połączenia i ustawienie podstawowych parametrów:
	 *
	 * - zwracanie nagłówka przez curl_exec (będzie w razie czego
	 *   obcięty przez klasę, ale jest potrzebny do analizy)
	 *
	 * - domyślny User Agent brany z przeglądarki użytkownika, jeśli
	 *   klasa jest uruchamiana ze skryptu webowego
	 *
	 * - dla Windows wyłączana jest weryfikacja certyfikatów SSL (nie
	 *   działa dobrze na Apache, na innych serwerach nie testowane)
	 */
	public function __construct()
	{
		$this->connection = curl_init();
		curl_setopt( $this->connection, CURLOPT_HEADER, 1 );
		curl_setopt( $this->connection, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $this->connection, CURLOPT_AUTOREFERER, 1 );

		if ( isset($_SERVER["HTTP_USER_AGENT"]) ) {
			$this->setAgent( $_SERVER["HTTP_USER_AGENT"] );
		}

		if ( strpos(PHP_OS, "WIN") !== false ) {
			$this->setSslVerify( 0 );
		}
	}

	public function __destruct()
	{
		curl_close( $this->connection );
	}

	/**
	 * Uruchamia zarządzanie nagłówkiem Referer. Pierwszy wykonany request
	 * będzie miał wstawiony podany adres w polu Referer - jednocześnie url
	 * tego requesta zostanie zapamiętany we właściwości $this->referer.
	 *
	 * Każdy następny request będzie wykorzystywał jako Referer url z tej
	 * właściwości, po czym nadpisywał ją urlem bieżącym.
	 */
	public function setReferer( $url = false )
	{
		$this->referer = $url ? $url : false;
	}

	/**
	 * Uruchamia ograniczanie zużycia łącza - działa to w ten sposób, że
	 * wymuszany jest podany tutaj czas pomiędzy _startem_ 2 kolejnych
	 * requestów - jeśli upłynęło zbyt mało czasu, używana jest funkcja
	 * sleep() do wstrzymania działania skryptu na pozostały czas.
	 *
	 * Działa tylko dla requestów GET, oraz tylko wtedy, gdy dane nie są
	 * transparentnie pobierane z cache.
	 *
	 * TODO: zamiana sekund na milisekundy.
	 */
	public function setDelay( $delay )
	{
		$this->delay = $delay;
	}

	/**
	 * Ustawia podany adres IP lokalnego interfejsu sieciowego do użytku
	 * przez bibliotekę cURL przy wykonywaniu requestów.
	 *
	 * Metoda nigdy nie testowana, aczkolwiek funkcjonalność weryfikowana
	 * z kilkoma źródłami dokumentacji. Nie ma też metody zwracającej
	 * listę lokalnych interfejsów sieciowych, ani metody mapującej
	 * lokalne IP na zewnętrzne-wychodzące IP.
	 */
	public function setInterface( $ip )
	{
		curl_setopt( $this->connection, CURLOPT_INTERFACE, $ip );
	}

	/**
	 * Ustawia generalny timeout w sekundach dla curl_exec().
	 */
	public function setTimeout( $sec )
	{
		curl_setopt( $this->connection, CURLOPT_TIMEOUT, $sec );
	}

	/**
	 * Ustawia zakres danych w bajtach (np. "0-4096") do zwrócenia przez
	 * zdalny serwer i ściągnięcia.
	 *
	 * Metoda nigdy nie testowana z uwagi na fakt, że cURL nie ma metody
	 * anulującej tą opcję dla istniejącego połączenia - a co za tym idzie,
	 * nie ma możliwości podania zakresu tylko dla pojedynczego pliku bez
	 * tworzenia osobnej instancji tej klasy dla każdego pobieranego pliku.
	 */
	public function setRange( $range )
	{
		curl_setopt( $this->connection, CURLOPT_RANGE, $range );
	}

	/**
	 * Ustawia deklarowaną nazwę przeglądarki www do wysyłania do serwera.
	 */
	public function setAgent( $agent )
	{
		curl_setopt( $this->connection, CURLOPT_USERAGENT, $agent );
	}

	/**
	 * Ustawia adres i opcjonalnie port serwera proxy, którego klasa ma
	 * używać przy wykonywaniu requestów.
	 *
	 * Metoda nigdy nie testowana, aczkolwiek funkcjonalność weryfikowana
	 * z kilkoma źródłami dokumentacji.
	 */
	public function setProxy( $proxy )
	{
		curl_setopt( $this->connection, CURLOPT_PROXY, $proxy );
	}

	/**
	 * Ustawia login i hasło do serwera proxy, którego klasa ma używać
	 * przy wykonywaniu requestów.
	 *
	 * Metoda nigdy nie testowana, aczkolwiek funkcjonalność weryfikowana
	 * z kilkoma źródłami dokumentacji.
	 */
	public function setProxyPass( $username, $password )
	{
		curl_setopt( $this->connection, CURLOPT_PROXYUSERPWD, "$username:$password" );
	}

	/**
	 * Ustawia login i hasło do autoryzacji http do pobieranego pliku.
	 *
	 * Podane login i hasło są wykorzystywane we wszystkich kolejnych
	 * requestach, w tym do różnych serwerów itp. - stąd należy ostrożnie
	 * używać tej opcji, gdyż cURL wysyła nagłówek autoryzacji na ślepo,
	 * nawet jeśli zdalny serwer o to nie prosi.
	 *
	 * http://wortal.php.pl/phppl/wortal/artykuly/php/biblioteki/curl_cz_1_podstawy_i_protokol_http/identyfikacja_uzytkownika
	 */
	public function setAuth( $username, $password )
	{
		curl_setopt( $this->connection, CURLOPT_HTTPAUTH, CURLAUTH_ANY );
		curl_setopt( $this->connection, CURLOPT_USERPWD, "$username:$password" );
	}

	/**
	 * Ustawia nazwę pliku-kontenera dla ciasteczek.
	 */
	public function setCookieJar( $cookiejar )
	{
		curl_setopt( $this->connection, CURLOPT_COOKIEJAR, $cookiejar );
		curl_setopt( $this->connection, CURLOPT_COOKIEFILE, $cookiejar );
	}

	/**
	 * Włącza tryb gadatliwy - skutkuje to wypisywaniem na stdout większej
	 * ilości danych dot. requesta (zwracanego nagłówka itp.).
	 *
	 * Dane wypisywane są tylko na stdout, zatem metoda nadaje się tylko do
	 * skryptów konsolowych - przynajmniej do czasu implementacji ob_start
	 * do przechwytywania komunikatów cURLa.
	 */
	public function setVerbose( $verbose = 1 )
	{
		curl_setopt( $this->connection, CURLOPT_VERBOSE, $verbose );
	}

	/**
	 * Ustawia podany identyfikator cache dla ściąganych danych. Cache jest
	 * używany tylko dla requestów GET i tylko w sytuacji, gdy jeśli kod
	 * odpowiedzi od serwera był 1xx, 2xx lub 3xx. Oprócz nazwy segmentu
	 * należy podać również okres przechowywania danych w sekundach.
	 */
	public function setCache( $id, $period )
	{
		unset( $this->cache );
		$this->cache = KlimCache::getInstance( $id );
		$this->period = $period;
	}

	/**
	 * Włącza lub wyłącza weryfikację certyfikatów SSL.
	 *
	 * cURL nie jest w stanie zapytać użytkownika, czy ufa przedstawionemu
	 * przez serwer certyfikatowi - dlatego też przy domyślnie włączonej
	 * weryfikacji po prostu odrzuca wszelkie certyfikaty, co do których
	 * występują problemy (niezgodność nazwy hosta, nieznane CA itp.).
	 *
	 * Weryfikacja może źle działać na Windows z Apache 2.x - dlatego też
	 * jest tam wyłaczana domyślnie w konstruktorze klasy.
	 *
	 * W przypadku, gdy weryfikacja jest włączona i zostanie wykryty błąd
	 * certyfikatu, request nie zostanie w ogóle wykonany.
	 *
	 * Uwaga: wyłączenie weryfikacji certyfikatów może stanowić zagrożenie
	 * bezpieczeństwa danych.
	 */
	public function setSslVerify( $verify )
	{
		curl_setopt( $this->connection, CURLOPT_SSL_VERIFYPEER, $verify );
		curl_setopt( $this->connection, CURLOPT_SSL_VERIFYHOST, $verify );
	}

	/**
	 * Włącza lub wyłącza podążanie za przekierowaniami http.
	 *
	 * Jeśli zdalny serwer odpowiada kodem 301/302, cURL w zależności od
	 * tego ustawienia może wykonać kolejnego requesta na url z nagłówka
	 * Location - domyślnie maksymalna liczba takich podążeń to 50.
	 *
	 * W razie potrzeby efektywny url może być wyciągnięty przy użyciu
	 * metody getEffectiveUrl().
	 */
	public function setFollowLocation( $follow )
	{
		curl_setopt( $this->connection, CURLOPT_FOLLOWLOCATION, $follow );
		$this->followLocation = $follow;
	}

	/**
	 * Włącza lub wyłącza zwracanie nagłówków http przez metodę execute()
	 *
	 * To ustawienie nie ma wpływu na cache'owanie nagłówków otrzymanych
	 * od serwera - są one zawsze cache'owane i odpowiednio przetwarzane
	 * przez klasę, natomiast to ustawienie dotyczy tylko i wyłącznie
	 * ostatecznego zwracania ich do klienta.
	 *
	 * Należy mieć na uwadze, że w trybie podążania za przekierowaniami
	 * może zostać zwróconych wiele następujących po sobie nagłówków,
	 * oddzielonych pustymi liniami.
	 */
	public function setReturnHeader( $returnheader )
	{
		$this->returnheader = $returnheader;
	}

	/**
	 * Zwraca ostatni wewnętrzny błąd cURLa lub pusty ciąg, jeśli ostatnia
	 * operacja zakończyła się pomyślnie.
	 */
	public function getError()
	{
		return curl_error( $this->connection );
	}

	/**
	 * Zwraca identyfikator ostatniego wewnętrznego błędu cURLa lub 0,
	 * jeśli ostatnia operacja zakończyła się pomyślnie.
	 */
	public function getErrno()
	{
		return curl_errno( $this->connection );
	}

	/**
	 * Zwraca sumaryczny czas aktywności sieciowej, tj. wykonań funkcji
	 * curl_exec().
	 */
	public function getTotalTime()
	{
		return $this->totalTime;
	}

	/**
	 * Zwraca sumaryczny rozmiar danych ściągniętych przez curl_exec().
	 *
	 * Rozmiar danych ściągniętych może się różnić od rozmiaru danych
	 * zwróconych przez metodę execute() z uwagi na ewentualną kompresję
	 * http (klasa dokonuje wówczas dekompresji w locie).
	 */
	public function getTotalSize()
	{
		return $this->totalSize;
	}

	/**
	 * Zwraca średnią prędkość przesyłania danych (iloraz sumarycznego
	 * rozmiaru ściągniętych danych i czasu aktywności sieciowej).
	 */
	public function getTotalSpeed()
	{
		return (int)( $this->totalSize / ( $this->totalTime ? $this->totalTime : 1 ) );
	}

	/**
	 * Zwraca url z nagłówka Location, jeśli przy ostatnim requeście
	 * serwer odpowiedział takim nagłówkiem, oraz jeśli podążanie za
	 * przekierowaniami jest wyłączone.
	 */
	public function getRedirectUrl()
	{
		return $this->newLocation;
	}

	/**
	 * Zwraca efektywny url requesta - w sytuacji, gdy zdalny serwer
	 * odpowiedział na requesta kodem 301/302 i nagłówkiem Location,
	 * cURL mógł opcjonalnie wykonać kolejny request na adres z tego
	 * nagłówka. Efektyny url to wówczas albo ostatni url, który
	 * rzeczywiście został pobrany, albo url strony przekierowującej.
	 *
	 * Przy czym to działa tylko jeśli podążanie za przekierowaniami
	 * jest włączone - jeśli nie, url z nagłówka Location zostanie
	 * zapisany w innej właściwości i będzie go można pobrać metodą
	 * getRedirectUrl().
	 */
	public function getEffectiveUrl()
	{
		return $this->lastLocation;
	}

	/**
	 * Zwraca kod http ostatniej odpowiedzi od zdalnego serwera. Jeśli
	 * ostatnio wystąpił błąd (np. weryfikacji certyfikatu SSL), zwraca
	 * liczbę 0 - podobnie jeśli w bieżącej instancji klasy nie wykonano
	 * jeszcze żadnego requesta.
	 */
	public function getResponseCode()
	{
		return $this->lastCode;
	}

	/**
	 * Zwraca tablicę dodatkowych nagłówków http wyłuskanych z ostatniej
	 * odpowiedzi od zdalnego serwera, związanych z cache'owaniem danych.
	 */
	public function getCacheHeaders()
	{
		return $this->lastHeaders;
	}

	/**
	 * Zwraca informacje sterujące cache'owaniem danych wyłuskane z
	 * ostatniej odpowiedzi od zdalnego serwera (nagłówek Cache-Control).
	 */
	public function getCacheControl()
	{
		return $this->lastCache;
	}


	/**
	 * Callback służący do przesyłania danych w trybie raw post body do
	 * zdalnego serwera.
	 */
	protected function callbackBody( $connection, $fd, $length )
	{
		if ( !$this->raw_length || $this->position >= $this->raw_length ) {
			return false;
		}

		if ( is_string($this->raw_data) ) {
			$ret = substr( $this->raw_data, $this->position, $length );
			$this->position += $length;
			return $ret;

		} elseif ( is_resource($this->raw_data) ) {
			$this->position += $length;
			return fread( $this->raw_data, $length );

		} else {
			return false;
		}
	}

	/**
	 * Usuwa z cache podany url. Można podać albo kompletny url, albo
	 * bazowy url i parametry GET.
	 */
	public function deleteCachedUrl( $url, $vars = false )
	{
		if ( $this->cache ) {
			$url = KlimHttpRequest::buildUrl( $url, $vars );
			$this->cache->delete( $url );
		}
	}

	/**
	 * Wykonuje requesta GET/POST (właściwa implementacja).
	 *
	 * Ta metoda została wydzielona z metody execute() w celu uproszczenia
	 * implementacji cache'owania. Implementuje ona właściwą funkcjonalność
	 * komunikacyjną podczas, gdy execute() zajmuje się analizą nagłówków
	 * otrzymanych od zdalnego serwera.
	 */
	protected function doExecute( $url, $vars, $vars_post, $type, $headers )
	{
		if ( $type == self::GET || !$vars_post ) {

			/**
			 * Dla requestów GET najpierw próbujemy wyciągnąć dane z cache.
			 */
			if ( $this->cache ) {
				$ret = $this->cache->get( $url );

				if ( $ret !== false && $ret !== null ) {
					if ( $this->referer ) {
						$this->referer = $url;
					}
					return array( $ret, true );
				}
			}

			curl_setopt( $this->connection, CURLOPT_HTTPGET, true );
		} else {
			curl_setopt( $this->connection, CURLOPT_POST, true );

			if ( $type != self::POST_RAW && $type != self::POST_RAWFILE ) {

				if ( is_array($vars_post) && $type != self::POST_BINARY ) {
					$fields = KlimHttpRequest::buildQuery( $vars_post );
				} else {
					$fields = $vars_post;
				}

				curl_setopt( $this->connection, CURLOPT_POSTFIELDS, $fields );

			} else {
				if ( $type == self::POST_RAWFILE ) {

					$fp = @fopen( $vars_post, "rb" );
					if ( !$fp ) {
						throw new KlimRuntimeException( "cannot open raw post data file $vars_post, url $url" );
					}

					$stat = fstat( $fp );
					$this->raw_data = $fp;
					$this->raw_length = $stat["size"];
				} else {
					$this->raw_data = $vars_post;
					$this->raw_length = strlen( $vars_post );
				}

				$headers["content-length"] = $this->raw_length;
				curl_setopt( $this->connection, CURLOPT_READFUNCTION, array($this, "callbackBody") );
			}
		}

		/**
		 * Check the time since start of the previous request, and
		 * if it's less than declared delay time, pause the script.
		 */
		if ( $this->delay ) {
			if ( $this->lastTime + $this->delay > time() ) {
				sleep( $this->delay - ( time() - $this->lastTime ) );
			}
			$this->lastTime = time();
		}

		/**
		 * Convert all headers to the canonical form.
		 */
		if ( !empty($headers) ) {
			$headersFmt = array();

			foreach ( $headers as $name => $value ) {
				$canonicalName = implode( "-", array_map( "ucfirst", explode("-", $name) ) );
				$headersFmt[]  = $canonicalName . ": " . $value;
			}

			curl_setopt( $this->connection, CURLOPT_HTTPHEADER, $headersFmt );
		}

		if ( $this->referer ) {
			curl_setopt( $this->connection, CURLOPT_REFERER, $this->referer );
			$this->referer = $url;
		}

		/**
		 * Perform the actual request execution.
		 */
		curl_setopt( $this->connection, CURLOPT_URL, $url );

		$time_start = KlimTime::getNow();
		$ret = curl_exec( $this->connection );
		$time_end = KlimTime::getNow();

		$this->totalTime += ( $time_end - $time_start );
		$this->totalSize += curl_getinfo( $this->connection, CURLINFO_SIZE_DOWNLOAD );

		/**
		 * Cleanup.
		 */
		if ( $type == self::POST_RAWFILE ) {
			fclose( $fp );
		}

		$this->raw_data = false;
		$this->raw_length = 0;
		$this->position = 0;

		return array( $ret, false );
	}

	/**
	 * Wykonuje requesta GET/POST.
	 *
	 * Zmienna $vars_post może przybierać 4 formy:
	 * - dane formularza w formie urlencoded
	 * - tablica parametrów (również zagnieżdżona)
	 * - surowe dane jako string
	 * - surowe dane jako nazwa pliku
	 *
	 * http://urzenia.net/350/curlowe-zagwozdki/
	 * http://wortal.php.pl/wortal/artykuly/php/biblioteki/curl_cz_1_podstawy_i_protokol_http/formularze
	 */
	public function execute( $url, $vars = false, $vars_post = false, $type = self::GET, $headers = array() )
	{
		$url = KlimHttpRequest::buildUrl( $url, $vars );

		$this->lastCache = array();
		$this->lastHeaders = array();
		$this->newLocation = false;
		$this->lastLocation = $url;
		$this->lastCode = 0;

		$result = $this->doExecute( $url, $vars, $vars_post, $type, $headers );

		if ( $result === false ) {
			return false;
		}

		/**
		 * Tablica przekształceń nagłówków - służy do zamiany nagłówków
		 * wieloliniowych (rfc2616, 2.2) na zwykłe, łatwe do parsowania.
		 */
		$from = array( "\r\n", "\n ", "\n\t", "\t" );
		$to = array( "\n", " ", " ", " " );

		/**
		 * Wyciągamy ze ściągniętych danych ostatni nagłówek http od serwera
		 * (z niego wyciągamy kod http, oraz informacje o kompresji).
		 */
		$from_cache = $result[1];
		$content = $result[0];
		do {
			$stop = strpos( $content, "\r\n\r\n" );
			$header = substr( $content, 0, $stop + 2 );
			$content = substr( $content, $stop + 4 );

			$header = str_replace( $from, $to, $header );

			/**
			 * W bardziej skomplikowanych przypadkach ostatnia odpowiedź
			 * http nie musi zawierać nagłówka Location - może to być
			 * odpowiedź od jakiegoś proxy, a Location może być np. w
			 * przedostatnim.
			 */
			if ( preg_match("/Location: (.+)/i", $header, $location) ) {
				if ( $this->followLocation ) {
					$this->lastLocation = $location[1];
				} else {
					$this->newLocation = $location[1];
				}
			}

		} while ( substr($content, 0, 5) == "HTTP/" );

		$parser = new KlimHttpResponseParser( $header );

		/**
		 * Wyciągamy kod http - albo z ostatniego nagłówka (jeśli jest),
		 * albo z cURLa, który być może gdzieś go zdołał znaleźć (o ile
		 * dane nie idą z cache).
		 */
		if ( $code = $parser->getCode() ) {
			$this->lastCode = $code;
		} else {
			$this->lastCode = curl_getinfo( $this->connection, CURLINFO_HTTP_CODE );
		}

		/**
		 * Wyciągamy nagłówki http i dyrektywy Cache-Control.
		 */
		$this->lastHeaders = $parser->getHeaders();
		$this->lastCache = $parser->getCacheControl();

		/**
		 * Jeśli spełnione są wstępne warunki cache'owania, określamy na
		 * podstawie nagłówków http maksymalny dopuszczalny czas składowania
		 * danych w cache i je zapisujemy.
		 */
		if ( $this->cache && !$from_cache && ($type == self::GET || !$vars_post) && ($this->lastCode == 200 || $this->lastCode == 301) ) {
			$period = KlimHttpResponseCache::computePeriod( $url, $this->lastHeaders, $this->lastCache, $this->period );
			if ( $period ) {
				$this->cache->set( $url, $result[0], $period );
			}
		}

		/**
		 * Jeśli włączone jest zwracanie nagłówków http, kończymy w tym
		 * miejscu bez dekompresji strony. Zwracamy wówczas skompresowaną
		 * stronę wraz ze wszystkimi nagłówkami.
		 */
		if ( $this->returnheader ) {
			return $result[0];
		}

		/**
		 * Wyciągamy z ostatniego nagłówka informacje o kompresji, a jeśli
		 * strona jest skompresowana (co dotyczy z reguły plików JS/CSS),
		 * rozpakowujemy ją.
		 */
		if ( $encoding = $parser->getContentEncoding() ) {
			switch ( $encoding ) {
				case "gzip":
				case "x-gzip":
					$content = gzinflate( substr($content, 10) );
					break;
				case "deflate":
					$content = gzuncompress( $content );
					break;
				case "bzip2":
					// http://pl.php.net/manual/en/function.bzdecompress.php
					KlimLogger::error( "http", "found encoding method: $encoding, url $url" );
					$content = bzdecompress( $content );
					break;
				default:
					KlimLogger::error( "http", "unknown encoding method: $encoding, url $url" );
			}
		}

		return $content;
	}
}

