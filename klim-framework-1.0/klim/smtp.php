<?php
/**
 * Klasa do wysyłania maili (wrapper na phpmailer-2.3)
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


class KlimSmtp
{
	protected $mailer;
	protected $debug = false;
	protected $sent = false;

	public function __construct( $credentials = false )
	{
		Bootstrap::addLibrary("phpmailer-5.2.14-patched");
		require_once "class.phpmailer.php";
		require_once "class.smtp.php";

		$this->mailer = new PHPMailer();
		$this->mailer->setLanguage( "en" );
		$this->mailer->CharSet = "UTF-8";

		if ( !is_array($credentials) || empty($credentials) ) {
			$this->mailer->isSendmail();
		} else {
			$this->mailer->isSMTP();
			$this->mailer->Host = $credentials["host"];
			$this->mailer->Port = $credentials["port"];

			$types = array( "ssl", "tls" );
			if ( !empty($credentials["secure"]) && in_array($credentials["secure"], $types, true) ) {
				$this->mailer->SMTPSecure = $credentials["secure"];
			}

			if ( !empty($credentials["username"]) ) {
				$this->mailer->SMTPAuth = true;
				$this->mailer->Username = $credentials["username"];

				if ( !empty($credentials["password"]) ) {
					$this->mailer->Password = $credentials["password"];
				} else if ( !empty($credentials["entity"]) ) {
					$this->mailer->Password = KlimPassword::getFromFile( $credentials["entity"], $credentials["username"] );
				} else {
					throw new KlimApplicationException( "smtp-auth password/entity not given" );
				}
			}
		}
	}

	public function __destruct()
	{
		if ( !$this->sent ) {
			KlimLogger::debug( "smtp", "sending email from destructor" );
			$this->send();
		}
	}

	public function debugFailures( $enable = true )
	{
		$this->debug = $enable;
	}

	public function setClientIp( $ip )
	{
		$this->mailer->addCustomHeader( "X-IP: $ip" );
	}

	public function setConfirmReadingTo( $addr )
	{
		$addr = preg_replace( "/\s+/", "", $addr );
		$this->mailer->ConfirmReadingTo = $addr;
	}

	public function setFrom( $addr, $name = false )
	{
		$addr = preg_replace( "/\s+/", "", $addr );
		$name = $name ? preg_replace("/\s+/", " ", $name) : $addr;
		$this->mailer->Sender = $addr;
		$this->mailer->From = $addr;
		$this->mailer->FromName = $name;
	}

	public function addReplyTo( $addr, $name = false )
	{
		$addr = preg_replace( "/\s+/", "", $addr );
		$name = $name ? preg_replace("/\s+/", " ", $name) : $addr;
		$this->mailer->addReplyTo( $addr, $name );
	}

	public function addCC( $addr, $name = false )
	{
		$addr = preg_replace( "/\s+/", "", $addr );
		$name = $name ? preg_replace("/\s+/", " ", $name) : $addr;
		$this->mailer->addCC( $addr, $name );
	}

	public function addBCC( $addr, $name = false )
	{
		$addr = preg_replace( "/\s+/", "", $addr );
		$name = $name ? preg_replace("/\s+/", " ", $name) : $addr;
		$this->mailer->addBCC( $addr, $name );
	}

	public function addRecipient( $addr, $name = false )
	{
		$addr = preg_replace( "/\s+/", "", $addr );
		$name = $name ? preg_replace("/\s+/", " ", $name) : $addr;
		$this->mailer->addAddress( $addr, $name );
	}

	public function addHeader( $header, $value = false )
	{
		$header = preg_replace( "/\s+/", " ", $header );
		if ( $value ) {
			$header .= ":" . preg_replace( "/\s+/", " ", $value );
		}
		$this->mailer->addCustomHeader( $header );
	}

	public function addAttachment( $file, $name = false )
	{
		if ( $name ) {
			$name = preg_replace( "/\s+/", " ", $name );
		}
		$this->mailer->addAttachment( $file, $name );
	}

	public function addEmbeddedImage( $file, $cid, $name = false )
	{
		if ( $name ) {
			$name = preg_replace( "/\s+/", " ", $name );
		}
		$this->mailer->addEmbeddedImage( $file, $cid, $name );
	}

	public function setPriority( $priority )
	{
		$this->mailer->Priority = (int)$priority;
	}

	public function setSubject( $subject )
	{
		$subject = preg_replace( "/\s+/", " ", $subject );
		$this->mailer->Subject = $subject;
	}

	public function setBody( $text, $html = false, $basedir = false )
	{
		if ( $basedir ) {
			$this->mailer->MsgHTML( $html, $basedir );
		} else if ( $html ) {
			$this->mailer->Body = $html;
			$this->mailer->AltBody = $text;
			$this->mailer->IsHTML( true );
		} else {
			$this->mailer->Body = $text;
			$this->mailer->IsHTML( false );
		}
	}

	public function sign( $cert, $key, $password )
	{
		$this->mailer->Sign( $cert, $key, $password );
	}

	public function send()
	{
		if ( $this->sent ) {
			throw new KlimApplicationException( "another explicit try to send already sent email", $resource );
		}

		$this->sent = true;
		if ( $this->mailer->send() ) {
			return true;
		}

		if ( $this->debug ) {
			$this->dumpFailure();
		}

		$resource = $this->mailer->ErrorInfo;
		if ( !is_null($this->mailer->ErrorResource) ) {
			$resource .= "\n" . str_replace( "\r\n", "\n", $this->mailer->ErrorResource );
		}

		throw new KlimRuntimeException( "message could not be sent, mailer error attached", $resource );
	}

	protected function dumpFailure()
	{
		$pid = getmypid();
		$date = date( "Ymd.His" );
		$code = "unknown";

		if ( !empty($this->mailer->ErrorCode) ) {
			$code = $this->mailer->ErrorCode;
		}

		$file = Bootstrap::getApplicationRoot() . "/cache/smtp_failures/$code.$date.$pid";

		@file_put_contents( "$file.dump", serialize($this->mailer) );
		@file_put_contents( "$file.debug", print_r($this->mailer, true) );
	}
}

