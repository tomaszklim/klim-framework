<?php
/**
 * Klasa bazowa dla implementacji poszczególnych protokołów
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


abstract class KlimApiRequest
{
	protected $context;
	protected $output = false;

	public function __construct( $context, $data )
	{
		$this->context = $context;
		$this->setup( $data );
	}

	abstract protected function setup( $data );

	abstract public function execute();

	public function getOutput()
	{
		return $this->output;
	}
}

