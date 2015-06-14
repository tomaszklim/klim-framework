<?php
/**
 * Klasa emulująca zapytanie SELECT na arkuszu kalkulacyjnym
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


class KlimDatabaseAdapterSheet extends KlimDatabaseAdapter
{
	public function execute( $schema, $fields, $where, $clauses, $die )
	{
		Bootstrap::addLibrary( "phpexcel-1.7.6" );
		require_once "PHPExcel.php";
		require_once "PHPExcel/IOFactory.php";
		require_once "PHPExcel/Worksheet.php";
		require_once "PHPExcel/Settings.php";
		require_once "PHPExcel/CachedObjectStorageFactory.php";

		if ( isset($clauses["group by"]) || isset($clauses["having"]) ) {
			throw new KlimRuntimeDatabaseException( "group by and having clauses are not supported for excel sheets" );
		}

		$definition = KlimDatabaseSchema::getDefinition( $schema );

		try {
			$cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_in_memory_gzip;
			PHPExcel_Settings::setCacheStorageMethod( $cacheMethod );

			$excel = PHPExcel_IOFactory::load( $definition["workbook"] );
			$sheet = $excel->getSheetByName( $definition["worksheet"] );
			$result = $sheet->toArray();
			$sheet->disconnectCells();

		// TODO: jeśli ustawiona jest maska DB_NO_DIE_IF_DISCONNECT, to return false;
		} catch ( Exception $e ) {
			throw new KlimRuntimeDatabaseException( "error loading sheet $schema", $e->getMessage() );
		}

		$rows = $this->mapRows( $result, $definition["fields"], $definition["header_rows"] );
		$rows = $this->simpleProjection( $rows, $fields, $where, $clauses );

		unset( $result, $sheet, $excel );

		return new KlimDatabaseResultArray( false, $rows );
	}
}

