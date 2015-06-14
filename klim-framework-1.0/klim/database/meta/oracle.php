<?php
/**
 * Klasa do dekodowania schematów baz danych dla bazy Oracle i modułu php5-oci8
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


class KlimDatabaseMetaOracle extends KlimDatabaseMeta
{
	public function getTables()
	{
		return $this->listSimple( "select distinct table_name from all_all_tables where table_name not like '%$%' and owner not in ('SYS', 'SYSTEM', 'MDSYS', 'OLAPSYS') order by table_name", true );
	}

	public function getProcedures()
	{
		return $this->listSimple( "select distinct name from all_source where type = 'PROCEDURE' and name not like '%$%' and owner not in ('SYS', 'SYSTEM', 'MDSYS', 'OLAPSYS', 'ORDSYS', 'XDB', 'FLOWS_020100') order by name", true );
	}

	public function getFields( $table )
	{
		$types = array (
			"VARCHAR2" => "char",
			"CHAR"     => "char",
			"CLOB"     => "char",
			"NUMBER"   => "int",
			"DATE"     => "date",
		);

		$uptable = strtoupper( $table );

		$cols = $this->query( "select column_name, data_type, data_precision, char_length, nullable, data_default from all_tab_columns where table_name = '$uptable' order by column_id" );
		$idxs = $this->query( "select distinct column_name from all_ind_columns where table_name = '$uptable'" );
		$pks = $this->query( "select cc.column_name, cc.position from all_cons_columns cc, all_constraints c where cc.table_name = '$uptable' and c.table_name = cc.table_name and c.constraint_name = cc.constraint_name and c.constraint_type = 'P'" );

		$indexes = array();
		foreach ( $idxs as $idx ) {
			$indexes[] = $idx["column_name"];
		}

		$pk = isset($pks[0]["column_name"]) ? $pks[0]["column_name"] : false;

		foreach ( $cols as $column ) {
			$name = $column["column_name"];
			$lowname = strtolower( $name );
			$raw_type = $column["data_type"];
			$size = empty($column["char_length"]) ? $column["data_precision"] : $column["char_length"];
			$default = ( $column["data_default"] != "" && $column["data_default"] != "null" );
			$not_null = ( $column["nullable"] == "N" );
			$primary = ( $name === $pk );
			$index = in_array( $name, $indexes, true );

			if ( !isset($types[$raw_type]) ) {
				throw new KlimApplicationException( "unknown oracle data type $raw_type in database $this->dbname, table $table, field $lowname" );
			}

			$type = $types[$raw_type];
			$fields[$lowname] = array( $type, $size, $default, $not_null, $index, $primary );
		}

		return $fields;
	}
}

