<?php
/**
 * Klasa do przetwarzania skryptów z zapytaniami
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


class KlimDatabaseQueryScript
{
	private $queries = array();
	private $separator = " ";

	public function __construct( $file )
	{
		$lines = file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		$query = "";

		if ( $lines === false ) {
			throw new KlimApplicationException( "file $file not found" );
		}

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( empty($line) || strpos($line, "#") === 0 || strpos($line, "--") === 0 )
				continue;

			$line = preg_replace( "/#[^']*$/", "", $line );
			$line = preg_replace( "/--[^']*$/", "", $line );
			$line = trim( $line );
			$query .= $line . $this->separator;

			if ( substr($line, -1) == ";" ) {
				$this->queries[] = $query;
				$query = "";
			}
		}
	}

	public function getQueries( $variables = false )
	{
		if ( !$variables ) {
			return $this->queries;
		}

		if ( !is_array($variables) ) {
			throw new KlimApplicationException( "invalid input data format" );
		}

		$out = array();
		$pattern = array();
		$replace = array();

		foreach ( $variables as $key => $value ) {
			if ( !is_string($value) || preg_match("/[^A-Za-z0-9_]/", $value) ) {
				throw new KlimApplicationException( "invalid key $key value" );
			}

			$pattern[] = "@@$key@@";
			$replace[] = $value;
		}

		foreach ( $this->queries as $query ) {
			$out[] = str_replace( $pattern, $replace, $query );
		}

		return $out;
	}
}

