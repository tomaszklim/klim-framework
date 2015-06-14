<?php
/**
 * Klasa do dekodowania schematów baz danych dla bazy Microsoft Access i Linuxa
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


class KlimDatabaseMetaMdb extends KlimDatabaseMeta
{
	public function getTables()
	{
		$arr = parse_ini_file( "/etc/odbc.ini", true );

		if ( empty($arr[$this->dbname]["Database"]) ) {
			throw new KlimApplicationException( "database file not found in odbc.ini for database $this->dbname" );
		}

		$file = KlimShell::escape( $arr[$this->dbname]["Database"] );
		$cmd = "/usr/bin/mdb-tables $file";
		$out = KlimShell::execute( $cmd );

		return explode( " ", trim($out) );
	}

	public function getProcedures()
	{
		return array();
	}

	public function getFields( $table )
	{
		$types = array (
			"Text"             => "char",
			"Memo/Hyperlink"   => "char",
			"OLE"              => "char",
			"Integer"          => "int",
			"Long Integer"     => "int",
			"Boolean"          => "bool",
			"Double"           => "float",
			"DateTime (Short)" => "date",
		);

		$cols = $this->query( "describe table $table" );

		$fields = array();
		$cnt = 0;
		foreach ( $cols as $column ) {
			$name = $column["Column Name"];
			$raw_type = $column["Type"];
			$size = $column["Size"];
			$default = false;
			$not_null = true;
			$index = ( preg_match("/^ID/i", $name) || preg_match("/ID$/i", $name) ? true : false );
			$primary = ( $cnt == 0 && $index );

			if ( !isset($types[$raw_type]) ) {
				throw new KlimApplicationException( "unknown mdb data type $raw_type in database $this->dbname, table $table, field $name" );
			}

			$type = $types[$raw_type];
			$fields[$name] = array( $type, $size, $default, $not_null, $index, $primary );
			$cnt++;
		}

		return $fields;
	}
}

