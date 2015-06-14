<?php
/**
 * Cache do danych oparty o serwer memcached
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


class KlimCacheDriverMemcachedPecl extends KlimCache
{
	protected $obj;

	public function __construct( $segment )
	{
		if ( empty($segment["host"]) || !is_numeric($segment["port"]) || !is_numeric($segment["compress_threshold"]) ) {
			throw new KlimApplicationException( "missing or invalid configuration settings" );
		}

		if ( !class_exists("Memcache") ) {
			throw new KlimApplicationException( "missing class Memcache" );
		}

		$this->obj = new Memcache();
		$this->obj->addServer( $segment["host"], $segment["port"] );
		$this->obj->setCompressThreshold( $segment["compress_threshold"] );
	}

	protected function rawGet( $key )
	{
		return false;
	}

	protected function rawSet( $key, $value, $period )
	{
		return false;
	}

	public function get( $key )
	{
		$data = $this->obj->get( $this->key($key) );
		return $data === false ? null : $data;
	}

	public function set( $key, $value, $period )
	{
		if ( $period > 2592000 ) {
			$period += time();
		}

		return $this->obj->set( $this->key($key), $value, MEMCACHE_COMPRESSED, $period );
	}

	protected function rawDelete( $key )
	{
		return $this->obj->delete( $key );
	}
}

