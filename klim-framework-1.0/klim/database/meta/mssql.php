<?php
/**
 * Klasa do dekodowania schematów baz danych dla bazy Microsoft SQL Server
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


class KlimDatabaseMetaMssql extends KlimDatabaseMeta
{
	public function getTables()
	{
		return $this->listSimple( "select name from sysobjects where type='u' order by name", true );
	}

	public function getProcedures()
	{
		return $this->listSimple( "select name from sysobjects where type='p' order by name" );
	}

	public function getFields( $table )
	{
		$types = array (
			"varchar"          => "char",
			"nvarchar"         => "char",
			"ntext"            => "char",
			"numeric"          => "int",
			"int"              => "int",
			"tinyint"          => "int",
			"smallint"         => "int",
			"uniqueidentifier" => "int",
			"decimal"          => "int",
			"timestamp"        => "date",
			"datetime"         => "date",
			"bit"              => "bool",
		);

		$cols = $this->query( "select o.id as f_table_id, c.colid as f_column_id, c.name as f_name, t.name as f_type, c.length as f_size, c.isnullable as f_nullable, c.cdefault as f_default from sysobjects o, syscolumns c, systypes t where lower(o.name) = '$table' and o.id = c.id and c.xtype = t.xtype and c.xtype = t.xusertype and t.name != 'sysname' order by c.colid" );

		$table_id = $cols[0]["f_table_id"];
		$idxs = $this->query( "select colid from sysindexkeys where id = $table_id order by colid" );
		$cstr = $this->query( "select name, lower(xtype) as type from sysobjects where parent_obj = $table_id and xtype in ('PK','UQ')" );

		$indexes = array();
		foreach ( $idxs as $idx ) {
			$indexes[] = $idx["colid"];
		}

		foreach ( $cols as $column ) {
			$column_id = $column["f_column_id"];
			$name = $column["f_name"];
			$lowname = strtolower( $name );
			$raw_type = $column["f_type"];
			$size = $column["f_size"];
			$default = !empty($column["f_default"]);
			$not_null = empty($column["f_nullable"]);
			$primary = false;
			$name2 = trim( str_ireplace("Id", "", $name), "_" );

			foreach ( $cstr as $constraint ) {
				if ( $constraint["type"] === "pk" && stripos($constraint["name"], $name2) !== false ) {
					$primary = true;
				}
			}

			// w razie czego można dodatkowo sprawdzić tablicę $cstr z type=uq,
			// tam są podefiniowane pola unikalne, których nazwy można również
			// przejechać nazwą kolumny - być może znajdą się dodatkowe indeksy
			$index = in_array( $column_id, $indexes, true );

			if ( !isset($types[$raw_type]) ) {
				throw new KlimApplicationException( "unknown mssql data type $raw_type in database $this->dbname, table $table, field $lowname" );
			}

			$type = $types[$raw_type];
			$fields[$lowname] = array( $type, $size, $default, $not_null, $index, $primary );
		}

		return $fields;
	}
}

