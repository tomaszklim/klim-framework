<?php
/**
 * Klasa do dekodowania schematów baz danych dla bazy SQLite
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


class KlimDatabaseMetaSqlite extends KlimDatabaseMeta
{
	public function getTables()
	{
		return $this->listSimple( "select name from sqlite_master where type='table' order by name" );
	}

	public function getProcedures()
	{
		return array();
	}

	public function getFields( $table )
	{
		$types = array (
			"varchar"                  => "char",
			"nvarchar"                 => "char",
			"varyingcharacter"         => "char",
			"nationalvaryingcharacter" => "char",
			"text"                     => "char",
			"blob"                     => "char",
			"clob"                     => "char",
			"int"                      => "int",
			"integer"                  => "int",
			"numeric"                  => "int",
			"float"                    => "float",
			"double"                   => "float",
			"date"                     => "date",
			"timestamp"                => "date",
			"boolean"                  => "bool",
		);

		$cols = $this->query( "pragma table_info($table)" );
		$idxs = $this->query( "pragma index_list($table)" );

		$indexes = array();
		foreach ( $idxs as $idx ) {
			$name = $idx["name"];
			if ( strpos($name, "(") === false ) {
				$info = $this->query( "pragma index_info($name)" );
				$indexes[] = $info[0]["name"];
			}
		}

		foreach ( $cols as $column ) {
			$name = $column["name"];
			$raw_type = strtolower( $column["type"] );
			$default = !empty($column["dflt_value"]);
			$not_null = (int)$column["notnull"];
			$primary = (int)$column["pk"];
			$index = in_array( $name, $indexes, true );

			preg_match( "/^([a-z0-9]+)(\(([0-9,]+)\))?$/i", $raw_type, $data );

			if ( !isset($types[$data[1]]) ) {
				throw new KlimApplicationException( "unknown sqlite data type $data[1] in database $this->dbname, table $table, field $name" );
			}

			$type = $types[$data[1]];
			$size = isset($data[3]) ? $data[3] : 0;

			if ( strpos($size, ",") !== false ) {
				list( $size ) = explode( ",", $size, 2 );
			}

			if ( $size < 0 ) {
				$size = 1000000000;
			}

			$fields[$name] = array( $type, $size, $default, $not_null, $index, $primary );
		}

		return $fields;
	}
}

