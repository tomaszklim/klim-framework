<?php
/**
 * Klasa bazowa dla implementacji poszczególnych dostawców API
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


abstract class KlimApiClient
{
	protected $context;
	protected $wsdl = false;
	protected $soapClient = false;
	protected $session = false;
	protected $sessionClass = false;  // klasa do obsługi logowania i sesji

	public function __construct( $context )
	{
		$this->context = $context;

		if ( $this->sessionClass )
		{
			$this->session = $this->context->attachSession( $this->sessionClass );
			$this->soapClient = $this->session->getSoapClient();
		}
		else if ( $this->wsdl )
		{
			$this->soapClient = $this->context->initSoap( $this->wsdl );
		}
		else
		{
			$this->context->initHttp();
		}
	}

	public function __call( $method, $args )
	{
		$method2 = "session_$method";

		if ( !method_exists($this, $method2) ) {
			throw new KlimApplicationException( "unknown method $method" );
		}

		if ( !$this->session->checkLogin() ) {
			$reason = $this->session->getReason();
			throw new KlimRuntimeApiLoginException( "cannot login before executing method $method: $reason" );
		}

		$ret = call_user_func_array( array($this, $method2), $args );

		$this->session->notifyRequest();
		return $ret;
	}
}

