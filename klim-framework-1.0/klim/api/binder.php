<?php
/**
 * Klasa spinająca wywołania metod API z różnych klas klienckich w jedną klasę
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


class KlimApiBinder
{
	protected $context;
	protected $instances = array();
	protected $binding = array();

	public function __construct( $context )
	{
		$this->context = $context;
	}

	protected function getObject( $class )
	{
		if ( !isset($this->instances[$class]) ) {
			$file = str_replace( "_", "/", KlimCamel::decode($class) );
			require_once "$file.php";

			$obj = new $class( $this->context );
			$this->instances[$class] = $obj;
		} else {
			$obj = $this->instances[$class];
		}

		return $obj;
	}

	public function __call( $method, $args )
	{
		if ( !isset($this->binding[$method]) ) {
			throw new KlimApplicationException( "unknown method $method" );
		}

		$obj = $this->getObject( $this->binding[$method] );

		return call_user_func_array( array($obj, $method), $args );
	}
}

