<?php
/**
 * Klasa bazowa sterownika baz danych
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
 * Uniwersalny klient do baz danych. Obsługuje z założenia dowolny typ
 * serwera baz danych, do którego ma napisany odpowiedni sterownik.
 *
 * Klient ten dostarcza jednolite API dla wszystkich obsługiwanych typów
 * baz danych, a także w pewnym zakresie dopasowuje uniwersalne zapytania
 * do specyfiki bieżącej bazy danych - robi to we współpracy z innymi
 * klasami należącymi do infrastruktury bazodanowej core'a aplikacji.
 *
 * Nie należy używać go bezpośrednio - do bezpośredniego użycia nadaje
 * się tylko klasa KlimDatabase, która implementuje prosty interfejs do
 * abstrakcyjnych operacji na źródłach danych (CRUD + obsługa transakcji).
 */
abstract class KlimDatabaseDriver
{
	protected $dbhost;
	protected $dbuser;
	protected $dbpass;
	protected $dbname;
	protected $persistent;
	protected $charset;  // metoda kodowania znaków diakrytycznych (w konwencji używanej przez serwer baz danych)
	protected $connection = false;
	protected $trxactive = false;
	protected $trxbroken = false;
	protected $trxdatabase = false;
	protected $trxinstance = 0;
	protected $errno = 0;
	protected $error = false;
	protected $fetch_error = false;
	private $last_sql = "";

	public function __construct( $host, $user, $pass, $name, $charset, $persistent )
	{
		$this->dbhost = $host;
		$this->dbuser = $user;
		$this->dbpass = $pass;
		$this->dbname = $name;
		$this->charset = $charset;
		$this->persistent = $persistent;
	}

	public function __destruct()
	{
		$this->close();
	}

	/**
	 * Metoda zwracająca metodę kodowania znaków diakrytycznych przez
	 * gotowy obiekt w formacie akceptowalnym przez funkcję iconv.
	 *
	 * Uwaga: w konfiguracji segmentu fizycznego należy podać metodę,
	 * jaka jest używana przez serwer baz danych - tam jednak podaje
	 * się ją w takim formacie, jakiej oczekuje serwer, natomiast tutaj
	 * zwracana jest w formacie zgodnym z iconv.
	 *
	 * Np. dla MySQL ta pierwsza to "latin2", a druga to "iso-8859-2".
	 */
	abstract public function getEncoding();

	/**
	 * Metoda zwracająca typ serwera baz danych, z jakim fizycznie
	 * zestawione jest połączenie w tym obiekcie.
	 */
	abstract public function getVendor();

	/**
	 * Metoda otwierająca połączenie z serwerem baz danych. Klasa dostaje
	 * komplet namiarów w konstruktorze, zatem zadaniem tej metody jest
	 * tylko nawiązanie połączenia z serwerem, oraz wynegocjowanie metody
	 * kodowania znaków diakrytycznych podanej w konfiguracji segmentu.
	 */
	abstract public function connect();

	/**
	 * Zwraca informację (związaną z ostatnim błędem), czy połączenie
	 * z serwerem zostało rozłączone, czy nie - na tej informacji polega
	 * logika obsługi błędów i obsługi transakcji.
	 */
	abstract public function isDisconnect();

	/**
	 * Zwraca informację, czy ostatni błąd w wykonaniu zapytania nastąpił
	 * wskutek próby złamania ograniczenia unikalnościowego (dotyczy
	 * zapytań insert i update).
	 */
	abstract public function isDuplicate();

	/**
	 * Zwraca informację, czy ostatni błąd w wykonaniu zapytania nastąpił
	 * wskutek problemu ze ściągnięciem wyniku od serwera (dotyczy tylko
	 * zapytań select).
	 */
	public function isFetchError()
	{
		return $this->fetch_error;
	}

	/**
	 * Zwraca informację, czy ostatni błąd w wykonaniu zapytania nastąpił
	 * wskutek przełączenia bazy danych w tryb tylko do odczytu, bądź też
	 * wskutek braku obsługi operacji modyfikujących dane przez sterownik
	 * (np. dostęp do bazy Microsoft Access przez mdbtools).
	 */
	public function isReadOnly()
	{
		return false;
	}

	/**
	 * Zwraca informację, czy obiekt ma nawiązane połączenie z bazą danych.
	 */
	public function isOpen()
	{
		return $this->connection ? true : false;
	}

	/**
	 * Zwraca numeryczny identyfikator ostatniego błędu, jaki wystąpił
	 * na poziomie natywnego sterownika bazy danych. Uwaga: każda baza
	 * danych posiada swoje własne zestawy błędów.
	 */
	abstract public function lastErrno();

	/**
	 * Zwraca tekstowy opis ostatniego błędu, jaki wystąpił na poziomie
	 * natywnego sterownika bazy danych. Uwaga: każda baza danych posiada
	 * swoje własne zestawy błędów.
	 */
	abstract public function lastError();

	/**
	 * Zwraca treść ostatnio wykonywanego zapytania.
	 */
	public function lastQuery()
	{
		return $this->last_sql;
	}

	/**
	 * Zwraca kolejną wartość z sekwencji powiązanej z podaną tabelą, lub
	 * false, jeśli dla bieżącej bazy danych uzupełnianie kluczy głównych
	 * należy wykonywać przez pominięcie pola w zapytaniu insert.
	 */
	abstract public function nextSequenceValue( $seq_name );

	/**
	 * Zwraca wartość wstawioną do klucza głównego w ostatnim zapytaniu
	 * insert. Na niektórych bazach danych poprawny wynik tej metody może
	 * zależeć od wcześniejszego wykonania metody nextSequenceValue, na
	 * innych wynik może być tracony po wykonaniu innego zapytania.
	 */
	abstract public function insertId();

	/**
	 * Zwraca liczbę wierszy zmienionych ostatnim zapytaniem update, bądź
	 * usuniętych ostatnim zapytaniem delete. W zależności od bazy danych,
	 * może to być albo liczba wierszy zakwalifikowanych do zmiany, albo
	 * liczba wierszy faktycznie zmienionych.
	 */
	abstract public function affectedRows();

	/**
	 * Sprawdza, czy połączenie z serwerem baz danych jest wciąż aktywne.
	 * Dla niektórych baz danych sterownik natywny może automatycznie
	 * próbować połączyć się ponownie, jeśli połączenie zostało zerwane
	 * (może to być również zależne od konfiguracji na poziomie sterownika
	 * natywnego). Zwraca true, jeśli połączenie było aktywne, lub zostało
	 * nawiązane ponownie, oraz false w pozostałych przypadkach.
	 */
	abstract public function ping();

	/**
	 * Zamyka połączenie z serwerem baz danych, czyści właściwości prywatne
	 * z nim związane i opcjonalnie wycofuje transakcję. Właściwe zamykanie
	 * połączenia wykonywane jest metodą doClose.
	 */
	public function close( $cleanup_trx = true )
	{
		if ( $this->connection ) {
			if ( $this->trxactive && $cleanup_trx ) {
				$this->rollback();
			}

			if ( $this->doClose() ) {
				$this->connection = false;
			}
		}

		return $this->isOpen();
	}

	/**
	 * Właściwa implementacja zamknięcia połączenia z serwerem baz danych
	 * na poziomie sterownika natywnego.
	 */
	abstract protected function doClose();

	/**
	 * Właściwa implementacja wykonania zapytania na poziomie sterownika
	 * natywnego. W miarę możliwości wykonuje tzw. "prepared query", czyli
	 * zapytanie z podanymi specjalnymi znacznikami w miejsce danych, gdzie
	 * właściwe dane przekazywane są w parametrze $variables.
	 */
	abstract protected function doQuery( $sql, $variables = false );

	/**
	 * Parsuje podaną datę i konwertuje na natywny format bieżącej bazy
	 * danych.
	 */
	abstract public function convertDate( $date );

	/**
	 * Właściwa implementacja escape'owania ciągów tekstowych.
	 *
	 * http://www.ispirer.com/doc/sqlways39/Output/SQLWays-1-038.html
	 */
	abstract public function strencode( $arg );

	/**
	 * Escape'uje ciągi tekstowe do bezpiecznego użycia w zapytaniach.
	 * Jeśli zamiast tekstu (nawet pustego) podano false lub null, zwraca
	 * tekst "null" bez cudzysłowów.
	 */
	public function addQuotes( $arg )
	{
		if ( $arg || $arg === "0" ) {
			return "'" . $this->strencode( $arg ) . "'";
		} else {
			return "null";
		}
	}

	/**
	 * Wykonuje zapytanie do bazy danych, kontrolując jednocześnie stan
	 * połączenia z serwerem i stan ewentualnej transakcji. W razie
	 * napotkania problemu przy próbie wykonania zapytania, próbuje
	 * automatycznie go naprawić na tyle, na ile się da - wycofując
	 * ewentualną transakcję lub próbując zrestartować połączenie.
	 */
	public function query( $sql, $variables = false )
	{
		if ( !$this->connection ) {
			throw new KlimApplicationException( "not connected to database", $sql );
		}

		if ( $this->trxbroken ) {
			throw new KlimApplicationException( "attempt to execute query inside broken transaction", $sql );
		}

		$this->last_sql = $sql;
		$res = $this->execute( $sql, $variables );

		if ( $res === false ) {

			/**
			 * Sytuacja 1: prawdopobodnie zostało utracone połączenie z
			 * serwerem (sterowniki do niektórych baz danych mogą zwracać
			 * true przy isDisconnect nawet jeśli połączenie nadal działa).
			 */
			if ( $this->isFetchError() || $this->isDisconnect() ) {

				if ( $this->trxactive ) {
					$this->doQuery( "ROLLBACK" );
					$this->trxbroken = true;
					$this->close( false );

				} else if ( !$this->ping() ) {
					$this->close( false );

				} else {
					KlimLogger::info( "db", "trying to reexecute query after disconnect and succesful reconnection", $sql );

					$res = $this->execute( $sql, $variables );

					if ( $res === false && ($this->isFetchError() || $this->isDisconnect()) ) {
						$this->close( false );
					}
				}

			/**
			 * Sytuacja 2: połączenie z serwerem nadal działa i jesteśmy
			 * w środku aktywnej transakcji. Wycofujemy ją ja poziomie
			 * serwera baz danych i oznaczamy jako uszkodzoną.
			 */
			} else if ( $this->trxactive ) {
				$this->doQuery( "ROLLBACK" );
				$this->trxbroken = true;
			}
		}

		return $res;
	}

	/**
	 * Metoda pomocnicza, pośrednicząca w wykonaniu zapytania - wykonuje
	 * je, zapamiętuje dane dot. ewentualnych błędów wykonania, oraz w
	 * przypadku wystąpienia błędu loguje odpowiedni komunikat.
	 */
	protected function execute( $sql, $variables = false )
	{
		$this->fetch_error = false;

		$res = $this->doQuery( $sql, $variables );

		if ( $this->fetch_error ) {
			unset( $res );
			$res = false;
		} else {
			$this->errno = $this->lastErrno();
			$this->error = $this->lastError();
		}

		if ( $res === false ) {
			KlimLogger::error( "db", "$this->error [$this->errno]", $sql );
		}

		return $res;
	}

	/**
	 * Rozpoczyna transakcję bazodanową i obsługuje ewentualne błędy.
	 */
	public function begin( $db, $instance )
	{
		if ( !$this->connection ) {
			throw new KlimApplicationException( "not connected to database" );
		}

		if ( $this->trxactive ) {
			throw new KlimApplicationException( "transaction already started for database $db" );
		}

		$this->trxactive = true;
		$this->trxdatabase = $db;
		$this->trxinstance = $instance;

		$ret = $this->execute( "BEGIN" );

		if ( $ret === false && $this->isDisconnect() ) {
			$this->trxbroken = true;
			$this->close( false );
		}

		return $ret;
	}

	/**
	 * Zatwierdza transakcję bazodanową.
	 */
	public function commit()
	{
		if ( !$this->trxactive ) {
			throw new KlimApplicationException( "no transaction in progress" );
		}

		if ( $this->trxbroken ) {
			throw new KlimApplicationException( "cannot commit broken transaction" );
		}

		$ret = $this->execute( "COMMIT" );
		return $this->cleanupTransaction( $ret );
	}

	/**
	 * Wycofuje transakcję bazodanową.
	 *
	 * Uszkodzone transakcje są automatycznie wycofywane na poziomie bazy
	 * danych, natomiast na poziomie tej klasy są oznaczane jako uszkodzone
	 * i nie można ich już kontynuować, trzeba za to jawnie obsłużyć ich
	 * zakończenie.
	 */
	public function rollback()
	{
		if ( !$this->trxactive ) {
			throw new KlimApplicationException( "no transaction in progress" );
		}

		if ( $this->trxbroken ) {
			$ret = true;
		} else {
			$ret = $this->execute( "ROLLBACK" );
		}

		return $this->cleanupTransaction( $ret );
	}

	/**
	 * Metoda pomocnicza, sprzątająca po zakończonej transakcji.
	 */
	protected function cleanupTransaction( $ret )
	{
		// jeśli udało się zatwierdzić lub wycofać, wyłączamy śledzenie
		if ( $ret ) {
			$this->trxactive = false;
			$this->trxbroken = false;
			$this->trxdatabase = false;
			$this->trxinstance = 0;
			return $ret;
		}

		if ( $this->isDisconnect() ) {
			$this->close( false );
		}

		$this->trxbroken = true;
		return $ret;
	}

	/**
	 * Zwraca informację, czy bieżący obiekt realizuje w tym momencie
	 * transakcję bazodanową.
	 */
	public function isActiveTransaction()
	{
		return $this->trxactive;
	}

	/**
	 * Zwraca informację, czy bieżący obiekt realizuje w tym momencie
	 * transakcję bazodanową i jest ona oznaczona jako uszkodzona.
	 */
	public function isBrokenTransaction()
	{
		return $this->trxbroken;
	}

	/**
	 * Zwraca nazwę bazy logicznej, dla którego obiekt klasy KlimDatabase
	 * zażądał rozpoczęcia transakcji.
	 */
	public function getTransactionDatabase()
	{
		return $this->trxdatabase;
	}

	/**
	 * Zwraca numer instancji klasy KlimDatabase.
	 */
	public function getTransactionInstance()
	{
		return $this->trxinstance;
	}
}

