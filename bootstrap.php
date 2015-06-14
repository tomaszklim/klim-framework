<?php
/**
 * Application bootstrap code
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


class Environment
{
	protected static $path = false;

	protected static function isAbsolutePath( $path )
	{
		return !empty($path) && strpos( trim($path), "/" ) === 0;
	}

	/**
	 * TODO: testy z mod_rewrite
	 */
	public static function getScript()
	{
		if ( empty(self::$path) ) {
			$tmp = false;
			if ( !empty($_SERVER["DOCUMENT_ROOT"]) ) {
				if ( !empty($_SERVER["SCRIPT_NAME"]) ) {
					$tmp = $_SERVER["DOCUMENT_ROOT"]."/".$_SERVER["SCRIPT_NAME"];
				} else if ( !empty($_SERVER["PHP_SELF"]) ) {
					$tmp = $_SERVER["DOCUMENT_ROOT"]."/".$_SERVER["PHP_SELF"];
				} else if ( !empty($_SERVER["REQUEST_URI"]) ) {
					$tmp = $_SERVER["DOCUMENT_ROOT"]."/".$_SERVER["REQUEST_URI"];
				}
			}

			if ( !$tmp ) {
				if ( self::isAbsolutePath($_SERVER["SCRIPT_NAME"]) ) {
					$tmp = $_SERVER["SCRIPT_NAME"];
				} else if ( self::isAbsolutePath($_SERVER["PHP_SELF"]) ) {
					$tmp = $_SERVER["PHP_SELF"];
				} else if ( self::isAbsolutePath($_SERVER["SCRIPT_FILENAME"]) ) {
					$tmp = $_SERVER["SCRIPT_FILENAME"];
				} else if ( self::isAbsolutePath($_SERVER["PATH_TRANSLATED"]) ) {
					$tmp = $_SERVER["PATH_TRANSLATED"];
				} else if ( !empty($_SERVER["PWD"]) ) {

					if ( !empty($_SERVER["SCRIPT_NAME"]) ) {
						$tmp = $_SERVER["PWD"]."/".$_SERVER["SCRIPT_NAME"];
					} else if ( !empty($_SERVER["PHP_SELF"]) ) {
						$tmp = $_SERVER["PWD"]."/".$_SERVER["PHP_SELF"];
					} else if ( !empty($_SERVER["SCRIPT_FILENAME"]) ) {
						$tmp = $_SERVER["PWD"]."/".$_SERVER["SCRIPT_FILENAME"];
					} else if ( !empty($_SERVER["PATH_TRANSLATED"]) ) {
						$tmp = $_SERVER["PWD"]."/".$_SERVER["PATH_TRANSLATED"];
					}
				}
			}

			self::$path = realpath( $tmp );
		}
		return self::$path;
	}

	/**
	 * Umożliwia nadpisanie nazwy bieżącego skryptu na potrzeby
	 * testów jednostkowych (pod PHPUnit nie działa $_SERVER).
	 */
	public function setScript( $script )
	{
		self::$path = $script;
	}
}


class LogUtils
{
	public static function decodeBacktrace( $backtrace, $index )
	{
		if ( !isset($backtrace[$index]) ) {
			return basename( Environment::getScript() );
		}

		$uplevel = array( "require", "require_once", "include", "include_once" );
		while ( in_array($backtrace[$index]["function"], $uplevel) && !empty($backtrace[$index+1]["function"]) ) {
			$index++;
		}

		while ( $backtrace[$index]["function"] == "__construct" && strpos($backtrace[$index]["class"], "Exception") !== false && !empty($backtrace[$index+1]["function"]) ) {
			$index++;
		}

		if ( isset($backtrace[$index]["class"]) ) {
			return $backtrace[$index]["class"] . "::" . $backtrace[$index]["function"];
		} else {
			return $backtrace[$index]["function"];
		}
	}

	public static function save( $facility, $entry )
	{
		$log = "/var/log/php/$facility.log";
		return file_put_contents( $log, $entry, FILE_APPEND | LOCK_EX );
	}
}


class BootstrapException extends Exception
{
	protected $facility = "bootstrap";

	public function __construct( $message = "" )
	{
		parent::__construct( $message );

		$pid = getmypid();
		$date = date( "Y-m-d H:i:s" );
		$script = Environment::getScript();

		$backtrace = debug_backtrace();
		$index = 2;
		$caller = LogUtils::decodeBacktrace( $backtrace, $index );

		$entry = "$date $script $pid $caller $message\n";
		LogUtils::save( $this->facility, $entry );

		if ( php_sapi_name() !== "cli" ) {
			$hdr = "500 Internal Server Error";
			@header( "HTTP/1.0 $hdr" );
			@header( "Status: $hdr" );
		}
	}
}


class Bootstrap
{
	private static $includes = array( "." );
	private static $root = false;

	private static $paths = array (
		"/app/backupsvc",
		"/app/fajne",
		"/app/klimbs",
		"/app/mailfilter",
		"/app/rewriter",
	);

	public static function run()
	{
		if ( empty(self::$root) ) {
			self::$root = self::detectInstanceRoot();
			self::$includes[] = self::$root."/include";
			self::setIncludePath();

			if ( php_sapi_name() === "cli" ) {
				set_time_limit(0);
			}
		}
	}

	public static function getInstanceRoot()
	{
		return self::$root;
	}

	public static function getApplicationRoot()
	{
		return dirname(dirname(self::$root));
	}

	public static function addLibrary( $name, $path = false )
	{
		if (!$path) {
			$path = self::getApplicationRoot() . "/libs/";
		}

		$full = $path.$name;

		if ( !in_array($full, self::$includes, true) ) {
			self::$includes[] = $full;
		}

		self::setIncludePath();
	}

	private static function setIncludePath()
	{
		ini_set( "include_path", implode(":", self::$includes) );
	}

	private static function detectInstanceRoot()
	{
		$script = Environment::getScript();

		foreach ( self::$paths as $path ) {
			if ( preg_match("#^$path/([a-zA-Z0-9-_.]+)/#", $script, $matches) ) {
				return $path."/".$matches[1];
			}
		}

		throw new BootstrapException( "could not recognize application instance" );
	}
}


class Server
{
	private static $type = false;

	public static function setup()
	{
		if ( empty(self::$type) ) {
			$file = "/etc/environment-type";
			$data = @file_get_contents( $file );

			if ( $data === false ) {
				throw new BootstrapException( "cannot load file $file" );
			}

			$accepted = array( "dev", "test", "prod" );
			$type = trim( $data );

			if ( !in_array($type, $accepted, true) ) {
				throw new BootstrapException( "unknown environment type, aborting script" );
			}

			self::$type = $type;
		}
	}

	public static function isDev()
	{
		self::setup();
		return self::$type === "dev";
	}

	public static function isTest()
	{
		self::setup();
		return self::$type === "test";
	}

	public static function isProd()
	{
		self::setup();
		return self::$type === "prod";
	}

	public static function getType()
	{
		self::setup();
		return self::$type;
	}

	public static function getHostname()
	{
		static $host = false;
		if ( $host === false ) {
			$uname = @posix_uname();
			$host = $uname["nodename"];
		}
		return $host;
	}
}

