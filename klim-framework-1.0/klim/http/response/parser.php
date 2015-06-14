<?php
/**
 * Klasa parsująca nagłówki odpowiedzi od serwera http
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


class KlimHttpResponseParser
{
	protected $headers;
	protected $patterns = array (
		"ETag",
		"Content-Type",
		"Content-Location",
		"Content-Disposition",
		"Content-Description",
		"Content-Transfer-Encoding",
		"Last-Modified",
		"Expires",
		"Vary",
		"Pragma",
		"Alternates",  // rfc2295, 8.3, 10.2
		"TCN",  // rfc2295, 8.5
		"Variant-Vary",  // rfc2295, 8.6
	);


	public function __construct( $headers )
	{
		$this->headers = $headers;
	}

	public function getCode()
	{
		if ( preg_match("/HTTP\/[0-9.]+ ([0-9]{3})(.*)/i", $this->headers, $code) ) {
			return (int)$code[1];
		} else {
			return false;
		}
	}

	public function getContentEncoding()
	{
		if ( preg_match("/Content-Encoding: (.+)/i", $this->headers, $encoding) ) {
			$encoding = trim( strtolower($encoding[1]) );
		} else {
			return false;
		}
	}

	public function getHeaders()
	{
		$out = array();
		foreach ( $this->patterns as $name ) {
			if ( preg_match("/$name: (.+)/i", $this->headers, $value) ) {
				$out[$name] = trim( $value[1] );
			}
		}
		return $out;
	}

	public function getCacheControl()
	{
		$out = array();
		if ( preg_match_all("/Cache-Control: (.+)/i", $this->headers, $values, PREG_PATTERN_ORDER) ) {
			foreach ( $values[1] as $value ) {
				$directives = explode( ",", $value );
				foreach ( $directives as $directive ) {
					@list($dname, $dvalue) = explode( "=", trim($directive) );
					$out[$dname] = $dvalue;
				}
			}
		}
		return $out;
	}
}

