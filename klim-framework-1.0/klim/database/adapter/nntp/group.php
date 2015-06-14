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


class KlimDatabaseAdapterNntpGroup extends KlimDatabaseAdapter
{
	protected $rows = array();
	protected $fields = array (
		// name                type    len   default not_n  index  pk
		"id"         => array( "int",  0,     false, true,  true,  true  ),
		"subject"    => array( "char", 65536, false, true,  false, false ),
		"from"       => array( "char", 65536, false, true,  false, false ),
		"from_name"  => array( "char", 65536, false, true,  false, false ),
		"date"       => array( "date", 0,     false, true,  false, false ),
		"message_id" => array( "char", 65536, false, true,  false, false ),
		"references" => array( "char", 65536, false, false, false, false ),
		"bytes"      => array( "int",  0,     false, true,  false, false ),
		"lines"      => array( "int",  0,     false, true,  false, false ),
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

		$where2 = new KlimDatabaseQueryWhere( $where, $definition, $obj );
		$term = $where2->getTerm( "id" );

		if ( $term ) {
			$commands = $this->processTerm( $term );
		} else {
			$commands = array( "xover 0-" );
		}

		foreach ( $commands as $command ) {
			$this->addRows( $obj, $command );
		}

		$rows = $this->simpleProjection( $this->rows, $fields, $where, $clauses );
		unset( $this->rows );

		return new KlimDatabaseResultArray( false, $rows );
	}


	protected function processTerm( $term )
	{
		$out = array();
		foreach ( $term as $operator => $values ) {
			switch ( $operator ) {

				case "=":
					$out[] = "xover {$values[0]}-{$values[0]}";
					break;

				case "!=":
				case "not in":
				case "is not":
					$out[] = "xover 0-";
					break;

				case "<":
					$value = $values[0] - 1;
					$out[] = "xover 0-{$value}";
					break;

				case ">":
					$value = $values[0] + 1;
					$out[] = "xover {$value}-";
					break;

				case "<=":
					$out[] = "xover 0-{$values[0]}";
					break;

				case ">=":
					$out[] = "xover {$values[0]}-";
					break;

				case "is":
				case "bitand":
				case "not bitand":
					throw new KlimRuntimeDatabaseException( "$operator term not supported" );

				case "in":
					foreach ( $values as $value )
						$out[] = "xover {$value}-{$value}";
					break;

				case "between":
					$out[] = "xover {$values[0]}-{$values[1]}";
					break;

				case "not between":
					$value0 = $values[0] - 1;
					$value1 = $values[1] + 1;
					$out[] = "xover 0-{$value0}";
					$out[] = "xover {$value1}-";
					break;
			}
		}

		return $out;
	}


	protected function addRows( $obj, $command )
	{
		$response = $obj->query( $command );
		$status = $response["status"];

		if ( $status != 224 ) {
			throw new KlimRuntimeDatabaseException( "nntp server error $status", $response["message"] );
		}

		$buf = $obj->getLine();
		while ( !preg_match("/^\.\s*$/", $buf) ) {
			$elements = explode( "\t", $buf );
			$buf = $obj->getLine();

			$from = KlimMime::decodeSender( KlimMime::decodeHeader($elements[2], "utf-8") );

			$this->rows[] = array (
				"id"         => $elements[0],
				"subject"    => KlimMime::decodeHeader( $elements[1], "utf-8" ),
				"from"       => $from["email"],
				"from_name"  => $from["name"],
				"date"       => KlimTime::getTimestamp( GMT_DB, $elements[3] ),
				"message_id" => $elements[4],
				"references" => trim( $elements[5] ),
				"bytes"      => (int)$elements[6],
				"lines"      => (int)$elements[7],
			);
		}
	}
}

