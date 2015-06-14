<?php
/**
 * Klasa bazowa do implementacji walidatorów
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


abstract class ValidatorBase
{

	/**
	 * Metoda zawierająca logikę walidatora. Dostaje wektor z danymi
	 * (array z kluczami numerycznymi) i zwraca false, jeśli walidacja
	 * się udała, lub tekstowy token reprezentujący treść błędu, jeśli
	 * się nie udała (dane nie spełniają założeń).
	 *
	 * Tłumaczenia tokenów na poszczególne języki znajdują się w pliku
	 * translations.php, pogrupowane po numerach krajów, zgodnie z
	 * konwencją używaną w całej aplikacji. Jeśli mechanizm walidacji
	 * nie znajdzie tłumaczenia dla danego tokena, zwróci tłumaczenie
	 * dla numeru języka 0, a jeżeli go też nie znajdzie, zwróci po
	 * prostu ten token jako treść błędu, poprzedzając go znakiem #.
	 *
	 * Dlatego też token powinien mieć postać tekstu w języku angielskim,
	 * zbliżonego do treści błędu (np. "invalid-nip-number"), z myślnikami
	 * zamiast spacji, z samymi małymi literami i nieco bardziej ogólnego.
	 */
	abstract public function check( $data );
}

