<?php
/**
 * Klasa implementująca operacje na linkach wyciąganych z kodu HTML
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


class KlimApiParserLinker
{

	/**
	 * Rekonstruuje nazwę hosta wraz z portem i protokołem na podstawie
	 * tablicy utworzonej funkcją parse_url(). Stara się pominąć port,
	 * jeśli nie jest on konieczny.
	 */
	public static function hostname( $parts )
	{
		$host = $parts["host"];
		$port = @$parts["port"];
		$scheme = $parts["scheme"];

		if ( ($scheme == "https" && $port == "443") || $port == "80" || $port == "" ) {
			return "$scheme://$host";
		} else {
			return "$scheme://$host:$port";
		}
	}

	/**
	 * Usuwa z urla informację o pliku, oraz ewentualne zmienne.
	 * Zwraca pola używane później przez parser:
	 * - bieżący protokół, host i ewentualny port
	 * - nadrzędny url, możliwy do złożenia z nową ścieżką względną
	 */
	public static function parentUrl( $url )
	{
		$parts = parse_url( $url );

		/**
		 * http://www.wikia.com/wiki/Main_Page?oldid=1 -> /wiki/Main_Page
		 */
		$path = @$parts["path"];

		/**
		 * /wiki/Main_Page -> /wiki/  (wycinamy nazwę pliku)
		 */
		if ( false !== ($pos = strrpos($path, "/")) ) {
			$path = substr( $path, 0, $pos + 1 );
		}

		/**
		 * host => http://www.wikia.com
		 * path => /wiki/
		 */
		return array( "host" => self::hostname($parts), "path" => $path );
	}

	/**
	 * Dekoduje i zamienia podany link względny o nieznanej postaci
	 * na link bezwzględny (w oparciu o podany link bazowy).
	 */
	public static function absoluteUrl( $parent, $url, $parts = false )
	{
		if ( !$parts ) {
			$parts = @parse_url( $url );
		}

		/**
		 * Jeśli link zaczyna się od ścieżki, np. /wiki/Main_Page,
		 * doklejamy mu protokół, host i port, tworząc kompletny url.
		 *
		 * Jeśli link jest tylko względną nazwą pliku, łączymy go
		 * z urlem bazowym, tworząc również kompletny url.
		 *
		 * Ostatni warunek stworzony jest pod strony z tagiem base href,
		 * w którego zawartości podana jest ścieżka wraz z plikiem, a
		 * nie tylko sam katalog bazowy, np.
		 *
		 *   http://www.europe.northsails.corsair.pl/swiss.htm
		 */
		if ( substr($url, 0, 1) == "/" ) {

			$url = $parent["host"] . $url;

		} else if ( empty($parts["scheme"]) ) {

			if ( substr($parent["path"], -1) == "/" ) {
				$url = $parent["host"] . $parent["path"] . $url;
			} else {
				$url = $parent["host"] . $parent["path"] . "/" . $url;
			}
		}

		/**
		 * Ponownie dzielimy złożony przed chwilą url na części, aby
		 * móc go lepiej przefiltrować (powyższa technika łączenia
		 * jest niedoskonała, szczególnie drugi przypadek).
		 */
		$parts = parse_url( $url );

		/**
		 * http://www.wikia.com/wiki/Main_Page?oldid=1 -> /wiki/Main_Page
		 */
		$path = empty($parts["path"]) ? "/" : $parts["path"];

		/**
		 * Filtrujemy powtórzenia slashy ("///")
		 */
		$path = preg_replace( "/\/+/", "/", $path );

		/**
		 * Filtrujemy niepotrzebny znak zapytania (zdarza się, że występuje
		 * bez jakichkolwiek dalszych parametrów).
		 */
		$query = empty($parts["query"]) ? "" : "?" . $parts["query"];

		/**
		 * Rekonstruujemy ostatecznego urla (będzie on jeszcze obrabiany
		 * przez metodę fixUrl(), ale na razie możemy przyjąć, że jest on
		 * w ostatecznej postaci).
		 */
		return self::hostname($parts) . $path . $query;
	}

	/**
	 * Dzieli podany link na 2 części:
	 * - link z obciętymi parametrami GET
	 * - tablicę sparsowanych parametrów GET
	 */
	public static function extractGetParams( $url )
	{
		$parts = parse_url( $url );

		if ( empty($parts["query"]) ) {
			return array( $url, array() );
		}

		$getarr = array();
		$query = trim( $parts["query"], "?&" );

		parse_str( $query, $getarr );

		$url = substr( $url, 0, strpos($url, "?") );
		return array( $url, $getarr );
	}
}

