<?php
/**
 * Cache do danych oparty o serwer Redis
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


class KlimCacheDriverRedis extends KlimCache
{
	protected $obj;

	public function __construct( $segment )
	{
		if ( empty($segment["host"]) || !is_numeric($segment["port"]) ) {
			throw new KlimApplicationException( "missing or invalid configuration settings" );
		}

		if ( !class_exists("Redis") ) {
			throw new KlimApplicationException( "missing class Redis" );
		}

		$this->obj = new Redis();
		$this->obj->connect( $segment["host"], $segment["port"] );
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
		return $this->obj->get( $this->key($key) );
	}

	public function set( $key, $value, $period )
	{
		return $this->obj->set( $this->key($key), $value, $period );
	}

	protected function rawDelete( $key )
	{
		return $this->obj->delete( $key );
	}
}

