<?php
/**
 * Główna klasa do obsługi baz danych
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


define( "DB_DIE", 0 );
define( "DB_NO_DIE_IF_DUPLICATE", 1 );
define( "DB_NO_DIE_IF_DISCONNECT", 2 );
define( "DB_NO_DIE_IF_READONLY", 4 );
define( "DB_NO_DIE_ELSE", 128 );
define( "DB_NO_DIE", 255 );


class KlimDatabase
{
	protected $connections = array();
	protected $instance;

	protected $operations = array (
		"select"      => "KlimDatabaseOperationSelect",
		"insert"      => "KlimDatabaseOperationInsert",
		"update"      => "KlimDatabaseOperationUpdate",
		"updateField" => "KlimDatabaseOperationUpdateField",
		"delete"      => "KlimDatabaseOperationDelete",
		"begin"       => "KlimDatabaseOperationBegin",
		"commit"      => "KlimDatabaseOperationCommit",
		"rollback"    => "KlimDatabaseOperationRollback",
		"rawQuery"    => "KlimDatabaseOperationRawQuery",
		"rawScript"   => "KlimDatabaseOperationRawScript",
	);

	protected $pipes = array (
		"getArray"    => "KlimDatabasePipeArray",
		"getValues"   => "KlimDatabasePipeValues",
		"getFields"   => "KlimDatabasePipeFields",
		"where"       => "KlimDatabasePipeWhere",
		"limit"       => "KlimDatabasePipeLimit",
		"order"       => "KlimDatabasePipeOrder",
		"join"        => "KlimDatabasePipeJoin",
		"leftJoin"    => "KlimDatabasePipeJoinLeft",
		"rightJoin"   => "KlimDatabasePipeJoinRight",
	);

	public function __construct()
	{
		$this->instance = mt_rand() % 100000;
	}

	public function getInstance()
	{
		return $this->instance;
	}

	/**
	 * Metoda przekierowująca wywołania własciwych operacji bazodanowych
	 * do klas implementujących obsługę tych operacji.
	 */
	public function __call( $method, $args )
	{
		if ( isset($this->operations[$method]) ) {
			$class = $this->operations[$method];
			$obj = new $class( $this );
			return call_user_func_array( array($obj, "execute"), $args );
		}

		if ( isset($this->pipes[$method]) ) {
			$class = $this->pipes[$method];
			$obj = new $class( $this );
			return call_user_func_array( array($obj, "execute"), $args );
		}

		throw new KlimApplicationException( "unknown method $method" );
	}

	/**
	 * Metoda cache'ująca instancje obiektów połączeniowych pomiędzy
	 * wywołaniami metod. Zapewnia sprawdzanie, czy przypadkiem obiekt nie
	 * wszedł w stan transakcyjny w innej instancji tego obiektu, bądź w
	 * ramach innej bazy logicznej - w takim przypadku cache jest resetowany
	 * i zwracany jest nowy obiekt, świeżo wyciągnięty z poolera.
	 */
	public function getConnection( $db, $die )
	{
		if ( isset($this->connections[$db]) ) {
			$obj = $this->connections[$db];

			if ( !$obj->isOpen() ) {
				unset( $obj, $this->connections[$db] );
			} else if ( $obj->isActiveTransaction() && ($obj->getTransactionInstance() != $this->instance || $obj->getTransactionDatabase() != $db) ) {
				unset( $obj, $this->connections[$db] );
			} else {
				return $obj;
			}
		}

		$obj = KlimDatabasePool::getConnection( $db );

		if ( is_object($obj) ) {
			$this->connections[$db] = $obj;
			return $obj;

		} else if ( is_set_bin_value($die, DB_NO_DIE_IF_DISCONNECT) ) {
			return false;

		} else if ( is_set_bin_value($die, DB_NO_DIE_ELSE) ) {
			return false;
		}

		throw new KlimRuntimeDatabaseException( "connection error: $obj" );
	}

	/**
	 * Zwraca typ serwera baz danych, z jakim fizycznie zestawione jest
	 * połączenie. Żadne funkcjonalności z wyjątkiem generatora definicji
	 * tabel nie powinny polegać na tym typie.
	 */
	public function getVendor( $db, $die = DB_DIE )
	{
		$obj = $this->getConnection( $db, $die );
		return $obj ? $obj->getVendor() : false;
	}

	/**
	 * Metoda wymuszająca zamknięcie połączeń z podaną bazą danych lub
	 * ze wszystkimi bazami, z którymi te połączenia są nawiązane.
	 */
	public function close( $db = false )
	{
		if ( !$db ) {

			foreach ( $this->connections as $db => $obj ) {
				$this->close( $db );
			}

		} else {

			if ( isset($this->connections[$db]) ) {
				$obj = $this->connections[$db];

				if ( $obj->isOpen() ) {
					$obj->close();
				}
			}
		}
	}
}

