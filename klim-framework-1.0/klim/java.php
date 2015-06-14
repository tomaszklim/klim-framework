<?php
/**
 * Wrapper do uruchamiania zewnętrznych programów w Javie
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


class KlimJava
{
	private $classpath;

	/**
	 * Tablica wymaganych archiwów JAR, leżących wewnątrz aplikacji, w
	 * podkatalogu /java/lib. Należy ją nadpisywać w klasach potomnych,
	 * aby dodać JARy, od których jest zależna nasza aplikacja.
	 */
	protected $jars = array();

	/**
	 * Tablica wymaganych archiwów JAR, leżących poza naszą aplikacją,
	 * np. w drzewie katalogów serwera aplikacji Javy, z którym się
	 * komunikuje nasza aplikacja.
	 *
	 * W tej tablicy należy podawać pełne ścieżki do plików, a nie tylko
	 * same nazwy. Należy unikać używania JARów spoza aplikacji - jest to
	 * możliwe, ale odradzane ze względu na możliwe konflikty wersji itp.
	 */
	protected $jarsExternal = array();

	/**
	 * Konstruktor przygotowuje do ustawienia zmienną środowiskową
	 * CLASSPATH - ustawianie musi być ponawiane przy każdym uruchomieniu
	 * programu zewnętrznego, gdyż inna instancja klasy potomnej mogła
	 * wpisać do niej inną zawartość, natomiast przygotować można tą
	 * zawartość tylko raz.
	 */
	public function __construct()
	{
		$root = Bootstrap::getInstanceRoot();
		$jars = $this->jarsExternal;
		foreach ( $this->jars as $jar ) {
			$jars[] = "$root/java/lib/$jar";
		}
		$jars[] = "$root/java/src";

		$this->classpath = implode( ":", $jars );
	}

	/**
	 * Uruchamia klasę z ustalonym wcześniej zestawem plików JAR, oraz
	 * podanym zestawem parametrów. Metoda ta powinna być uruchamiania
	 * z poziomu metody implementującej logikę biznesową, w ramach klasy
	 * nadrzędnej. Nadrzędna metoda jest wówczas odpowiedzialna za
	 * parsowanie wyniku (przechwyconego stdout Javy) zwróconego przez
	 * niniejszą metodę.
	 */
	protected function execute( $class, $params = array() )
	{
		if ( !empty($params) ) {
			foreach ( $params as $param ) {
				$params2[] = KlimShell::escape( $param );
			}

			$line = implode( " ", $params2 );
		} else {
			$line = "";
		}

		putenv( "CLASSPATH=$this->classpath" );
		return KlimShell::execute( "java $class $line" );
	}

	/**
	 * Rekompiluje klasę z ustaloną zmienną CLASSPATH. Do poprawnego
	 * działania wymaga zainstalowanego pełnego pakietu Sun JDK.
	 */
	public function recompile( $class )
	{
		$parts = explode( ".", $class );

		$dir_parts = array_slice( $parts, 0, -1 );
		$dir = implode( "/", $dir_parts );

		$root = Bootstrap::getInstanceRoot();
		@chdir( "$root/java/src/$dir" );

		$class_parts = array_slice( $parts, -1 );
		$class_name = $class_parts[0];

		putenv( "CLASSPATH=$this->classpath" );
		return KlimShell::execute( "javac $class_name.java" );
	}
}

