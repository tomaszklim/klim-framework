<?php
/**
 * Klasa bazowa klienta do transferu plików
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
 * Kilka prostych założeń odnośnie konfiguracji segmentów:
 * - $settings["remote_dir"] przechowuje zdalny katalog bazowy
 * - $settings["local_dir"] przechowuje lokalny katalog bazowy
 * - $remote_file to nazwa względna do $settings["remote_dir"]
 * - $local_file to nazwa względna do $settings["local_dir"]
 *
 * Są 2 typy klientów:
 * - native
 * - command line
 *
 * Klientem command line można się podłączyć właściwie do wszystkiego,
 * do czego da się znaleźć klienta CLI, ale:
 * - kuleje wydajność, rośnie zasobożerność
 * - kuleje bezpieczeństwo (trzeba bardzo uważnie escape'ować parametry
 *   metod)
 * - kuleje prawidłowa obsługa błędów (nie zawsze da się wszystko
 *   wychwycić i sparsować)
 * - interaktywności i tak nie ma - stdin trzeba zapodać przez echo i
 *   nie da się dorzucić np. hasła, czy czegokolwiek o zmiennym schemacie
 *
 * Klasy klienckie command line mają w nazwach przedrostek C.
 *
 * Natomiast wady klientów native to:
 * - konieczność posiadania odpowiedniego modułu do php, biblioteki itp.
 * - uruchamianie kodu z jakichś bibliotek i modułów w przestrzeni PHP
 *   i kwestie stabilności, bezpieczeństwa, dojrzałości kodu itd.
 *
 *
 * TODO: planowane są nowe konektory:
 * - local (do plików lokalnych, do manipulacji konfiguracją w runtime)
 * - SVN
 */
abstract class KlimTransfer
{
	protected $connection = false;
	protected $retries_loops = 3;
	protected $retries_seconds = 0;
	protected $remote_dir = "";
	protected $local_dir = "";

	/**
	 * TODO: $settings -> segmenty
	 */
	public static function getInstance( $settings )
	{
		if ( !$settings ) {
			return false;
		}

		$transport = $settings["transport"];
		switch ( $transport ) {
			case "csftp":  $class = "KlimTransferCsftp";  break;
			case "cssh":   $class = "KlimTransferCssh";   break;
			case "ssh":    $class = "KlimTransferSsh";    break;
			case "ftp":    $class = "KlimTransferFtp";    break;
			default:
				throw new KlimApplicationException( "unknown transport type $transport" );
		}

		$instance = new $class( $settings );

		if ( !$instance->connect() ) {
			throw new KlimRuntimeTransferException( "cannot connect to remote server through $transport" );
		}

		return $instance;
	}

	/**
	 * Metoda uruchamiająca programy zewnętrzne na potrzeby klientów
	 * command line, oraz przechwytująca zwrócone przez nie dane.
	 *
	 * W przypadku klientów native również może być używana do
	 * uruchamiania programów zewnętrznych na zdalnym serwerze,
	 * jeśli tylko dany klient taki tryb działania obsługuje.
	 */
	protected function execute( $command )
	{
		return KlimShell::execute( $command );
	}

	/**
	 * Metoda uruchamiająca programy zewnętrzne i kontrolująca
	 * wynik ich wykonania. Wykonuje kolejno 4 działania:
	 * - przekształcenie podanego polecenia
	 * - wywołanie execute()
	 * - sprawdzenie, czy wystąpił błąd
	 * - przepuszczenie wyniku przez parseShell()
	 */
	protected function executeCheck( $remote_command, $raw = false )
	{
		return false;
	}

	/**
	 * Standardowa metoda do uruchamiania krytycznych akcji w sposób
	 * powtarzalny, dzięki czemu w przypadku wystąpienia błędu akcja
	 * zostanie powtórzona zdefiniowaną ilość razy, a pomiędzy
	 * powtórzeniami zostanie wykonana zdefiniowana pauza.
	 */
	protected function executeCheckRetry( $remote_command, $raw = false )
	{
		for ( $i = 0; $i < $this->retries_loops; $i++ ) {

			if ( $ret = $this->executeCheck($remote_command, $raw) ) {
				return true;
			}

			if ( $this->retries_seconds > 0 ) {
				sleep( $this->retries_seconds );
			}
		}

		return false;
	}

	/**
	 * Metoda parsująca zawartość katalogu, zarówno z systemu lokalnego,
	 * jak i przekazaną ze zdalnego serwera (np. dla ftp). Implementując
	 * nową klasę kliencką należy pamiętać, że różne systemy operacyjne,
	 * a nawet takie same przy różnych ustawieniach locali, potrafią
	 * zwracać zawartość katalogu w różnych formatach, w szczególności
	 * format daty może być różny - obejmuje to też zmienną liczbę kolumn
	 * dla każdej przekazywanej informacji. Ta metoda powinna obsłużyć
	 * każdy format pod warunkiem, że w nazwach plików nie ma spacji.
	 */
	protected function parseDir( $items )
	{
		$list = array();

		foreach ( $items as $item ) {
			$filename = end( explode(" ", $item) );
			if ( $filename != ".." && $filename != "." ) {

				if ( substr($item, 0, 1) == "d" ) {
					$list["dir"][] = basename( $filename );
				} else if ( substr($item, 0, 1) == "-" ) {
					$list["file"][] = basename( $filename );
				}
			}
		}

		return $list;
	}

	/**
	 * Metoda parsująca odpowiedzi od shella i szukająca błędów
	 * typu brak dostępu, nie znaleziono pliku/katalogu itp.
	 */
	protected function parseShell( $output )
	{
		$errors = array (
			"Permission denied",
			"No such file or directory",
			"Brak dostępu",
			"Nie ma takiego pliku ani katalogu",
		);

		foreach ( $errors as $error ) {
			if ( stripos($output, $error) !== false ) {
				KlimLogger::info( "core", "permission problem found when executing command", $output );
				return false;
			}
		}

		return true;
	}

	/**
	 * W przypadku klientów utrzymujących stałe połączenie ze zdalnym
	 * serwerem ta metoda otwiera takie połączenie i zwraca status.
	 *
	 * W przypadku klientów tworzących nowe połączenie przy każdym
	 * wykonanym działaniu, ta metoda wykonuje jedno testowe działanie
	 * i zwraca status jego wykonania.
	 */
	abstract public function connect();

	/**
	 * Zwraca arraya z kluczami "dir" i "file", zawierającymi
	 * nazwy plików i katalogów (bez ścieżek) w podanym katalogu.
	 * Opcjonalnie pokazuje pliki i katalogi ukryte, natomiast
	 * nie stosuje rekurencji.
	 *
	 * Parametr $dir przyjmuje ścieżkę względną do katalogu
	 * bazowego, podanego w konfiguracji segmentu.
	 */
	abstract public function listDir( $dir, $show_hidden = false );

	/**
	 * Tworzy podany katalog na zdalnym serwerze.
	 *
	 * Parametr $dir przyjmuje ścieżkę względną do katalogu
	 * bazowego, podanego w konfiguracji segmentu.
	 */
	abstract public function makeDir( $dir );

	/**
	 * Usuwa podany katalog ze zdalnego serwera.
	 *
	 * Parametr $dir przyjmuje ścieżkę względną do katalogu
	 * bazowego, podanego w konfiguracji segmentu.
	 */
	abstract public function deleteDir( $dir, $recursive = false );

	/**
	 * Usuwa podany plik ze zdalnego serwera.
	 *
	 * Parametr $file przyjmuje ścieżkę względną do katalogu
	 * bazowego, podanego w konfiguracji segmentu.
	 */
	abstract public function deleteFile( $file );

	/**
	 * Kopiuje podany plik na zdalny serwer.
	 *
	 * Parametry $local_file i $remote_file przyjmują ścieżki względne
	 * do katalogów bazowych, podanych w konfiguracji segmentu.
	 */
	abstract public function putFile( $local_file, $remote_file );

	/**
	 * Kopiuje podany plik ze zdalnego serwera.
	 *
	 * Parametry $local_file i $remote_file przyjmują ścieżki względne
	 * do katalogów bazowych, podanych w konfiguracji segmentu.
	 */
	abstract public function getFile( $remote_file, $local_file );

	/**
	 * Zmienia nazwę pliku lub katalogu na zdalnym serwerze.
	 *
	 * Parametry $oldname i $newname przyjmują ścieżki względne
	 * do katalogów bazowych, podanych w konfiguracji segmentu.
	 */
	abstract public function rename( $oldname, $newname );
}

