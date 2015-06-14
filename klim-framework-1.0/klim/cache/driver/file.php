<?php
/**
 * Cache do danych oparty o nieakcelerowany storage plikowy
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


class KlimCacheDriverFile extends KlimCache
{
	protected $dir;    // root cache directory
	protected $level;  // compression level (1-9) or 0

	const COMPRESSED = 1;
	const SERIALIZED = 2;

	public function __construct( $segment )
	{
		if ( empty($segment["directory"]) || !is_numeric($segment["compress_level"]) ) {
			throw new KlimApplicationException( "missing or invalid configuration settings" );
		}

		if ( !$this->prepareDirectory($segment["directory"]) ) {
			throw new KlimRuntimeException( "cannot create cache root directory" );
		}

		$this->dir = $segment["directory"];
		$this->level = $segment["compress_level"];
	}

	protected function rawGet( $key )
	{
		return false;
	}

	protected function rawSet( $key, $value, $period )
	{
		return false;
	}

	protected function key( $key )
	{
		$md5 = md5( $key );
		$key = preg_replace( "#^http(s)?://#", "", $key );
		$key = preg_replace( "#[^a-zA-Z0-9._-]#", "_", $key );
		return $this->dir . "/" .
			substr( $md5, 0, 1 ) . "/" .
			substr( $md5, 0, 2 ) . "/" .
			substr( $md5, 0, 3 ) . "/" .
			substr( $md5, 0, 4 ) . "/" .
			substr( urlencode($key), 0, 127 );
	}

	public function get( $key )
	{
		$key = $this->key( $key );

		if ( file_exists($key) && $fp = fopen($key, "r") ) {
			flock( $fp, LOCK_SH );
			$data = fread( $fp, filesize($key) );
			fclose( $fp );

			$header = substr( $data, 0, 14 );
			list($expiry, $version, $state) = explode( " ", $header );

			/** remove expired keys */
			if ( intval($expiry) < time() ) {
				unlink( $key );
				return null;
			}

			$state = intval( $state );
			$value = substr( $data, 15 );

			if ( $state & self::COMPRESSED ) {
				$value = gzinflate( $value );
			}

			if ( $state & self::SERIALIZED ) {
				$value = unserialize( $value );
			}

			return $value;
		}

		return null;
	}

	public function set( $key, $value, $period )
	{
		$key = $this->key( $key );
		$state = 0;

		if ( !is_scalar($value) ) {
			$value = serialize( $value );
			$state |= self::SERIALIZED;
		}

		if ( $this->level >= 1 && $this->level <= 9 ) {
			$value = gzdeflate( $value, $this->level );
			$state |= self::COMPRESSED;
		}

		if ( $fp = fopen($key, "w") ) {
			$mask = "%010u %01u %01u ";
			$time = time() + $period;
			$version = 1;
			$data = sprintf( $mask, $time, $version, $state ) . $value;

			flock( $fp, LOCK_EX );
			fwrite( $fp, $data );
			fclose( $fp );
			return true;
		}

		return false;
	}

	protected function rawDelete( $key )
	{
		return file_exists($key) ? unlink($key) : true;
	}

	public function clean()
	{
		return $this->cleanExpiredFiles( $this->dir );
	}

	protected function cleanExpiredFiles( $dirname )
	{
		$status = true;
		$handle = @opendir( $dirname );

		/**
		 * Cache root directory doesn't exist or is inaccessible.
		 */
		if ( $handle === false ) {
			return false;
		}

		while ( false !== ($file = @readdir($handle)) ) {
			if ( $file != "." && $file != ".." ) {

				$path = "$dirname/$file";
				$ret = true;

				if ( is_dir($path) ) {
					$ret = $this->cleanExpiredFiles( $path );
				} else {
					$fp = @fopen( $path, "r" );

					/**
					 * File is inaccessible - assume that other files
					 * too, and simply return value indicating error.
					 */
					if ( $fp === false ) {
						return false;
					}

					flock( $fp, LOCK_SH );
					$header = fread( $fp, 14 );
					fclose( $fp );
					list($expiry, $version, $state) = explode( " ", $header );

					if ( intval($expiry) < time() ) {
						$ret = unlink( $path );
					}
				}

				if ( !$ret ) {
					$status = false;
				}
			}
		}

		closedir( $handle );
		return $status;
	}

	/**
	 * Przygotowuje cache plikowy do działania - tworzy strukturę katalogów,
	 * w której następnie zapisywane będą pliki z danymi. Struktura ta
	 * składa się z ponad 70.000 katalogów w na poziomach zagłębień. Jej
	 * czyszczenie jest z tego względu problematyczne - wydajniej jest je
	 * robić poprzez zmianę nazwy głównego katalogu cache tak, aby inna
	 * instancja klasy utworzyła nową strukturę, po czym usunięcie starej
	 * struktury, gdy system jest mniej obciążony.
	 */
	protected function prepareDirectory( $dirname )
	{
		if ( !file_exists($dirname) ) {

			/**
			 * Directory creation can fail, if current user don't have write rights
			 * for parent directory, or if parent directory doesn't exist. However
			 * if this directory will be succesfully created, there shouldn't be any
			 * problems with its subdirectories, so don't check for errors below.
			 */
			if ( @mkdir($dirname, 0700) === false ) {
				return false;
			}

			for ( $a = 0; $a <= 15; $a++ ) {

				$ha = dechex($a);
				$aa = $dirname . "/" . $ha;
				mkdir( $aa, 0700 );
				for ( $b = 0; $b <= 15; $b++ ) {

					$hb = dechex($b);
					$bb = $aa . "/" . $ha . $hb;
					mkdir( $bb, 0700 );
					for ( $c = 0; $c <= 15; $c++ ) {

						$hc = dechex($c);
						$cc = $bb . "/" . $ha . $hb . $hc;
						mkdir( $cc, 0700 );
						for ( $d = 0; $d <= 15; $d++ ) {

							$dd = $cc . "/" . $ha . $hb . $hc . dechex($d);
							mkdir( $dd, 0700 );
						}
					}
				}
			}
		}

		return true;
	}
}

