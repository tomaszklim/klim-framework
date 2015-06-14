<?php
/**
 * Wrapper do wyniku zapytania select dla serwerów IMAP i POP3
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


class KlimDatabaseResultImap extends KlimDatabaseResult
{
	private $db;
	private $connection;
	private $result;

	public function __construct( $db, $result )
	{
		$this->db = $db;
		$this->connection = $db->getConnection();
		$this->result = $result;
		$this->nrows = count( $result );
		$this->nfields = 2;
	}

	protected function free()
	{
		if ( $this->result ) {
			unset( $this->result );
			$this->result = false;
		}
	}

	protected function seekRow( $row )
	{
	}

	protected function fetchRow()
	{
		if ( $this->result ) {
			$id = $this->result[$this->cursor];
			$body = imap_fetchbody( $this->connection, $id, false, FT_PEEK );
			return array( "id" => $id, "message" => $body );
		} else {
			return false;
		}
	}

	/**
	 *
	 * Poniższe metody są specyficzne dla protokołu IMAP.
	 *
	 */

	public function getFolders()
	{
		return $this->db->getFolders();
	}

	public function move( $folder )
	{
		if ( !empty($this->result) ) {
			$id = $this->result[$this->cursor - 1];
			$this->db->queueMove( $id, $folder );
		}
	}

	public function delete()
	{
		if ( !empty($this->result) ) {
			$id = $this->result[$this->cursor - 1];
			$this->db->queueDelete( $id );
		}
	}
}

