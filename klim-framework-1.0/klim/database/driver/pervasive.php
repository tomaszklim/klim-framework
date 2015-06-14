<?php
/**
 * Sterownik do bazy danych Pervasive SQL
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


class KlimDatabaseDriverPervasive extends KlimDatabaseDriverOdbc
{
	/**
	 * Ten sterownik obsługuje tylko Windows-1250, ustawienie "charset"
	 * z konfiguracji segmentu jest ignorowane, a baza danych musi być
	 * skonfigurowana do obsługi Windows-1250.
	 */
	public function getEncoding()
	{
		return "Windows-1250";
	}

	public function getVendor()
	{
		return "pervasive";
	}

	// http://ww1.pervasive.com/support/technical/codes2k/1statcod2.html
	public function isDuplicate()
	{
		return ( abs($this->errno) == 1605 || strpos($this->error, "Illegal duplicate key") !== false ? true : false );
	}

	public function insertId()
	{
		if ( !$this->connection ) {
			return 0;
		}

		$sql = "SELECT @@identity";
		$res = $this->query( $sql );
		return (int)$res[0][0];
	}

	public function strencode( $arg )
	{
		return str_replace( "'", "''", $arg );
	}
}

