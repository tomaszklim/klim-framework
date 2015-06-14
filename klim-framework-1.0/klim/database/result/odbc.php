<?php
/**
 * Generyczny wrapper do wyniku zapytania select dla ODBC
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


class KlimDatabaseResultOdbc extends KlimDatabaseResult
{
	private $result;

	public function __construct( $db, $result )
	{
		$this->result = $result;
		$this->nfields = odbc_num_fields( $result );

		while ( true ) {
			$row = $this->fetchRow();
			if ( !$row ) break;
			$this->rows[$this->nrows++] = $row;
		}

		@odbc_free_result( $this->result );
		$this->result = false;
	}

	protected function free()
	{
	}

	protected function seekRow( $row )
	{
	}

	/**
	 * W zależności od implementacji ODBC (czyli od systemu operacyjnego),
	 * funkcja natywna odbc_fetch_array może istnieć, bądź nie. W drugim
	 * przypadku emulujemy jej działanie.
	 */
	protected function fetchRow()
	{
		if ( !$this->result || !odbc_fetch_row($this->result) ) return false;

		$row = array();
		$numfields = odbc_num_fields( $this->result );

		for ( $i = 1; $i <= $numfields; $i++ ) {
			$field = odbc_field_name( $this->result, $i );
			$tmp = odbc_result( $this->result, $i );
			$row[$i - 1] = $tmp;
			if ( !isset($row[$field]) ) {
				$row[$field] = $tmp;
			}
		}
		return $row;
	}
}

