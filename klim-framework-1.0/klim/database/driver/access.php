<?php
/**
 * Sterownik do bazy danych Microsoft Access
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


class KlimDatabaseDriverAccess extends KlimDatabaseDriverOdbc
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
		return $this->isReadOnly() ? "access" : "mdb";
	}

	/**
	 * Na chwilę obecną sterownik dla systemów innych niż Windows jest
	 * oparty o bibliotekę mdbtools, która obsługuje tylko operacje
	 * odczytu z pliku mdb.
	 *
	 * Dodatkowym utrudnieniem jest to, że biblioteka ta nie zwraca jawnie
	 * błędu przy próbie wykonania zapytania modyfikującego dane, ale po
	 * prostu wyświetla komunikat błędu na stdout i zwraca prawidłowy wynik
	 * (resource) z affected_rows = 0 (czyli brak zmodyfikowanych krotek,
	 * co w sumie jest prawdą).
	 *
	 * http://mailman.unixodbc.org/pipermail/unixodbc-support/2005-December/000840.html
	 * http://osdir.com/ml/db.mdb-tools.devel/2005-01/msg00006.html
	 * http://bryanmills.net/archives/2003/11/microsoft-access-database-using-linux-and-php/
	 */
	public function isReadOnly()
	{
		return strpos(PHP_OS, "WIN") !== false;
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

