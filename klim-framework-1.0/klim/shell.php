<?php
/**
 * Funkcje globalne związane z uruchamianiem programów zewnętrznych
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


class KlimShell
{
	private static $done = false;

	/**
	 * Workaround for http://bugs.php.net/bug.php?id=45132
	 * escapeshellarg() destroys non-ASCII characters if LANG is not a UTF-8 locale
	 */
	private static function init()
	{
		if ( self::$done === false ) {
			self::$done = true;
			$locale = "en_US.utf8";
			putenv( "LC_CTYPE=$locale" );
			setlocale( LC_CTYPE, $locale );
		}
	}

	public static function execute( $cmd )
	{
		self::init();

		$cmd .= " 2>&1";
		$retval = 1; // error by default?
		ob_start();
		passthru( $cmd, $retval );
		$output = ob_get_contents();
		ob_end_clean();

		switch ( $retval )
		{
			case 0:
				return $output;
			case 127:
				throw new KlimRuntimeException( "possibly missing executable file", $cmd );
			default:
				throw new KlimRuntimeException( "error executing file", $cmd );
		}
	}

	public static function escape( $arg )
	{
		self::init();
		return escapeshellarg( $arg );
	}

	public static function processExists( $process_id, $mask = false )
	{
		$pid = (int)$process_id;
		$ps = shell_exec( "ps p $pid" );
		$ps = explode( "\n", $ps );

		if ( count($ps) < 3 ) {
			return false;
		} else if ( $mask && strpos($ps[1], $mask) === false ) {
			return false;
		} else {
			return true;
		}
	}
}

