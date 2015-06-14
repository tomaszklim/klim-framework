<?php
/**
 * Klasa do generowania kodu PHP
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


class KlimExport
{
	public static function generate( $var, $name, $global = false, $force_multiline = false, $quote_char = "'" )
	{
		$content = $global ? "global \$$name;\n" : "";
		$code = self::generateCode( $var, 0, $force_multiline, $quote_char );
		$content .= "\$$name = " . $code[1] . ";\n\n";
		return $content;
	}

	protected static function generateCode( $var, $level, $force_multiline = false, $quote_char = "'" )
	{
		if ( is_numeric($var) || is_float($var) ) {
			return array( false, "$var" );
		}

		if ( is_bool($var) ) {
			return array( false, $var ? "true" : "false" );
		}

		if ( is_null($var) ) {
			return array( false, "null" );
		}

		if ( is_string($var) ) {
			$pos = strpos( $var, "\n" );
			$esc = str_replace( $quote_char, "\\".$quote_char, $var );
			return array( $pos, $quote_char.$esc.$quote_char );
		}

		if ( is_array($var) ) {
			$newlevel = $level + 1;
			$parts = array();
			$multiline = false;
			$index = 0;
			$linear_indexes = true;
			foreach ( $var as $key => $value ) {

				$val2 = self::generateCode( $value, $newlevel, $force_multiline, $quote_char );
				$val_code = $val2[1];
				if ($val2[0]) $multiline = true;

				if ( !is_integer($key) || $key !== $index ) {
					$linear_indexes = false;
				}

				if ( !$linear_indexes ) {
					$key2 = self::generateCode( $key, $newlevel, $force_multiline, $quote_char );
					$key_code = $key2[1];
					if ($key2[0]) $multiline = true;

					$parts[] = "$key_code => $val_code,";
				} else {
					$parts[] = "$val_code,";
				}

				$index++;
			}

			if ( $multiline || $force_multiline ) {
				$ret = "array (\n";
				foreach ( $parts as $part ) {
					$ret .= str_repeat("\t", $newlevel) . "$part\n";
				}
				$ret .= str_repeat("\t", $level) . ")";

			} else {
				$ret = "array(";
				foreach ( $parts as $part ) {
					$ret .= " $part";
				}
				$ret .= " )";
			}

			return array( true, $ret );
		}

		return array( true, var_export($var, true) );
	}
}

