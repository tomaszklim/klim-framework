<?php
/**
 * Wrapper do wyniku zapytania select dla bazy PostgreSQL
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


class KlimDatabaseResultPostgres extends KlimDatabaseResult
{
	private $result;

	public function __construct( $db, $result )
	{
		$this->result = $result;
		$this->nrows = pg_num_rows( $result );
		$this->nfields = pg_num_fields( $result );
	}

	protected function free()
	{
		if ( $this->result ) {
			pg_free_result( $this->result );
			$this->result = false;
		}
	}

	protected function seekRow( $row )
	{
		if ( $this->result ) {
			pg_result_seek( $this->result, $row );
		}
	}

	protected function fetchRow()
	{
		return $this->result ? pg_fetch_array($this->result) : false;
	}
}

