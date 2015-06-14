<?php
/**
 * Klient do transferu plików, protokół SSH+SCP, wersja CLI
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


class KlimTransferCssh extends KlimTransfer
{

	public function __construct( $settings )
	{
		$this->retries_loops = $settings["retries_loops"];
		$this->retries_seconds = $settings["retries_seconds"];
		$this->connection = $settings["username"] . "@" . $settings["host"];
		$this->remote_dir = $settings["remote_dir"];
		$this->local_dir = $settings["local_dir"];

		if ( substr($this->remote_dir, -1) != "/" ) {
			$this->remote_dir .= "/";
		}

		if ( substr($this->local_dir, -1) != "/" ) {
			$this->local_dir .= "/";
		}
	}

	protected function executeCheck( $remote_command, $raw = false )
	{
		$command = $raw ? $remote_command : "ssh $this->connection $remote_command";
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
		$dir = KlimShell::escape( $this->remote_dir . $dir );

		$command = "ssh $this->connection ls $param $dir";
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
		$local_file = KlimShell::escape( $this->local_dir . $local_file );
		$remote_file = KlimShell::escape( $this->remote_dir . $remote_file );
		return $this->executeCheckRetry( "scp $local_file $this->connection:$remote_file", true );
	}

	public function getFile( $remote_file, $local_file )
	{
		$local_file = KlimShell::escape( $this->local_dir . $local_file );
		$remote_file = KlimShell::escape( $this->remote_dir . $remote_file );
		return $this->executeCheckRetry( "scp $this->connection:$remote_file $local_file", true );
	}

	public function rename( $oldname, $newname )
	{
		$oldname = KlimShell::escape( $this->remote_dir . $oldname );
		$newname = KlimShell::escape( $this->remote_dir . $newname );
		return $this->executeCheck( "mv -f $oldname $newname" );
	}
}

