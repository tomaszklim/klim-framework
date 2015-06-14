<?php
/**
 * Klasa do buforowania wyjścia i nagłówków http dla aplikacji webowych
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


class KlimWebResponse
{
	protected static $emit = false;
	protected static $code = false;
	protected static $headers = array();
	protected static $cache = array();

	protected static $httpCodes = array (
		100 => "Continue",
		101 => "Switching Protocols",
		102 => "Processing",  // mediawiki
		200 => "OK",
		201 => "Created",
		202 => "Accepted",
		203 => "Non-Authoritative Information",
		204 => "No Content",
		205 => "Reset Content",
		206 => "Partial Content",
		207 => "Multi-Status",  // mediawiki
		300 => "Multiple choices",
		301 => "Moved Permanently",
		302 => "Found",
		303 => "See Other",
		304 => "Not Modified",
		305 => "Use Proxy",
		307 => "Temporary Redirect",
		400 => "Bad Request",
		401 => "Unauthorized",
		402 => "Payment Required",
		403 => "Forbidden",
		404 => "Not Found",
		405 => "Method Not Allowed",
		406 => "Not Acceptable",
		407 => "Proxy Authentication Required",
		408 => "Request Timeout",
		409 => "Conflict",
		410 => "Gone",
		411 => "Length Required",
		412 => "Precondition Failed",
		413 => "Request Entity Too Large",
		414 => "Request-URI Too Large",
		415 => "Unsupported Media Type",
		416 => "Requested Range Not Satisfiable",
		417 => "Expectation Failed",
		422 => "Unprocessable Entity",  // mediawiki
		423 => "Locked",  // mediawiki
		424 => "Failed Dependency",  // mediawiki
		500 => "Internal Server Error",
		501 => "Not Implemented",
		502 => "Bad Gateway",
		503 => "Service Unavailable",
		504 => "Gateway Timeout",
		505 => "HTTP version not supported",
		506 => "Variant Also Negotiates",  // rfc2295, 8.1
		507 => "Insufficient Storage",  // mediawiki
	);

	/**
	 * Dodaje nagłówek http do wyemitowania dla bieżącej podstrony.
	 */
	public static function addHeader( $data )
	{
		self::$headers[] = $data;
	}

	/**
	 * Dodaje dyrektywę do nagłówka Cache-Control dla bieżącej podstrony.
	 * Wszystkie są sklejane w pojedynczy nagłówek i emitowane razem z
	 * innymi nagłówkami. Nie należy dodawać nagłówka Cache-Control metodą
	 * addHeader(), ani w żaden inny sposób poza tą metodą.
	 */
	public static function addCacheControl( $key, $value = false )
	{
		self::$cache[$key] = $value;
	}

	/**
	 * Ustawia kod odpowiedzi http. Powoduje to fizyczną emisję 2 różnych
	 * nagłówków http - surowego HTTP/1.1 i nagłówka Status na wypadek,
	 * gdyby ta klasa była uruchamiana ze skryptu działającego w trybie
	 * CGI (wówczas sam surowy nagłówek HTTP/1.1 nie wystarcza).
	 */
	public static function setStatusCode( $code = 200 )
	{
		self::$code = $code;
	}

	/**
	 * Metoda zarządzająca i emitująca nagłówki http. Moduły aplikacji
	 * są zobowiązane do używania metod addHeader() i setStatusCode()
	 * tej klasy zamiast natywnej funkcji header(), dzięki czemu nagłówki
	 * są zbierane i łączone z nagłówkami generycznymi, np. od SEO, czy
	 * ograniczania zużycia pasma.
	 */
	public static function generateHeaders()
	{
		if ( !self::$emit ) {
			if ( self::$code && array_key_exists(self::$code, self::$httpCodes) ) {
				$hdr = self::$code . " " . self::$httpCodes[self::$code];
				@header( "HTTP/1.1 $hdr" );
				@header( "Status: $hdr" );
			}

			if ( !empty(self::$cache) ) {
				$cache = array();
				foreach ( self::$cache as $key => $value ) {
					if ( $value != "" ) {
						$cache[] = $key . "=" . $value;
					} else {
						$cache[] = $key;
					}
				}

				if ( !empty($cache) ) {
					self::$headers[] = "Cache-Control: " . implode( ", ", $cache );
				}
			}

			if ( !empty(self::$headers) ) {
				foreach ( self::$headers as $data ) {
					@header( $data );
				}
			}

			self::$emit = true;
		}
	}

	/**
	 * Metoda generująca załącznik na podstawie pliku - w zależności od
	 * rozszerzenia tego pliku generowany jest download, albo osadzenie
	 * pliku wewnątrz strony.
	 */
	public static function generateStream( $file )
	{
		$obj = new KlimFile( $file );
		$enc = urlencode( $obj->getName() );

		if ( !$obj->exists() ) {
			return false;
		}

		$date = KlimTime::getTimestamp( GMT_RFC2822, $obj->getModificationTime() );
		self::addHeader( "Last-Modified: $date" );

		if ( $mime = $obj->getContentType() ) {
			self::addHeader( "Content-Type: $mime" );
			self::addHeader( "Content-Disposition: inline; filename=\"$enc\";" );
		} else {
			self::addHeader( "Content-Description: File Transfer" );
			self::addHeader( "Content-Type: application/octet-stream" );
			self::addHeader( "Content-Disposition: attachment; filename=\"$enc\";" );
			self::addHeader( "Content-Transfer-Encoding: binary" );
		}

		if ( !empty($_SERVER["HTTP_IF_MODIFIED_SINCE"]) ) {
			$modsince = preg_replace( "/;.*$/", "", $_SERVER["HTTP_IF_MODIFIED_SINCE"] );
			$sinceTime = strtotime( $modsince );

			if ( $obj->getModificationTime() <= $sinceTime ) {
				self::setStatusCode( 304 );
				self::generateHeaders();
				return $obj->getSize();
			}
		}

		self::addHeader( "Content-Length: " . $obj->getSize() );
		self::generateHeaders();
		return @readfile( $file );
	}
}

