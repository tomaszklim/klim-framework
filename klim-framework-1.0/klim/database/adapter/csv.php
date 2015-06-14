<?php
/**
 * Klasa emulująca zapytanie SELECT na pliku CSV
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


class KlimDatabaseAdapterCsv extends KlimDatabaseAdapter
{
	public function execute( $schema, $fields, $where, $clauses, $die )
	{
		Bootstrap::addLibrary( "php-csv-utils-r88" );
		require_once "Csv/Exception.php";
		require_once "Csv/Exception/CannotDetermineDialect.php";
		require_once "Csv/Exception/InvalidHeaderRow.php";
		require_once "Csv/Exception/FileNotFound.php";
		require_once "Csv/Dialect.php";
		require_once "Csv/Dialect/Excel.php";
		require_once "Csv/AutoDetect.php";
		require_once "Csv/Reader/Abstract.php";
		require_once "Csv/Reader.php";
		require_once "Csv/Reader/String.php";

		if ( isset($clauses["group by"]) || isset($clauses["having"]) ) {
			throw new KlimRuntimeDatabaseException( "group by and having clauses are not supported for csv files" );
		}

		$definition = KlimDatabaseSchema::getDefinition( $schema );

		$file = $definition["file"];

		if ( empty($definition["dialect"]) ) {
			$dialect = null;
		} else if ( is_array($definition["dialect"]) ) {
			$dialect = new Csv_Dialect( $definition["dialect"] );
		} else if ( $definition["dialect"] == "excel" ) {
			$dialect = new Csv_Dialect_Excel();
		}

		try {
			$reader = new Csv_Reader( $file, $dialect );
			$reader->setHeader( $definition["header"] );

			$first_row = $reader->current();
			$reader->rewind();

			$header_rows = array();
			foreach ( $first_row as $col => $val ) {
				if ( $col == $val || in_array($val, $definition["header"], true) ) {
					$header_rows[] = 0;
					break;
				}
			}

			$rows = $this->mapRows( $reader, $definition["fields"], $header_rows );

		// TODO: jeśli ustawiona jest maska DB_NO_DIE_IF_DISCONNECT, to return false;
		} catch ( Csv_Exception_FileNotFound $e ) {
			throw new KlimRuntimeDatabaseException( "source file $file not found", $e->getMessage() );

		// TODO: jeśli ustawiona jest maska DB_NO_DIE_ELSE, to return false;
		} catch ( Exception $e ) {
			throw new KlimRuntimeDatabaseException( "unknown error reading file $file", $e->getMessage() );
		}

		$rows = $this->simpleProjection( $rows, $fields, $where, $clauses );

		return new KlimDatabaseResultArray( false, $rows );
	}
}

