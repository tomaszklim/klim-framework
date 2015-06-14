<?php
/**
 * Plik ładujący
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
 * Flaga, czy agregować połączenia do różnych logicznych baz danych,
 * jeśli odwołują się one do tych samych segmentów fizycznych.
 *
 * Agregacja jest wyłączana na czas wykonywania na połączeniu transakcji,
 * zatem wyłączenie tej flagi może mieć sens co najwyżej wydajnościowy.
 */
$kgDbAggregate = true;



if ( !function_exists("set_bin_value") ) {
	function set_bin_value( $mask, $bit ) {
		return $mask | $bit;
	}
}

if ( !function_exists("is_set_bin_value") ) {
	function is_set_bin_value( $mask, $bit ) {
		return $mask & $bit;
	}
}

if ( !function_exists("unset_bin_value") ) {
	function unset_bin_value( $mask, $bit ) {
		return $mask & ~ $bit;
	}
}


function __autoload_klim_framework( $class ) {
	if ( strpos($class, "Klim") === 0 ) {
		$file = KlimCamel::decode( $class );
		$path = str_replace( "_", "/", $file );
		return include_once( "$path.php" );
	}
	return false;
}


require_once "klim/camel.php";
require_once "klim/exception.php";
require_once "klim/time.php";  // define
require_once "klim/database.php";  // define
spl_autoload_register( "__autoload_klim_framework" );

