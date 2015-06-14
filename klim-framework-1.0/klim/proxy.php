<?php
/**
 * Klasa implementująca proxy kodowania znaków dla innych klas
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
 * Proxy jest wstawiane pomiędzy obiekt, który "gada" w dowolnym
 * kodowaniu znaków, z zasady różnym od utf-8, a obiekt z niego
 * korzystający, który "gada" w utf-8.
 *
 * Obiekt podrzędny musi implementować metodę getEncoding, która
 * musi zwracać nazwę sposobu kodowania znaków w bieżącej instancji
 * (może ona być zmienna) w formacie zrozumiałym dla funkcji iconv.
 *
 * Proxy działa w ten sposób, że wrapuje obiekt podrzędny i podmienia
 * transparentnie kodowanie znaków we wszystkich możliwych sposobach
 * interakcji obiektu nadrzędnego z podrzędnym.
 *
 * http://www.jansch.nl/tag/proxy/
 */
class KlimProxy
{
	private $object;  // obiekt wewnętrzny
	private $encoding = false;  // kodowanie, jakiego on używa (false jeśli utf-8)

	public function __construct( $object, $parent_encoding = false )
	{
		$this->object = $object;

		if ( method_exists($object, "getEncoding") ) {

			$encoding = $object->getEncoding();
			if ( !empty($encoding) && strcasecmp($encoding, "utf-8") ) {
				$this->encoding = $encoding;
			}

		} else if ( $parent_encoding ) {
			$this->encoding = $parent_encoding;
		}
	}

	public function getEncoding()
	{
		return "utf-8";
	}

	private function __convert( $input, $from, $to )
	{
		if ( is_string($input) ) {
			return iconv( $from, $to . "//TRANSLIT", $input );
		}

		if ( is_array($input) ) {
			foreach ( $input as $key => $value ) {
				$input[$key] = $this->__convert( $value, $from, $to );
			}

			return $input;
		}

		if ( !is_object($input) ) {
			return $input;
		}

		$class = get_class( $input );

		if ( strpos($class, "KlimDatabaseResult") === 0 ) {
			$input->setProxyEncoding( $this->encoding );
		} else if ( $class != "KlimProxy" ) {
			$input = new KlimProxy( $input, $this->encoding );
		}

		return $input;
	}

	public function __call( $method, $args )
	{
		if ( $this->encoding ) {
			$args = $this->__convert( $args, "utf-8", $this->encoding );
		}

		$output = call_user_func_array( array($this->object, $method), $args );

		if ( $this->encoding ) {
			$output = $this->__convert( $output, $this->encoding, "utf-8" );
		}

		return $output;
	}

	public function __set( $name, $value )
	{
		if ( $this->encoding ) {
			$value = $this->__convert( $value, "utf-8", $this->encoding );
		}

		$this->object->$name = $value;
	}

	public function __get( $name )
	{
		$output = $this->object->$name;

		if ( $this->encoding ) {
			$output = $this->__convert( $output, $this->encoding, "utf-8" );
		}

		return $output;
	}
}

