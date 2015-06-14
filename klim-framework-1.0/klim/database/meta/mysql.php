<?php
/**
 * Klasa do dekodowania schematów baz danych dla bazy MySQL
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


class KlimDatabaseMetaMysql extends KlimDatabaseMeta
{
	public function getTables()
	{
		return $this->listSimple( "show tables" );
	}

	/**
	 * W przypadku MySQL wyświetlane są tylko procedury stworzone przez tego
	 * samego użytkownika, który jest podpięty do aplikacji - inne procedury
	 * w ogóle nie będą widziane.
	 *
	 * Można to ominąć, robiąc select bezpośrednio z tabeli mysql.proc, ale
	 * do tego trzeba mieć uprawnienia do tej tabeli, a w dzisiejszych
	 * wersjach MySQL ma je tylko użytkownik root.
	 */
	public function getProcedures()
	{
		$rawname = $this->getRawName();
		return $this->listSimple( "select routine_name from information_schema.routines where routine_type='PROCEDURE' and routine_schema='$rawname'" );
	}

	public function getFields( $table )
	{
		$types = array (
			"mediumtext" => "char",
			"mediumblob" => "char",
			"varchar"    => "char",
			"char"       => "char",
			"varbinary"  => "char",
			"binary"     => "char",
			"int"        => "int",
			"bigint"     => "int",
			"smallint"   => "int",
			"tinyint"    => "int",
			"float"      => "float",
			"datetime"   => "date",
			"timestamp"  => "date",
		);

		$cols = $this->query( "show columns from $table" );
		$idxs = $this->query( "show indexes from $table" );

		$fields = array();
		foreach ( $cols as $column ) {
			$name = $column["Field"];
			$raw_type = $column["Type"];
			$default = !empty($column["Default"]);
			$not_null = ( $column["Null"] == "NO" );
			$primary = ( $column["Key"] == "PRI" );
			$index = false;

			foreach ( $idxs as $idx ) {
				if ( $name == $idx["Column_name"] ) {
					$index = true;
					break;
				}
			}

			preg_match( "/^([a-z0-9]+)(\(([0-9]+)\))?$/i", $raw_type, $data );

			if ( !isset($types[$data[1]]) ) {
				throw new KlimApplicationException( "unknown mysql data type $data[1] in database $this->dbname, table $table, field $name" );
			}

			$type = $types[$data[1]];
			$size = isset($data[3]) ? $data[3] : 0;

			if ( $raw_type == "mediumtext" || $raw_type == "mediumblob" ) {
				$size = 65536;
			}

			$fields[$name] = array( $type, $size, $default, $not_null, $index, $primary );
		}

		return $fields;
	}
}

