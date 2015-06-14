<?php
/**
 * Klasa do dekodowania nagłówków MIME
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
 * @author Terence Yim <chtyim@gmail.com>
 * @author Tomasz Klim <framework@tomaszklim.com>
 * @license http://www.gnu.org/copyleft/gpl.html
 */


class KlimMime
{
	/**
	 * Ta metoda wykrywa i dekoduje ciągi tekstowe potencjalnie zakodowane
	 * w standardzie MIME, po czym wykrywa kodowanie znaków diakrytycznych
	 * w zdekodowanym tekście i konwertuje je na podaną metodę kodowania.
	 * Jeśli nie można wykryć metody kodowania znaków, przyjmowane jest
	 * ISO-8859-2.
	 */
	public static function decodeHeader( $str, $output_encoding )
	{
		if ( preg_match("/.*=\?(.*)\?/iU", $str, $matches) ) {
			$encoding = $matches[1];
		} else {
			$encoding = "iso-8859-2";
		}

		while ( preg_match("/(.*)=\?.*\?q\?(.*)\?=(.*)/i", $str, $matches) ) {
			$str = str_replace( "_", " ", $matches[2] );
			$str = $matches[1] . quoted_printable_decode($str) . $matches[3];
		}

		while ( preg_match("/=\?.*\?b\?.*\?=/i", $str, $matches) && strpos($str, "'") === false ) {
			$str = preg_replace( "/(.*)=\?.*\?b\?(.*)\?=(.*)/ie", "'$1'.base64_decode('$2').'$3'", $str );
		}

		if ( empty($encoding) || !strcasecmp($encoding, "iso-8859-1") ) {
			$encoding = "iso-8859-2";
		} else if ( !strcasecmp($encoding, "x-user-defined") ) {
			$encoding = "utf-8";
		}

		if ( strcasecmp($encoding, $output_encoding) ) {
			$str = iconv( $encoding, $output_encoding . "//TRANSLIT", $str );
		}

		return $str;
	}

	/**
	 * Ta metoda rozpoznaje nazwę i adres mailowy z nagłówka From,
	 * Sender itp. Rozpoznawana jest większość wariacji form:
	 *   Nazwa <adres>
	 *   <adres> (Nazwa)
	 *
	 * Jeśli nie uda się rozpoznać żadnej formy, podany ciąg tekstowy
	 * kopiowany jest do obu pól - nazwy i adresu - bez dodatkowej
	 * weryfikacji.
	 *
	 * TODO: Metoda nie radzi sobie z nagłówkami zawierającymi po kilka
	 * adresów. Docelowo można obsługę takich nagłówków zaimplementować
	 * wykonując explode() po przecinku i średniku, jednak trzeba będzie
	 * zmienić format zwracanych danych.
	 */
	public static function decodeSender( $sender )
	{
		$pat1 = "\w\-=!#$%^*'+\\.={}|?~ąęłóżćśńó";
		$pat2 = "\w\-=!#$%^*'+\\={}|?~ąęłóżćśńó";

		if ( preg_match("/(['|\"])?(.*)(?(1)['|\"]) <([$pat1]+@[$pat1]+[$pat2])>/", $sender, $matches) ) {
			// Match address in the form: Name <email@host>
			$result["name"] = $matches[2];
			$result["email"] = $matches[count($matches) - 1];
		} elseif ( preg_match("/([$pat1]+@[$pat1]+[$pat2]) \((.*)\)/", $sender, $matches) ) {
			// Match address in the form: email@host (Name)
			$result["email"] = $matches[1];
			$result["name"] = $matches[2];
		} else {
			// Only the email address present
			$result["name"] = $sender;
			$result["email"] = $sender;
		}

		$result["name"] = str_replace( array('"', "'") , "", $result["name"] );
		return $result;
	}

	/**
	 * Ta metoda dekoduje treść maila/posta (treść główną lub załącznik).
	 */
	public static function decodeContent( $content, $content_type, $transfer_encoding, $output_encoding )
	{
		if ( stripos($transfer_encoding, "quoted-printable") !== false )
			$decoded = quoted_printable_decode( $content );
		else if ( stripos($transfer_encoding, "base64") !== false )
			$decoded = base64_decode( $content );
		else if ( stripos($transfer_encoding, "uuencode") !== false )
			$decoded = self::uudecode( $content );
		else
			$decoded = $content;

		if ( stripos($content_type, "text") !== false ) {

			if ( !preg_match("/charset=(.*)/i", $content_type, $res) ) {
				$charset = "iso-8859-2";
			} else {
				$charset = str_replace( array("'", '"'), "", $res[1] );
				if ( $pos = strpos($charset, ";") ) {
					$charset = substr( $charset, 0, $pos );
				}
				if ( empty($charset) || !strcasecmp($charset, "iso-8859-1") ) {
					$charset = "iso-8859-2";
				}
			}

			if ( strcasecmp($charset, $output_encoding) ) {
				$decoded = iconv( $charset, $output_encoding . "//TRANSLIT", $decoded );
			}
		}

		return $decoded;
	}

	/**
	 * Dekoduje załączniki kodowane metodą uuencode.
	 */
	protected static function uudecode( $in )
	{
		$out = "";
		$lines = preg_split( "/\r?\n/", $in );

		foreach ( $lines as $line ) {
			$len = ord( $line{0} );
			if ( $len < 0x20 || $len > 0x5f ) {
				break;
			}
			$len = $len - 0x20;
			$temp = $len;
			$new_len = strlen($out) + $len;

			$i = 1;
			$tmp_out = "";
			while ( $temp > 0 ) {
				$tmp_out .= chr(((ord($line{$i}) - 0x20) << 2) & 0xFC | ((ord($line{$i + 1}) - 0x20) >> 4) & 0x03);
				$tmp_out .= chr(((ord($line{$i + 1}) - 0x20) << 4) & 0xF0 | ((ord($line{$i + 2}) - 0x20) >> 2) & 0x0F);
				$tmp_out .= chr(((ord($line{$i + 2}) - 0x20) << 6) & 0xC0 | (ord($line{$i + 3}) - 0x20) & 0x3F);
				$temp -= 3;
				$i += 4;
			}
			$out .= substr( $tmp_out, 0, $len );
		}

		return $out;
	}
}

