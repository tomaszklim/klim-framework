<?php
/**
 * Klasa główna do walidacji danych
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
 * $v = new KlimValidator();
 * $v->register( "myservice.username", $POST["login"], $COUNTRY_ID );  <- wszędzie dynamiczna lista argumentów, min. 1 sztuka
 * $v->register( "myservice.maxwordlength", $POST["about_me"], 12 );
 * $v->register( "password.equals", $POST["pass1"], $POST["pass2"] );
 * $v->register( "password.strength", $POST["pass1"], $COUNTRY_ID );
 * $v->register( "official.polish.nip", $POST["nip"] );
 * $v->execute();    <- tu następuje przetwarzanie
 * $r = $v->isValid();
 * $x = $v->getWarnings( $COUNTRY_ID );   <- tutaj język ostrzeżeń, wcześniej kraj był podawany jako element reguły walidacji
 */
class KlimValidator
{
	protected $chain = array();
	protected $warnings = array();

	protected function warn( $token )
	{
		$this->warnings[] = $token;
	}

	protected function loadTranslations()
	{
		if ( !@include("validator/translations.php") ) {
			throw new KlimApplicationException( "cannot load validator translations" );
		}

		if ( !isset($translations) || !is_array($translations) ) {
			throw new KlimApplicationException( "invalid validator translations file" );
		}

		return $translations;
	}

	protected static function loadClass( $class )
	{
		if ( strpos($class, "Validator") === 0 ) {
			$file = KlimCamel::decode( $class );
			$path = str_replace( "_", "/", $file );
			return @include_once( "$path.php" );
		}
		return false;
	}

	public function __construct()
	{
		spl_autoload_register( array("KlimValidator", "loadClass") );
	}

	/**
	 * Rejestruje nową regułę walidacji.
	 */
	public function register( $rawclass )
	{
		$params = func_get_args();
		array_shift( $params );
		$this->chain[$rawclass][] = $params;
	}

	/**
	 * Wykonuje właściwą walidację - jest to osobna metoda, aby
	 * przerobić wszystkie reguły za jednym razem.
	 */
	public function execute()
	{
		foreach ( $this->chain as $rawclass => $chain ) {
			foreach ( $chain as $index => $data ) {

				$raw = str_replace( ".", "_", "validator.$rawclass" );
				$class = KlimCamel::encode( $raw, true );

				is_callable( array($class, "check") );

				if ( !class_exists($class) ) {
					throw new KlimApplicationException( "invalid validator $rawclass" );
				}

				$obj = new $class();
				$ret = $obj->check( $data );

				if ( $ret !== false ) {
					$this->warn( $ret );
				}

				unset( $obj );
			}
		}
	}

	/**
	 * Zwraca true, jeśli walidacja wszystkich danych się powiodła,
	 * lub false, jeśli wystąpił błąd przy co najmniej jednej regule.
	 */
	public function isValid()
	{
		return empty( $this->warnings );
	}

	/**
	 * Zwraca arraya z listą komunikatów błędów walidacji w podanym języku.
	 */
	public function getWarnings( $language )
	{
		$translations = $this->loadTranslations();
		$out = array();

		foreach ( $this->warnings as $token ) {
			$p1 = false;
			$p2 = false;

			if ( is_array($token) ) {
				if (count($token) > 2) $p2 = $token[2];
				if (count($token) > 1) $p1 = $token[1];
				$token = $token[0];
			}

			if ( !empty($translations[$language][$token]) ) {
				$tmp = $translations[$language][$token];
			} else if ( !empty($translations[0][$token]) ) {
				$tmp = $translations[0][$token];
			} else {
				$tmp = "#$token";
			}

			if ($p1 !== false) $tmp = str_replace( "$1", $p1, $tmp );
			if ($p2 !== false) $tmp = str_replace( "$2", $p2, $tmp );

			$out[] = $tmp;
		}

		return $out;
	}
}

