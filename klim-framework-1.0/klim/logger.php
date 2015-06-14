<?php
/**
 * Klasa do obsługi logowania błędów
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


class KlimLogger
{
	const PRIORITY_ERROR = 0;
	const PRIORITY_INFO = 1;
	const PRIORITY_DEBUG = 2;

	public static function error( $facility, $message, $resource = false, $backtrace = false )
	{
		return self::route( self::PRIORITY_ERROR, $facility, $message, $resource, $backtrace );
	}

	public static function info( $facility, $message, $resource = false, $backtrace = false )
	{
		return self::route( self::PRIORITY_INFO, $facility, $message, $resource, $backtrace );
	}

	public static function debug( $facility, $message, $resource = false, $backtrace = false )
	{
		return self::route( self::PRIORITY_DEBUG, $facility, $message, $resource, $backtrace );
	}

	/**
	 * Główna metoda przetwarzająca komunikaty, odbierająca je od metod
	 * publicznych z nadanym priorytetem, oraz przepuszczająca przez
	 * serię metod wykonujących właściwy zapis do pliku i ew. na konsolę.
	 */
	protected static function route( $type, $facility, $message, $resource, $backtrace )
	{
		if ( $type === self::PRIORITY_DEBUG && Server::isProd() ) {
			return true;
		}

		if ( $backtrace === false ) {
			$backtrace = debug_backtrace();
			$index = 2;
		} else {
			$index = 1;
		}

		$caller = LogUtils::decodeBacktrace( $backtrace, $index );

		$message = preg_replace( "/\s+/", " ", $message );

		if ( $resource ) {
			$resource = trim( $resource );
		}

		$ret = self::writeFile( $type, $facility, $caller, $message, $resource );

		if ( $ret ) {
			return true;
		} if ( php_sapi_name() === "cli" ) {
			return self::writeConsole( $type, $facility, $caller, $message, $resource );
		} else {
			return false;
		}
	}

	/**
	 * Zapisuje komunikat w lokalnym pliku. Na początku każdej linii
	 * dodawany jest znacznik czasu, nazwa skryptu głównego i jego PID.
	 */
	protected static function writeFile( $type, $facility, $caller, $message, $resource )
	{
		static $pid;
		if ( !isset($pid) ) {
			$pid = getmypid();
		}

		$date = date( "Y-m-d H:i:s" );
		$script = Environment::getScript();
		$entry = "$date $script $pid $caller $message\n";

		if ( !empty($resource) ) {
			$lines = explode( "\n", $resource );
			foreach ( $lines as $line ) {
				$entry .= "> $line\n";
			}
		}

		return LogUtils::save( $facility, $entry );
	}

	protected static function writeConsole( $type, $facility, $caller, $message, $resource )
	{
		$date = date( "Y-m-d H:i:s" );
		echo "[$date $facility $caller] $message\n$resource\n";
		return true;
	}
}

