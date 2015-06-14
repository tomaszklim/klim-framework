<?php
/**
 * Klasa do dekodowania schematów baz danych dla bazy Pervasive SQL
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


class KlimDatabaseMetaPervasive extends KlimDatabaseMeta
{
	public function getTables()
	{
		return $this->listSimple( "select Xf\$Name from X\$File where Xf\$Flags != 16", true );
	}

	public function getProcedures()
	{
		return $this->listSimple( "select Xp\$Name from X\$Proc where Xp\$Flags != 1" );
	}

	/**
	 * TODO: zweryfikować prawidłowość dekodowania not null z kolumny flags.
	 * W tej chwili jest zrobione zgodnie z dokumentacją (linki poniżej) i
	 * postem na forum pervasive.com, jednakże analiza struktury tabeli w
	 * posiadanych przeze mnie bazach danych każe przypuszczać, że bit 2 we
	 * flagach powinien być interpretowany odwrotnie, niż teraz jest.
	 * Podobnie PCC pokazuje, że dla tabeli np. BD wszystkie pola są nullable,
	 * a po bicie 2 wszystkie pola są not null.
	 *
	 * Do zbadania w przyszłości, na własnych tabelach.
	 *
	 * http://ww1.pervasive.com/library/docs/psql/794/sqlref/sqlsystb.html
	 * http://ww1.pervasive.com/library/docs/psql/794/sqlref/sqldtype2.html
	 */
	public function getFields( $table )
	{
		$types_native = array (
			"0"  => "character",
			"1"  => "integer",
			"2"  => "float",
			"3"  => "date",
			"7"  => "bit",
			"11" => "zstring",
			"12" => "note",
			"14" => "unsigned",
		);

		$types_driver = array (
			"character" => "char",
			"zstring"   => "char",
			"note"      => "char",
			"integer"   => "int",
			"float"     => "float",
			"date"      => "date",
			"bit"       => "bool",
			"unsigned"  => "int",
		);

		$ltable = strtolower( $table );
		$res = $this->query( "select Xf\$Id as id from X\$File where lower(Xf\$Name) = '$ltable'" );
		$table_id = (int)$res[0][0];

		if ( !$table_id ) {
			throw new KlimRuntimeException( "unknown pervasive table $table in database $this->dbname" );
		}

		$cols = $this->query( "select f.Xe\$Name as name, f.Xe\$DataType as type, f.Xe\$Size as size, Xe\$Flags as flags, count(p.Xi\$Number) as pk, count(i.Xi\$Number) as idx from X\$Field f left join X\$Index p on f.Xe\$Id = p.Xi\$Field and p.Xi\$Number = 0 left join X\$Index i on f.Xe\$Id = i.Xi\$Field where f.Xe\$File = $table_id and f.Xe\$DataType != 255 group by name, type, size, flags, f.Xe\$Id order by f.Xe\$Id" );

		$fields = array();
		foreach ( $cols as $column ) {
			$name = trim( $column["name"] );
			$raw_type = $column["type"];
			$size = $column["size"];

			if ( !isset($types_native[$raw_type]) ) {
				throw new KlimApplicationException( "unknown pervasive data type $raw_type in database $this->dbname, table $table, field $name" );
			}

			$type = $types_driver[$types_native[$raw_type]];
			$default = false;
			$flags = (int)$column["flags"];
			$not_null = ( (int)($flags & 4) === 0 );
			$index = ( $column["idx"] > 0 );
			$primary = ( $column["pk"] > 0 );

			$fields[$name] = array( $type, $size, $default, $not_null, $index, $primary );
		}

		return $fields;
	}
}

