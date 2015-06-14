<?php
/**
 * Klasa implementująca filtr do danych
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


/**
 * Filtr sortujący wynik zapytania po podanych kolumnach.
 *
 * Składnia:
 *
 *   $db = new KlimDatabase();
 *   $rows = $db->select( "ip_users", "*" );
 *
 *   $order = array (
 *       "iu_search_nip2" => "desc",
 *       "iu_last_update" => "desc",
 *   );
 *
 *   $types = array (
 *       "iu_search_nip2" => "int",
 *       "iu_last_update" => "date",
 *   );
 *
 *   $rows = $db->order( $rows, $order, $types );
 */
class KlimDatabasePipeOrder extends KlimDatabasePipe
{
	protected $types;
	protected $order;

	public function execute( $rows, $order = array(), $types = array() )
	{
		if ( !is_array($order) || !is_array($types) ) {
			throw new KlimApplicationException( "invalid input data" );
		}

		if ( empty($rows) || empty($order) ) {
			return $rows;
		}

		$keys = array_keys( $rows[0] );

		foreach ( $order as $key => $value )
		{
			if ( !in_array($key, $keys) ) {
				unset( $order[$key] );
				continue;
			}

			if ( strtolower($value) == "desc" )
				$order[$key] = -1;
			else
				$order[$key] = 1;
		}

		if ( empty($order) ) {
			return $rows;
		}

		$this->order = $order;
		$this->types = $types;

		if ( is_object($rows) ) {
			$rows = $this->controller->getArray( $rows );
		}

		usort( $rows, array($this, "callbackSort") );
		return $rows;
	}

	protected function callbackSort( $rowA, $rowB )
	{
		$ret = 0;
		foreach ( $this->order as $column => $direction )
		{
			// TODO: dedykowana metoda do porównywania dat
			$numeric = array( "int", "float" );

			if ( isset($this->types[$column]) && in_array($this->types[$column], $numeric, true) )
			{
				$ret = $this->compareNumeric( $rowA, $rowB, $column, $direction );
			}
			else if ( !isset($this->types[$column]) && is_numeric($rowA[$column]) && is_numeric($rowB[$column]) )
			{
				$ret = $this->compareNumeric( $rowA, $rowB, $column, $direction );
			}
			else
			{
				$ret = $this->compareLiteral( $rowA, $rowB, $column, $direction );
			}

			if ( $ret != 0 )
				break;
		}

		return $ret;
	}

	protected function compareNumeric( $rowA, $rowB, $column, $direction )
	{
		$a = (float)$rowA[$column];
		$b = (float)$rowB[$column];

		if ( $a == $b )
			return 0;
		else
			return $direction * ( $a < $b ? -1 : 1 );
	}

	protected function compareLiteral( $rowA, $rowB, $column, $direction )
	{
		return $direction * strcoll( $rowA[$column], $rowB[$column] );
	}
}

