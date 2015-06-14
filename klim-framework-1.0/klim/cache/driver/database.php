<?php
/**
 * Cache do danych oparty o bazę danych
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


class KlimCacheDriverDatabase extends KlimCache
{
	protected $obj;
	protected $db;
	protected $table;
	protected $colKey;
	protected $colValue;
	protected $colExpiry;

	public static function getDatabase()
	{
		static $instance;
		if ( !isset($instance) ) {
			$instance = new KlimDatabase();
		}
		return $instance;
	}

	public function __construct( $segment )
	{
		if ( empty($segment["table"]) || empty($segment["column_key"]) || empty($segment["column_value"]) || empty($segment["column_expiry"]) ) {
			throw new KlimApplicationException( "missing or invalid configuration settings" );
		}

		$this->obj = self::getDatabase();
		$this->db = @$segment["database"];
		$this->table = $segment["table"];
		$this->colKey = $segment["column_key"];
		$this->colValue = $segment["column_value"];
		$this->colExpiry = $segment["column_expiry"];
	}

	protected function rawGet( $key )
	{
		$rows = $this->obj->select( $this->table,
			array($this->colValue, $this->colExpiry),
			array($this->colKey => $key)
		);

		if ( !count($rows) ) {
			return null;
		}

		$expiry = KlimTime::getTimestamp( GMT_UNIX, $rows[0][$this->colExpiry] );

		/**
		 * Remove expired keys. Put the expiry time in the WHERE
		 * condition to avoid deleting a newly-inserted value.
		 */
		if ( intval($expiry) < time() ) {
			$this->obj->delete( $this->table, array (
				$this->colKey => $key,
				$this->colExpiry => $expiry,
			) );
			return null;
		}

		return $rows[0][$this->colValue];
	}

	protected function rawSet( $key, $value, $period )
	{
		if ( !empty($this->db) ) {
			$this->obj->begin( $this->db );
		}

		$this->obj->delete( $this->table, array($this->colKey => $key) );
		$this->obj->insert( $this->table, array(
			$this->colKey => $key,
			$this->colValue => $value,
			$this->colExpiry => time() + $period,
		) );

		if ( !empty($this->db) ) {
			$this->obj->commit( $this->db );
		}

		return true;
	}

	protected function rawDelete( $key )
	{
		$this->obj->delete( $this->table, array($this->colKey => $key) );
		return true;
	}

	public function clean()
	{
		$this->obj->delete( $this->table, array (
			$this->colExpiry => array( "<", "now()" ),
		) );
		return true;
	}
}

