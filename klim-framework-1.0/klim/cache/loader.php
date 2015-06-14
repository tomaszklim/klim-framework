<?php
/**
 * Klasa ładująca i konfigurująca sterownik cache
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


class KlimCacheLoader
{
	private static $entries = array();
	private static $instances = array();
	private static $drivers = array (
		"database"       => false,
		"tyrant"         => false,
		"redis"          => false,
		"file"           => false,
		"memcached.dean" => false,
		"memcached.pecl" => false,
		"eaccelerator"   => "eaccelerator_get",
		"apc"            => "apc_fetch",
		"turck"          => "mmcache_get",
		"xcache"         => "xcache_get",
		"wincache"       => "wincache_ucache_get",
		"zend.shm"       => "zend_shm_cache_fetch",
		"zend.disk"      => "zend_disk_cache_fetch",
	);

	public static function getInstance( $id )
	{
		$config = self::getConfig();

		if ( !array_key_exists($id, $config) ) {
			throw new KlimApplicationException( "unknown cache $id" );
		}

		if ( isset(self::$instances[$id]) ) {
			return self::$instances[$id];
		}

		$instance = self::getObject( $config[$id] );

		self::$instances[$id] = $instance;
		return $instance;
	}

	public static function getConfig()
	{
		if ( !empty(self::$entries) ) {
			return self::$entries;
		}

		if ( !include("config/cache.php") ) {
			throw new KlimApplicationException( "cannot load cache configuration" );
		}

		if ( !isset($config) || !is_array($config) ) {
			throw new KlimApplicationException( "invalid cache configuration" );
		}

		self::$entries = $config;
		return $config;
	}

	private static function getObject( $segment )
	{
		$driver = $segment["driver"];

		if ( empty($driver) || !isset(self::$drivers[$driver]) ) {
			throw new KlimApplicationException( "unknown driver for cache $id" );
		}

		$require = self::$drivers[$driver];

		if ( $require && !function_exists($require) ) {
			throw new KlimApplicationException( "cache $id configured as $driver, but required php extension not loaded" );
		}

		$raw = str_replace( ".", "_", "klim.cache.driver.$driver" );
		$class = KlimCamel::encode( $raw, true );

		return new $class( $segment );
	}
}

