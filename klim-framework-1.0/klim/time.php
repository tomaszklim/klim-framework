<?php
/**
 * Time and date related functions, taken from MediaWiki code and expanded
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
 * @author Tim Starling <tstarling@wikimedia.org>
 * @author Tomasz Klim <framework@tomaszklim.com>
 * @license http://www.gnu.org/copyleft/gpl.html
 */


define( "GMT_UNIX",      0 );  /** Unix time - the number of seconds since 1970-01-01 00:00:00 UTC */
define( "GMT_MW",        1 );  /** MediaWiki concatenated string timestamp (YYYYMMDDHHMMSS) */
define( "GMT_DB",        2 );  /** MySQL DATETIME (YYYY-MM-DD HH:MM:SS) */
define( "GMT_RFC2822",   3 );  /** RFC 2822 format, for E-mail and HTTP headers */
define( "GMT_ISO_8601",  4 );  /** ISO 8601 format with no timezone: 1986-02-09T20:00:00Z */
define( "GMT_EXIF",      5 );  /** Exif timestamp (YYYY:MM:DD HH:MM:SS) */
define( "GMT_ORACLE",    6 );  /** Oracle format time */
define( "GMT_POSTGRES",  7 );  /** Postgres format time */
define( "GMT_DB2",       8 );  /** DB2 format time */
define( "LOCAL_RFC2822", 9 );  /** RFC 2822 format, in local timezone */


class KlimTime
{
	/**
	 * Metoda zwracająca bieżący czas łącznie z mikrosekundami
	 * w postaci liczby zmiennoprzecinkowej.
	 */
	public static function getNow()
	{
		list($usec, $sec) = explode(" ", microtime());
		return (float)$usec + (float)$sec;
	}

	/**
	 * Metoda przepisująca podaną datę na podany format. Parsuje zmienną
	 * z datą, próbując dopasować jej zawartość do jednego ze znanych
	 * formatów zapisu daty i czasu. Jeśli się uda, generuje datę w podanym
	 * formacie. Jeśli nie, zwraca false i loguje komunikat błędu.
	 */
	public static function getTimestamp( $outputtype = GMT_UNIX, $ts = 0 )
	{
		$uts = 0;
		$da = array();

		if ($ts === 0) {
			$uts = time();
		} elseif (preg_match('/^(\d{4})\-(\d\d)\-(\d\d) (\d\d):(\d\d):(\d\d)$/D',$ts,$da)) {
			// GMT_DB
		} elseif (preg_match('/^(\d{4}):(\d\d):(\d\d) (\d\d):(\d\d):(\d\d)$/D',$ts,$da)) {
			// GMT_EXIF
		} elseif (preg_match('/^(\d{4})(\d\d)(\d\d)(\d\d)(\d\d)(\d\d)$/D',$ts,$da)) {
			// GMT_MW
		} elseif (preg_match('/^\d{1,13}$/D',$ts)) {
			// GMT_UNIX
			$uts = $ts;
		} elseif (preg_match('/^(\d{2})-(\w{3})-(\d\d(\d\d)?) (\d\d)\.(\d\d)\.(\d\d)/', $ts)) {
			// GMT_ORACLE (old)
			$uts = strtotime(preg_replace('/(\d\d)\.(\d\d)\.(\d\d)(\.(\d+))?/', "$1:$2:$3",
					str_replace("+00:00", "UTC", $ts)));
		} elseif (preg_match('/^\d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2}.\d{6}$/', $ts)) {
			// GMT_ORACLE (new)
			// session altered to DD-MM-YYYY HH24:MI:SS.FF6
			$uts = strtotime(preg_replace('/(\d\d)\.(\d\d)\.(\d\d)(\.(\d+))?/', "$1:$2:$3",
					str_replace("+00:00", "UTC", $ts)));
		} elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.*\d*)?Z$/', $ts, $da)) {
			// GMT_ISO_8601
		} elseif (preg_match('/^(\d{4})\-(\d\d)\-(\d\d) (\d\d):(\d\d):(\d\d)\.*\d*[\+\- ](\d\d)$/',$ts,$da)) {
			// GMT_POSTGRES
		} elseif (preg_match('/^(\d{4})\-(\d\d)\-(\d\d) (\d\d):(\d\d):(\d\d)\.*\d* GMT$/',$ts,$da)) {
			// GMT_POSTGRES
		} elseif (preg_match('/^(\w{3}),\s{1,2}(\d{1,2}) (\w{3}) (\d{4}) (\d\d):(\d\d):(\d\d) GMT$/',$ts)) {
			// GMT_RFC2822
			$uts = strtotime(substr($ts, 5));
		} elseif (preg_match('/^(\w{3}),\s{1,2}(\d{1,2}) (\w{3}) (\d{4})\s{1,2}(\d{1,2}):(\d\d):(\d\d) [+|-](\d{4})$/',$ts)) {
			// GMT_RFC2822 + timezone
			$uts = strtotime(substr($ts, 5));
		} elseif (preg_match('/^(\w{3}),\s{1,2}(\d{1,2}) (\w{3}) (\d{4})\s{1,2}(\d{1,2}):(\d\d):(\d\d) [+|-](\d{4}) \(/',$ts)) {
			// GMT_RFC2822 + timezone + timezone description
			$sub = substr($ts, 5, strpos($ts, "(") - 6);
			$uts = strtotime(trim($sub));
		} elseif (preg_match('/^(\w{3}),\s{1,2}(\d{1,2}) (\w{3}) (\d{4}) (\d\d):(\d\d):(\d\d)$/',$ts)) {
			// GMT_RFC2822 without any timezone specifier
			$uts = strtotime(substr($ts, 5));
		} elseif (preg_match('/^(\d{1,2}) (\w{3}) (\d{4})\s{1,2}(\d{1,2}):(\d\d):(\d\d) [+|-](\d{4})$/',$ts)) {
			// GMT_RFC2822 + timezone, incomplete: without day of week
			$uts = strtotime($ts);
		} elseif (preg_match('/^(\w{3}) (\w{3}) (\d{1,2}) (\d{1,2}):(\d\d):(\d\d) (\w{3,5}) (\d{4})$/',$ts)) {
			// Mon Apr 22 10:42:25 CEST 2013
			$uts = strtotime($ts);
		} elseif (preg_match('/^(\d\d)\/(\d\d)\/(\d\d) (\d\d):(\d\d):(\d\d)$/D',$ts,$da)) {
			// mysql 2-digit year (02/05/08 11:46:37 -> 2008-02-05)
			$uts = gmmktime((int)$da[4],(int)$da[5],(int)$da[6],
							(int)$da[1],(int)$da[2],(int)$da[3]);
			$da = array();
		} elseif (preg_match('/^(\d\d)\-(\d\d)\-(\d\d)$/D',$ts,$da)) {
			// excel 2-digit year without time (12-27-08 -> 2008-12-27)
			$uts = gmmktime(0,0,0,(int)$da[1],(int)$da[2],(int)$da[3]);
			$da = array();
		} elseif (preg_match('/^(\d{4})\-(\d\d)\-(\d\d)$/',$ts,$da) || preg_match('/^(\d{4})\/(\d\d)\/(\d\d)$/',$ts,$da)) {
			// generic date without time, assume midnight
			$da[4] = $da[5] = $da[6] = 0;
		} else {
			// original behavior was fall back to the epoch ($uts=0)
			throw new KlimRuntimeException( "bogus time value: $outputtype; $ts" );
		}

		if ( !empty($da) ) {
			// Warning! gmmktime() acts oddly if the month or day is set to 0
			// We may want to handle that explicitly at some point
			$uts = gmmktime((int)$da[4],(int)$da[5],(int)$da[6],
							(int)$da[2],(int)$da[3],(int)$da[1]);
		}

		if ( !is_numeric($outputtype) ) {
			throw new KlimApplicationException( "illegal timestamp output type" );
		}

		switch ( $outputtype ) {
			case GMT_UNIX:
				return $uts;
			case GMT_MW:
				return gmdate( 'YmdHis', $uts );
			case GMT_DB:
				return gmdate( 'Y-m-d H:i:s', $uts );
			case GMT_ISO_8601:
				return gmdate( 'Y-m-d\TH:i:s\Z', $uts );
			// This shouldn't ever be used, but is included for completeness
			case GMT_EXIF:
				return gmdate( 'Y:m:d H:i:s', $uts );
			case GMT_RFC2822:
				return gmdate( 'D, d M Y H:i:s', $uts ) . ' GMT';
			case GMT_ORACLE:
				return gmdate( 'd-m-Y H:i:s.000000', $uts);
				//return gmdate( 'd-M-y h.i.s A', $uts ) . ' +00:00';
			case GMT_POSTGRES:
				return gmdate( 'Y-m-d H:i:s', $uts ) . ' GMT';
			case GMT_DB2:
				return gmdate( 'Y-m-d H:i:s', $uts );
			case LOCAL_RFC2822:
				return date( 'D, d M Y H:i:s O', $uts );
			default:
				throw new KlimApplicationException( "illegal timestamp output type: $outputtype" );
		}
	}
}

