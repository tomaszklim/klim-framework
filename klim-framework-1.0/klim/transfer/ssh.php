<?php
/**
 * Klient do transferu plików, protokół SSH+SCP, wersja natywna
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


/**
 * Ten klient wymaga rozszerzenia php-ssh2:
 *    http://pecl.php.net/package/ssh2
 */
class KlimTransferSsh extends KlimTransfer
{
	private $host;
	private $port;
	private $username;
	private $password;

	public function __construct( $settings )
	{
		$this->host = $settings["host"];
		$this->port = $settings["port"];
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

	public function connect()
	{
		if ( $this->connection ) {
			return true;
		}

		$this->connection = ssh2_connect( $this->host, $this->port );

		if ( !$this->connection ) {
			throw new KlimRuntimeTransferException( "cannot connect to ssh://$this->username@$this->host:$this->port" );
		}

		if ( !ssh2_auth_password($this->connection, $this->username, $this->password) ) {
			$this->connection = false;
			throw new KlimRuntimeTransferException( "cannot login as ssh://$this->username@$this->host:$this->port" );
		}

		return true;
	}

	protected function execute( $command )
	{
		$command .= " 2>&1";
		$stream = ssh2_exec( $this->connection, $command );

		if ( !$stream ) {
			throw new KlimRuntimeTransferException( "error executing '$command' as ssh://$this->username@$this->host:$this->port" );
		}

		stream_set_blocking( $stream, true );
		$output = stream_get_contents( $stream );
		fclose( $stream );

		return $output;
	}

	protected function executeCheck( $remote_command, $raw = false )
	{
		$output = $this->execute( $remote_command );
		return $this->parseShell( $output );
	}

	public function listDir( $dir, $show_hidden = false )
	{
		$param = $show_hidden ? "-al" : "-l";
		$dir = KlimShell::escape( $this->remote_dir . $dir );

		$command = "ls $param $dir";
		$output = $this->execute( $command );

		return $this->parseDir( explode("\n", $output) );
	}

	public function makeDir( $dir )
	{
		$dir = KlimShell::escape( $this->remote_dir . $dir );
		return $this->executeCheck( "mkdir $dir" );
	}

	public function deleteDir( $dir, $recursive = false )
	{
		$param = $recursive ? "-rf" : "-f";
		$dir = KlimShell::escape( $this->remote_dir . $dir );
		return $this->executeCheck( "rm $param $dir" );
	}

	public function deleteFile( $file )
	{
		$remote_file = KlimShell::escape( $this->remote_dir . $file );
		return $this->executeCheck( "rm -f $remote_file" );
	}

	public function putFile( $local_file, $remote_file )
	{
		for ( $i = 0; $i < $this->retries_loops; $i++ ) {

			if ( ssh2_scp_send($this->connection, $this->local_dir.$local_file, $this->remote_dir.$remote_file) ) {
				return true;
			}

			if ( $this->retries_seconds > 0 ) {
				sleep( $this->retries_seconds );
			}
		}

		throw new KlimRuntimeTransferException( "cannot upload file $local_file to ssh://$this->username@$this->host:$this->port" );
	}

	public function getFile( $remote_file, $local_file )
	{
		for ( $i = 0; $i < $this->retries_loops; $i++ ) {

			if ( ssh2_scp_recv($this->connection, $this->remote_dir.$remote_file, $this->local_dir.$local_file) ) {
				return true;
			}

			if ( $this->retries_seconds > 0 ) {
				sleep( $this->retries_seconds );
			}
		}

		throw new KlimRuntimeTransferException( "cannot download file $remote_file from ssh://$this->username@$this->host:$this->port" );
	}

	public function rename( $oldname, $newname )
	{
		$oldname = KlimShell::escape( $this->remote_dir . $oldname );
		$newname = KlimShell::escape( $this->remote_dir . $newname );
		return $this->executeCheck( "mv -f $oldname $newname" );
	}
}

