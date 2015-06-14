<?php
/**
 * Pula połączeń z bazami danych
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
 * Ta klasa implementuje prosty pseudo-pooler do połączeń z bazą danych.
 *
 * Jego zadaniem jest tłumaczenie podanej nazwy identyfikującej logiczną
 * bazę danych, z którą program chce się połączyć, na dane konfiguracyjne
 * odpowiadającego jej segmentu fizycznego, a następnie inicjalizacja
 * obiektu połączeniowego i pooling połączonych już z bazą obiektów
 * pomiędzy wywołaniami metody getConnection.
 *
 * Pooling ten działa tylko w ramach tej samej instancji skryptu PHP.
 *
 * Cały sterownik ma zaimplementowane zarządzanie transakcjami - stan
 * transakcji jest śledzony, a transakcje są automatycznie wycofywane w
 * momencie wystąpienia błędu.
 *
 * Na poziomie poolera, w momencie, gdy instancja sterownika wchodzi w stan
 * transakcyjny, przestaje być zwracana w kolejnych wywołaniach metody
 * getConnection aż do zakończenia transakcji, a zamiast niej tworzona
 * i zwracana jest kolejna instancja.
 *
 *
 * Należy zauważyć, że pooler nie grupuje cache'owanych wewnątrz obiektów
 * po nazwie logicznej bazy danych, ale po danych konfiguracyjnych segmentu
 * z nią skojarzonego (jak np. host serwera baz danych, nazwa użytkownika
 * itp.) - w efekcie dla wielu różnych baz danych może być zwracany ten sam
 * obiekt połączeniowy. Można z tego mechanizmu zrezygnować, odpowiednio
 * ustawiając zmienną $kgDbAggregate w konfiguracji.
 *
 * Należy zauważyć, że w konfiguracji poszczególnych segmentów podawany
 * jest sposób kodowania znaków diakrytycznych, natomiast komunikacja
 * ze sterownikiem odbywa się zawsze w UTF-8 (za pomocą klasy KlimProxy
 * dokonywana jest obustronna konwersja: zarówno danych wejściowych,
 * jak i zwracanych wyników).
 */
class KlimDatabasePool
{
	private static $entries = array();
	private static $connections = array();
	private static $error = false;

	public static function getConnection( $db )
	{
		self::$error = false;

		$config = self::getConfig();

		if ( !array_key_exists($db, $config) ) {
			throw new KlimApplicationException( "unknown database $db" );
		}

		$segment = $config[$db];

		$type = $segment["type"];
		$host = $segment["host"];
		$user = $segment["user"];
		$pass = $segment["pass"];
		$name = $segment["name"];
		$charset = $segment["charset"];
		$persistent = ( isset($segment["persistent"]) ? $segment["persistent"] : false );

		$obj = self::getObject( $db, $type, $host, $user, $pass, $name, $charset, $persistent );
		return $obj ? $obj : self::$error;
	}

	public static function getConfig()
	{
		if ( !empty(self::$entries) ) {
			return self::$entries;
		}

		if ( !include("config/database/database.php") ) {
			throw new KlimApplicationException( "cannot load database configuration" );
		}

		if ( !isset($config) || !is_array($config) ) {
			throw new KlimApplicationException( "invalid database configuration" );
		}

		self::$entries = $config;
		return $config;
	}

	public static function addDatabase( $db, $type, $host, $user, $pass, $name, $charset, $persistent = false )
	{
		if ( empty(self::$entries) ) {
			self::getConfig();
		}

		if ( isset(self::$entries[$db]) ) {
			throw new KlimApplicationException( "tried to overwrite database $db" );
		}

		self::$entries[$db] = array (
			"type" => $type,
			"host" => $host,
			"user" => $user,
			"pass" => $pass,
			"name" => $name,
			"charset" => $charset,
			"persistent" => $persistent,
		);
	}

	private static function getObject( $db, $type, $host, $user, $pass, $name, $charset, $persistent )
	{
		global $kgDbAggregate;

		$psig = ( $persistent ? "1" : "0" );

		/**
		 * Tutaj tworzony jest tekst agregujący obiekty połączeniowe.
		 */
		if ( $kgDbAggregate ) {
			$id = "$type:$host:$user:$name:$charset:$psig";
		} else {
			$id = "$type:$host:$user:$name:$charset:$psig:$db";
		}

		if ( isset(self::$connections[$id]) ) {
			foreach ( self::$connections[$id] as $key => $obj ) {

				/**
				 * Wariant 1: przeszukujemy tablicę instancji i znaleźliśmy
				 * obiekt z otwartym połączeniem, ale również aktywną
				 * transakcją. Szukamy więc dalej i jeśli nie znajdziemy
				 * innego bez transakcji, tworzymy nową instancję.
				 */
				if ( $obj->isOpen() ) {
					if ( !$obj->isActiveTransaction() ) {
						return $obj;
					}

				/**
				 * Wariant 2: znaleźliśmy obiekt bez otwartego połączenia,
				 * tj. taki, na którym połączenie zostało zamknięte albo
				 * na skutek błędu, albo ręcznie przez aplikację. Obiekt
				 * w takim stanie ma wyczyszczone wszystkie właściwości,
				 * zatem jest gotowy do ponownego otwarcia połączenia.
				 * Jeśli ponowne połączenie się nie uda, to nie tworzymy
				 * już nowej instancji, gdyż ona również nie będzie w
				 * stanie połączyć się z tą bazą danych.
				 */
				} else if ( $obj->connect() ) {
					return $obj;
				} else {
					self::$error = $obj->lastError();
					return false;
				}
			}
		}

		/**
		 * Wariant 1 - kontynuacja: wszystkie znalezione instancje były
		 * w stanie transakcyjnym, wobec czego tworzymy nową instancję
		 * i dołączamy ją do tablicy.
		 *
		 * Wariant 3: tablica instancji jeszcze nie istnieje. Jest więc
		 * to pierwsze wywołanie getConnection w tej instancji skryptu.
		 */
		$class = KlimDatabaseHandler::getDriverClass( $db, $type );

		$obj = new $class( $host, $user, $pass, $name, $charset, $persistent );
		$obj = new KlimProxy( $obj );

		if ( $obj->connect() ) {
			self::$connections[$id][] = $obj;
			return $obj;
		} else {
			self::$error = $obj->lastError();
			return false;
		}
	}
}

