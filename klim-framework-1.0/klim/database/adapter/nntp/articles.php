<?php
/**
 * Klasa emulująca zapytanie SELECT na serwerze NNTP
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


class KlimDatabaseAdapterNntpArticles extends KlimDatabaseAdapter
{
	protected $fields = array (
		// name             type    len   default not_n index  pk
		"id"      => array( "int",  0,     false, true, true,  true  ),
		"article" => array( "char", 65536, false, true, false, false ),
	);

	public function execute( $schema, $fields, $where, $clauses, $die )
	{
		if ( isset($clauses["group by"]) || isset($clauses["having"]) ) {
			throw new KlimRuntimeDatabaseException( "group by and having clauses are not supported for csv files" );
		}

		$definition = KlimDatabaseSchema::getDefinition( $schema );
		$definition["fields"] = $this->fields;

		$obj = $this->controller->getConnection( $definition["db"], $die );

		if ( $obj === false ) {
			return false;
		}

		$group = $definition["group"];

		if ( !$obj->setContext($group) ) {
			throw new KlimRuntimeDatabaseException( "error setting group $group", $obj->lastError() );
		}

		$min = $obj->getMinId();
		$max = $obj->getMaxId();

		$where2 = new KlimDatabaseQueryWhere( $where, $definition, $obj );
		$term = $where2->getTerm( "id" );

		if ( $term ) {
			$articles = $this->processTerm( $term, $min, $max );
		} else {
			$articles = array();
			for ( $x = $min; $x <= $max; $x++ )
				$articles[] = $x;
		}

		$rows = array();

		foreach ( $articles as $article ) {
			$response = $obj->query( "article $article" );
			$status = $response["status"];

			// TODO: obsługa kodów 4xx, trzeba rozróżnić sytuacje, gdy konkretny artykuł
			//       został skasowany od sytuacji rozłączenia połączenia itp. problemów
			if ( $status == 220 || $status == 222 ) {
				$rows[] = array( "id" => $article, "article" => $obj->getBody() );
			} else {
				KlimLogger::info( "nntp", "no article $article on group $group, nntp server error [$status]", $response["message"] );
			}
		}

		$rows = $this->simpleProjection( $rows, $fields, $where, $clauses );

		return new KlimDatabaseResultArray( false, $rows );
	}


	protected function processTerm( $term, $min, $max )
	{
		$out = array();
		foreach ( $term as $operator => $values ) {
			switch ( $operator ) {

				case "=":
					$out[] = $values[0];
					break;

				case "!=":
					for ( $x = $min; $x <= $max; $x++ )
						if ( $x != $values[0] )
							$out[] = $x;
					break;

				case "<":
					for ( $x = $min; $x < $values[0]; $x++ )
						$out[] = $x;
					break;

				case "<=":
					for ( $x = $min; $x <= $values[0]; $x++ )
						$out[] = $x;
					break;

				case ">":
					for ( $x = $values[0] + 1; $x <= $max; $x++ )
						$out[] = $x;
					break;

				case ">=":
					for ( $x = $values[0]; $x <= $max; $x++ )
						$out[] = $x;
					break;

				case "is":
				case "bitand":
				case "not bitand":
					throw new KlimRuntimeDatabaseException( "$operator term not supported" );

				case "is not":
					for ( $x = $min; $x <= $max; $x++ )
						$out[] = $x;
					break;

				case "in":
					foreach ( $values as $value )
						$out[] = $value;
					break;

				case "not in":
					for ( $x = $min; $x <= $max; $x++ )
						if ( !in_array($x, $values, true) )
							$out[] = $x;
					break;

				case "between":
					for ( $x = $values[0]; $x <= $values[1]; $x++ )
						$out[] = $x;
					break;

				case "not between":
					for ( $x = $min; $x < $values[0]; $x++ )
						$out[] = $x;
					for ( $x = $values[0] + 1; $x <= $max; $x++ )
						$out[] = $x;
					break;
			}
		}

		return $out;
	}
}

