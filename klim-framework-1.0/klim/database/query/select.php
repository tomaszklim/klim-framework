<?php
/**
 * Klasa do składania zapytań select
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


class KlimDatabaseQuerySelect
{
	private $query = "";

	/**
	 * TODO: Trzeba dodać obsługę klucza "lock", który byłby mapowany na
	 * klauzulę "for update" - dopuszczalna wartość to true (wówczas
	 * domyślna długość blokady) lub liczba (for update wait X).
	 */
	public function __construct( $clauses, $dbf )
	{
		$fields = $this->normalize( $clauses, "fields",   false );
		$from   = $this->normalize( $clauses, "from",     "from" );
		$where2 = $this->normalize( $clauses, "where",    false );
		$group  = $this->normalize( $clauses, "group by", "group by" );
		$order  = $this->normalize( $clauses, "order by", "order by" );
		$having = $this->normalize( $clauses, "having",   "having" );
		$where  = $where2 ? "where $where2" : false;
		$limit  = 0;
		$offset = 0;

		if ( isset($clauses["limit"]) && is_numeric($clauses["limit"]) ) {
			$limit = $clauses["limit"];
		}

		if ( isset($clauses["offset"]) && is_numeric($clauses["offset"]) ) {
			$offset = $clauses["offset"];
		}

		$sql = false;
		switch ( $dbf->getVendor() ) {

			case "oracle":
				if ( !$from ) {
					$from = "from dual";
				}

				if ( !$limit ) {

					$sql = "select $fields $from $where $group $having $order";

				} else if ( !$offset ) {

					if ( $group || $having || $order ) {
						$sql = "select * from (select $fields $from $where $group $having $order) where rownum <= $limit";
					} else if ( !$where ) {
						$sql = "select $fields $from where rownum <= $limit";
					} else {
						$sql = "select $fields $from where ($where2) and rownum <= $limit";
					}

				} else if ( strpos($fields, "*") !== false ) {

					throw new KlimRuntimeDatabaseException( "$vendor limit-offset query requires explicit field list" );

				} else if ( $group || $having || $order ) {

					throw new KlimRuntimeDatabaseException( "$vendor limit-offset query requires that no group by/order by/having clauses are set" );

				} else {
					$start = $offset + 1;
					$end = $offset + $limit;
					$sql = "select * from (select $fields, rownum as old_rownum $from $where) where old_rownum between $start and $end";
				}
				break;

			case "mysql":
			case "sqlite":
			case "postgres":
				$limit2 = "";
				if ( $offset && $limit ) {
					$limit2 = "limit $limit offset $offset";
				} else if ( $limit ) {
					$limit2 = "limit $limit";
				}

				$sql = "select $fields $from $where $group $having $order $limit2";
				break;

			// http://matipl.pl/2008/05/30/rozne-wizje-limit-w-zapytaniach-sql/
			case "ibase":
				$limit2 = "";
				if ( $offset && $limit ) {
					$start = $offset + 1;
					$end = $offset + $limit;
					$limit2 = "rows $start to $end";
				} else if ( $limit ) {
					$limit2 = "rows $limit";
				}

				$sql = "select $fields $from $where $group $having $order $limit2";
				break;

			case "mdb":
				if ( !$limit && !$offset ) {
					$sql = "select $fields $from $where $group $having $order";
				} else {
					throw new KlimRuntimeDatabaseException( "$vendor database doesn't support limit in queries" );
				}

			case "pervasive":
			case "access":
			case "mssql":
			case "unknown":
			default:
				if ( !$limit ) {
					$sql = "select $fields $from $where $group $having $order";
				} else if ( !$offset ) {
					$sql = "select top $limit $fields $from $where $group $having $order";
				} else {
					throw new KlimRuntimeDatabaseException( "$vendor database doesn't support limit-offset queries" );
				}
		}

		if ( $sql ) {
			$this->query = $sql;
		}
	}

	/**
	 * Zwraca gotową treść zapytania.
	 */
	public function getQuery()
	{
		return $this->query;
	}

	/**
	 * Metoda normalizująca klauzule w zapytaniu - przycina białe znaki,
	 * oraz łączy elementy podane w postaci tablicy w jedną klauzulę.
	 */
	private function normalize( $clauses, $key, $keyword )
	{
		if ( !isset($clauses[$key]) ) {
			return false;
		}

		if ( is_array($clauses[$key]) ) {
			$tmp = array();
			foreach ( $clauses[$key] as $element ) {
				$tmp[] = trim( $element );
			}
			$clause = implode( ",", $tmp );
		} else {
			$clause = trim( $clauses[$key] );
		}

		if ( strlen($clause) > 0 ) {
			return $keyword ? "$keyword $clause" : $clause;
		} else {
			return false;
		}
	}
}

