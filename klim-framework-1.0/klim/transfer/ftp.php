<?php
/**
 * Klient do transferu plików, protokół FTP/FTPS, wersja natywna
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


class KlimTransferFtp extends KlimTransfer
{
	private $ssl;
	private $host;
	private $port;
	private $passive;
	private $timeout;
	private $username;
	private $password;

	public function __construct( $settings )
	{
		$this->ssl = $settings["ssl"];
		$this->host = $settings["host"];
		$this->port = $settings["port"];
		$this->passive = $settings["passive"];
		$this->timeout = $settings["timeout"];
		$this->username = $settings["username"];
		$this->password = $settings["password"];
		$this->retries_loops = $settings["retries_loops"];
		$this->retries_seconds = $settings["retries_seconds"];
		$this->remote_dir = $settings["remote_dir"];
		$this->local_dir = $settings["local_dir"];

		if ( substr($this->remote_dir, -1) != "/" ) {
			$this->remote_dir .= "/";
		}

		if ( substr($this->local_dir, -1) != "/" ) {
			$this->local_dir .= "/";
		}
	}

	public function __destruct()
	{
		if ( $this->connection ) {
			ftp_close( $this->connection );
			$this->connection = false;
		}
	}

	public function connect()
	{
		if ( $this->connection ) {
			return true;
		}

		if ( $this->ssl ) {
			$this->connection = ftp_ssl_connect( $this->host, $this->port, $this->timeout );
		} else {
			$this->connection = ftp_connect( $this->host, $this->port, $this->timeout );
		}

		if ( !$this->connection ) {
			throw new KlimRuntimeTransferException( "cannot connect to ftp://$this->username@$this->host:$this->port" );
		}

		@ftp_set_option( $this->connection, FTP_TIMEOUT_SEC, $this->timeout );

		if ( !@ftp_login($this->connection, $this->username, $this->password) ) {
			$this->connection = false;
			throw new KlimRuntimeTransferException( "cannot login as ftp://$this->username@$this->host:$this->port" );
		}

		if ( $this->passive ) {
			ftp_pasv( $this->connection, true );
		}

		return true;
	}

	public function listDir( $dir, $show_hidden = false )
	{
		$items = ftp_rawlist( $this->connection, $this->remote_dir . $dir );
		return $items ? $this->parseDir($items) : false;
	}

	public function makeDir( $dir )
	{
		return ftp_mkdir( $this->connection, $this->remote_dir . $dir );
	}

	public function deleteDir( $dir, $recursive = false )
	{
		if ( $recursive ) {

			if ( substr($dir, -1) != "/" ) {
				$dir .= "/";
			}

			$items = $this->listDir( $dir, true );
			$ok = true;

			if ( !empty($items["dir"]) ) {
				foreach ( $items["dir"] as $item ) {
					$ret = $this->deleteDir( $dir . $item, true );
					if ( !$ret ) $ok = false;
				}
			}

			if ( !empty($items["file"]) ) {
				foreach ( $items["file"] as $item ) {
					$ret = $this->deleteFile( $dir . $item );
					if ( !$ret ) $ok = false;
				}
			}

			if ( !$ok ) {
				return false;
			}
		}

		return ftp_rmdir( $this->connection, $this->remote_dir . $dir );
	}

	public function deleteFile( $file )
	{
		return ftp_delete( $this->connection, $this->remote_dir . $file );
	}

	public function putFile( $local_file, $remote_file )
	{
		for ( $i = 0; $i < $this->retries_loops; $i++ ) {

			if ( ftp_put($this->connection, $this->remote_dir.$remote_file, $this->local_dir.$local_file, FTP_BINARY) ) {
				return true;
			}

			if ( $this->retries_seconds > 0 ) {
				sleep( $this->retries_seconds );
			}
		}

		throw new KlimRuntimeTransferException( "cannot upload file $local_file to ftp://$this->username@$this->host:$this->port" );
	}

	public function getFile( $remote_file, $local_file )
	{
		for ( $i = 0; $i < $this->retries_loops; $i++ ) {

			if ( ftp_get($this->connection, $this->local_dir.$local_file, $this->remote_dir.$remote_file, FTP_BINARY) ) {
				return true;
			}

			if ( $this->retries_seconds > 0 ) {
				sleep( $this->retries_seconds );
			}
		}

		throw new KlimRuntimeTransferException( "cannot download file $remote_file from ftp://$this->username@$this->host:$this->port" );
	}

	public function rename( $oldname, $newname )
	{
		$oldname = $this->remote_dir . $oldname;
		$newname = $this->remote_dir . $newname;
		return ftp_rename( $this->connection, $oldname, $newname );
	}
}

