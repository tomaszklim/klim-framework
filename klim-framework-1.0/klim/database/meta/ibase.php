<?php
/**
 * Klasa do dekodowania schematów baz danych dla bazy InterBase/Firebird
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


class KlimDatabaseMetaIbase extends KlimDatabaseMeta
{
	public function getTables()
	{
		return $this->listSimple( "select rdb\$relation_name from rdb\$relations where rdb\$system_flag = 0 and rdb\$view_blr is null order by rdb\$relation_name", true );
	}

	public function getProcedures()
	{
		return $this->listSimple( "select rdb\$procedure_name from rdb\$procedures where rdb\$system_flag = 0 order by rdb\$procedure_name", true );
	}

	public function getFields( $table )
	{
		$types = array (
			"VARYING"   => "char",
			"LONG"      => "int",
			"SHORT"     => "int",
			"DATE"      => "date",
			"TIMESTAMP" => "date",
		);

		$uptable = strtoupper( $table );

		$cols = $this->query( "select rf.rdb\$field_name, f.rdb\$field_type, f.rdb\$field_sub_type, t.rdb\$type_name, rf.rdb\$null_flag, f.rdb\$field_length, f.rdb\$field_scale, f.rdb\$character_length, f.rdb\$field_precision, f.rdb\$default_value, f.rdb\$default_source from rdb\$relation_fields rf join rdb\$fields f on rf.rdb\$field_source = f.rdb\$field_name join rdb\$types t on t.rdb\$field_name = 'RDB\$FIELD_TYPE' and t.rdb\$type = f.rdb\$field_type where rf.rdb\$relation_name = '$uptable' order by rf.rdb\$field_position, rf.rdb\$field_name" );

		$idxs = $this->query( "select i.rdb\$index_id, i.rdb\$unique_flag, i.rdb\$foreign_key, s.rdb\$field_name from rdb\$indices i join rdb\$index_segments s on i.rdb\$index_name = s.rdb\$index_name where i.rdb\$relation_name = '$uptable'" );

		$indexes = array();
		$pk = false;
		foreach ( $idxs as $idx ) {
			$indexes[] = trim($idx["rdb\$field_name"]);
			if ( $idx["rdb\$index_id"] == 1 && $idx["rdb\$unique_flag"] == 1 && $idx["rdb\$foreign_key"] == "" ) {
				$pk = trim($idx["rdb\$field_name"]);
			}
		}

		foreach ( $cols as $column ) {
			$name = trim($column["rdb\$field_name"]);
			$lowname = strtolower( $name );
			$raw_type = trim($column["rdb\$type_name"]);
			$size = $column["rdb\$field_length"];
			$default = ( $column["rdb\$default_value"] != "" || $column["rdb\$default_source"] != "" );
			$not_null = ( $column["rdb\$null_flag"] == "1" );
			$primary = ( $name === $pk );
			$index = in_array( $name, $indexes, true );

			if ( !isset($types[$raw_type]) ) {
				throw new KlimApplicationException( "unknown ibase data type $raw_type in database $this->dbname, table $table, field $lowname" );
			}

			$type = $types[$raw_type];
			$fields[$lowname] = array( $type, $size, $default, $not_null, $index, $primary );
		}

		return $fields;
	}
}

