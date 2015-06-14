<?php
/**
 * Cache do danych oparty o moduł eAccelerator
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


class KlimCacheDriverEaccelerator extends KlimCache
{
	protected function rawGet( $key )
	{
		return eaccelerator_get( $key );
	}

	protected function rawSet( $key, $value, $period )
	{
		return eaccelerator_put( $key, $value, $period );
	}

	protected function rawDelete( $key )
	{
		return eaccelerator_rm( $key );
	}

	public function clean()
	{
		eaccelerator_gc();
		return true;
	}
}

