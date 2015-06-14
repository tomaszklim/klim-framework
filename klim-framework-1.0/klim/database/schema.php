<?php
/**
 * Klasa ładująca definicje schematów tabel, oraz szablony tabel
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


class KlimDatabaseSchema
{
	protected static $schemas = array();
	protected static $templates = array();

	/**
	 * Metoda ładująca definicję podanej tabeli. W definicji znajduje
	 * się nazwa bazy logicznej, w którego skład wchodzi tabela,
	 * oraz lista kolumn i ich właściwości.
	 */
	public static function getDefinition( $schema )
	{
		if ( isset(self::$schemas[$schema]) ) {
			return self::$schemas[$schema];
		}

		if ( !include("config/database/schemas/$schema.php") ) {
			throw new KlimApplicationException( "cannot load schema definition file for database object $schema" );
		}

		if ( !isset($config) || !is_array($config) ) {
			throw new KlimApplicationException( "invalid schema definition file for database object $schema" );
		}

		if ( isset($config["template"]) ) {
			$config["fields"] = self::getFields( $schema, $config["template"], $config["table"] );
		}

		self::$schemas[$schema] = $config;
		return $config;
	}

	/**
	 * Metoda ładująca szablon zawierający definicję kolumn w tabeli. Szablony
	 * są stosowane do upraszczania plików z definicjami, jeśli np. mamy kilka
	 * instancji bazy z tymi samym tabelami, kolumnami itd.
	 */
	public static function getFields( $schema, $template, $table )
	{
		if ( isset(self::$templates[$template][$table]) ) {
			return self::$templates[$template][$table];
		}

		if ( !include("config/database/templates/$template/$table.php") ) {
			throw new KlimApplicationException( "cannot load schema template file for database object $schema" );
		}

		if ( !isset($fields) || !is_array($fields) ) {
			throw new KlimApplicationException( "invalid schema template file for database object $schema" );
		}

		self::$templates[$template][$table] = $fields;
		return $fields;
	}

	/**
	 * Metoda tworząca plik z definicją tabeli lub szablonem.
	 */
	public static function write( $database, $table, $fields, $output, $template = false )
	{
		$tab = $template ? "\t" : "\t\t";
		$file = "<?php\n\n";

		if ( $template ) {
			$file .= "\$fields = array (\n";
		} else {
			$file .= "\$config = array (\n";
			$file .= "\t\"class\" => \"database\",\n";
			$file .= "\t\"db\" => \"$database\",\n";
			$file .= "\t\"table\" => \"$table\",\n";
			$file .= "\t\"fields\" => array (\n";
		}

		$file .= "$tab// name type len default not_n index pk\n";

		foreach ( $fields as $name => $field ) {
			$type = $field[0];
			$size = ( $type == "char" ? $field[1] : 0 );
			$default = ( $field[2] ? "true" : "false" );
			$not_null = ( $field[3] ? "true" : "false" );
			$index = ( $field[4] || $field[5] ? "true" : "false" );  // primary key traktujemy jako dodatkowy index
			$primary = ( $field[5] ? "true" : "false" );

			$file .= "$tab\"$name\" => array( \"$type\", $size, $default, $not_null, $index, $primary ),\n";
		}

		if ( !$template ) {
			$file .= "\t),\n";
		}

		$file .= ");\n\n";
		$base = Bootstrap::getInstanceRoot();

		if ( $template ) {
			$path = "$base/include/config/database/templates/$template/$output.php";
		} else {
			$path = "$base/include/config/database/schemas/$output.php";
		}

		if ( !file_put_contents($path, $file) ) {
			throw new KlimApplicationException( "cannot write to file $path" );
		}

		return $path;
	}
}

