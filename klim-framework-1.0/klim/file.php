<?php
/**
 * Klasa do operacji na plikach
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


class KlimFile
{
	protected $filename;
	protected $inline = array (
		"txt"  => "text/plain",
		"html" => "text/html",
		"htm"  => "text/html",
		"xml"  => "text/xml",

		"pdf"  => "application/pdf",
		"doc"  => "application/msword",
		"xls"  => "application/vnd.ms-excel",
		"ppt"  => "application/vnd.ms-powerpoint",
		"ps"   => "application/postscript",
		"ai"   => "application/postscript",
		"eps"  => "application/postscript",

		"png"  => "image/x-png",
		"gif"  => "image/gif",
		"jpg"  => "image/jpeg",
		"jpeg" => "image/jpeg",
		"jpe"  => "image/jpeg",
		"bmp"  => "image/bmp",
		"tif"  => "image/tiff",
		"tiff" => "image/tiff",
		"xbm"  => "image/x-xbitmap",

		"wav"  => "audio/wav",
		"wma"  => "audio/x-ms-wma",
		"wmv"  => "video/x-ms-wmv",
		"avi"  => "video/x-msvideo",
		"mp3"  => "audio/mpeg",
		"mpg"  => "video/mpeg",
		"mpeg" => "video/mpeg",
		"mpe"  => "video/mpeg",
	);

	/*
	 * Plan przyszłego rozwoju tej klasy:
	 *
	 * - isReadable, isWritable - uprawnienia z bieżącego użytkownika
	 * - getModificationDate - i podobne daty (odświeżane co wywołanie)
	 * - odczyt i zapis (overwrite, append)
	 *
	 * http://www.php.net/manual/en/function.stat.php
	 */
	public function __construct( $filename )
	{
		$this->filename = $filename;
	}

	public function exists()
	{
		return file_exists( $this->filename );
	}

	public function getSize()
	{
		return filesize( $this->filename );
	}

	public function getModificationTime()
	{
		return filemtime( $this->filename );
	}

	public function getPath()
	{
		return dirname( $this->filename );
	}

	public function getName()
	{
		return basename( $this->filename );
	}

	public function getExtension()
	{
		return strtolower( substr( strrchr($this->filename, "."), 1 ) );
	}

	/**
	 * Ta metoda wyciąga rozszerzenie z podanej nazwy pliku, po czym
	 * zwraca mime-type przypisany do tego rozszerzenia. Jeśli nie
	 * uda się znaleźć powiązanego mime-type, zwracane jest albo false,
	 * albo domyślne "application/octet-stream", wskazujące na plik
	 * binarny do pobrania, a nie do osadzenia w renderowanej stronie.
	 */
	public function getContentType( $default = false )
	{
		$extension = $this->getExtension();

		if ( array_key_exists($extension, $this->inline) ) {
			return $this->inline[$extension];
		} else {
			return $default ? "application/octet-stream" : false;
		}
	}
}

