<?php
/**
 * Klasa do dekodowania schematów baz danych dla bazy PostgreSQL
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


class KlimDatabaseMetaPostgres extends KlimDatabaseMeta
{
	public function getTables()
	{
		return $this->listSimple( "select table_name from information_schema.tables where table_schema='public' and table_type='BASE TABLE' order by table_name" );
	}

	public function getProcedures()
	{
		$rawname = $this->getRawName();
		return $this->listSimple( "select routine_name from information_schema.routines where routine_type='FUNCTION' and routine_schema='$rawname'" );
	}

	public function getFields( $table )
	{
		$types = array (
			"text"        => "char",
			"numeric"     => "int",
			"int2"        => "int",
			"int4"        => "int",
			"int8"        => "int",
			"timestamptz" => "date",
		);

		$cols = $this->query( "select a.attname as field, t.typname as type, a.attlen as length, a.atthasdef as has_default, a.attnotnull as not_null, a.attrelid as table_id, i.indisprimary as is_pk, i.indisunique as is_unique from pg_class c join pg_attribute a on a.attnum > 0 and a.attrelid = c.oid join pg_type t on a.atttypid = t.oid left join pg_index i on i.indrelid = a.attrelid and i.indkey[0] = a.attnum where c.relname = '$table' order by a.attnum" );

		foreach ( $cols as $column ) {
			$name = $column["field"];
			$raw_type = $column["type"];
			$size = $column["length"];
			$default = ( $column["has_default"] == "t" );
			$not_null = ( $column["not_null"] == "t" );
			$primary = ( $column["is_pk"] == "t" );
			$index = ( $column["is_unique"] != "" );

			if ( !isset($types[$raw_type]) ) {
				throw new KlimApplicationException( "unknown postgres data type $raw_type in database $this->dbname, table $table, field $name" );
			}

			if ( $size < 0 ) {
				$size = 1024 * 1024 * 1024;
			}

			$type = $types[$raw_type];
			$fields[$name] = array( $type, $size, $default, $not_null, $index, $primary );
		}

		return $fields;
	}
}

