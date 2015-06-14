<?php
/**
 * Klasa do wymuszania zgodności danych z podanym typem pola
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


class KlimDatabaseQueryEscape
{
	private $dbf;
	private $vendor;
	private $skipQuotes;

	public function __construct( $dbf )
	{
		$this->dbf = $dbf;
		$this->vendor = $dbf->getVendor();
		$this->skipQuotes = ( $this->vendor === "oracle" );
	}

	public function parse( $name, $value, $type, $size = 0 )
	{
		if ( $value === null ) {
			return "null";
		}

		switch ( $type ) {

			case "int":
				if ( is_numeric($value) ) {
					return (int)$value;
				} else {
					throw new KlimRuntimeDatabaseException( "invalid field $name value [int]" );
				}

			case "bool":
				if ( is_bool($value) ) {
					return (int)( (bool)$value );
				} else {
					throw new KlimRuntimeDatabaseException( "invalid field $name value [bool]" );
				}

			case "float":
				$tmp = str_replace( ",", ".", $value );

				if ( is_numeric($tmp) ) {
					return (float)$tmp;
				} else {
					throw new KlimRuntimeDatabaseException( "invalid field $name value [float]" );
				}

			case "date":
				if ( !strcasecmp("now()", trim($value)) ) {
					if ( $this->vendor === "sqlite" ) {
						return "datetime('now')";
					} else {
						return $value;
					}
				}

				$tmp = $this->dbf->convertDate( $value );

				if ( $tmp === false ) {
					throw new KlimRuntimeDatabaseException( "invalid field $name value [date]" );
				}

				if ( !$this->skipQuotes ) {
					$tmp = $this->dbf->addQuotes( $tmp );
				}

				if ( $this->vendor === "access" ) {
					return str_replace( "'", "#", $tmp );
				} else {
					return $tmp;
				}

			case "char":
				$tmp = $this->skipQuotes ? $value : $this->dbf->addQuotes($value);

				if ( $size < 1 || strlen($tmp) <= $size + 2 ) {
					return $tmp;
				} else {
					throw new KlimRuntimeDatabaseException( "invalid field $name value [char]" );
				}

			default:
				throw new KlimApplicationException( "unknown field $name type in table definition" );
		}
	}
}

