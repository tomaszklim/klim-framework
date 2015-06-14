<?php
/**
 * Cache do danych oparty o serwer Tokyo Tyrant
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


class KlimCacheDriverTyrant extends KlimCache
{
	protected $obj;

	public function __construct( $segment )
	{
		if ( empty($segment["host"]) || !is_numeric($segment["port"]) ) {
			throw new KlimApplicationException( "missing or invalid configuration settings" );
		}

		if ( !class_exists("TokyoTyrant") ) {
			throw new KlimApplicationException( "missing class TokyoTyrant" );
		}

		$this->obj = new TokyoTyrant( $segment["host"], $segment["port"] );
	}

	protected function rawGet( $key )
	{
		return $this->obj->get( $key );
	}

	protected function rawSet( $key, $value, $period )
	{
		return false;
	}

	public function set( $key, $value, $period )
	{
		return $this->obj->put( $this->key($key), serialize($value) );
	}

	protected function rawDelete( $key )
	{
		return $this->obj->out( $key );
	}
}

