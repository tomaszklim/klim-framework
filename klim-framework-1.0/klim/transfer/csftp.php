<?php
/**
 * Klient do transferu plików, protokół SFTP, wersja CLI
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


class KlimTransferCsftp extends KlimTransfer
{

	public function __construct( $settings )
	{
		$this->retries_loops = $settings["retries_loops"];
		$this->retries_seconds = $settings["retries_seconds"];
		$this->connection = $settings["username"] . "@" . $settings["host"] . ":" . $settings["remote_dir"];
		$this->remote_dir = $settings["remote_dir"];
		$this->local_dir = $settings["local_dir"];

		if ( substr($this->local_dir, -1) != "/" ) {
			$this->local_dir .= "/";
		}
	}

	protected function executeCheck( $remote_command, $raw = false )
	{
		$command = $raw ? $remote_command : "echo '$remote_command' | sftp $this->connection";
		$output = $this->execute( $command );
		return $this->parseShell( $output );
	}

	public function connect()
	{
		return $this->executeCheck( "pwd" );
	}

	public function listDir( $dir, $show_hidden = false )
	{
		$param = $show_hidden ? "-al" : "-l";
		$dir = KlimShell::escape( $dir );

		$command = "echo 'ls $param $dir' | sftp $this->connection";
		$output = $this->execute( $command );

		return $this->parseDir( explode("\n", $output) );
	}

	public function makeDir( $dir )
	{
		$dir = KlimShell::escape( $dir );
		return $this->executeCheck( "mkdir $dir" );
	}

	public function deleteDir( $dir, $recursive = false )
	{
		$param = $recursive ? "-rf" : "-f";
		$dir = KlimShell::escape( $dir );
		return $this->executeCheck( "rm $param $dir" );
	}

	public function deleteFile( $file )
	{
		$remote_file = KlimShell::escape( $file );
		return $this->executeCheck( "rm -f $remote_file" );
	}

	public function putFile( $local_file, $remote_file )
	{
		$local_file = KlimShell::escape( $this->local_dir . $local_file );
		$remote_file = KlimShell::escape( $remote_file );
		return $this->executeCheckRetry( "put $local_file $remote_file" );
	}

	public function getFile( $remote_file, $local_file )
	{
		$local_file = KlimShell::escape( $this->local_dir . $local_file );
		$remote_file = KlimShell::escape( $remote_file );
		return $this->executeCheckRetry( "get $remote_file $local_file" );
	}

	public function rename( $oldname, $newname )
	{
		$oldname = KlimShell::escape( $oldname );
		$newname = KlimShell::escape( $newname );
		return $this->executeCheck( "rename $oldname $newname" );
	}
}

