<?php
/**
 * Implementacja bazowych wyjątków dla aplikacji
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


// zdarzenie związane z bezpieczeństwem aplikacji
// (osobna, nieprzechwytywalna linia wyjątków)
class SecurityException extends BootstrapException
{
	protected $facility = "security";
}


// wyjątek bazowy dla linii przechwytywalnych wyjątków
class KlimException extends Exception
{
	protected $facility = "core";

	public function __construct( $message = "", $resource = false )
	{
		parent::__construct( $message );
		KlimLogger::error( $this->facility, $message, $resource );
	}
}

// błąd w aplikacji (np. brak pilnowania stanu transakcji
// i odpalanie zapytań "na pałę") lub jej konfiguracji (brak
// podanego pola w tabeli, nieznana baza danych)
class KlimApplicationException extends KlimException
{
}

// zdarzenie losowe typu przerwanie połączenia bazą danych,
// koniec miejsca na dysku, brak uprawnień itp.
class KlimRuntimeException extends KlimException
{
}

// zdarzenie losowe, ograniczone do bazy danych
class KlimRuntimeDatabaseException extends KlimRuntimeException
{
	protected $facility = "db";
}

// zdarzenie losowe, ograniczone do klas transportowych
class KlimRuntimeTransferException extends KlimRuntimeException
{
	protected $facility = "transfer";
}

// zdarzenie losowe, ograniczone do problemów z łącznością
// ze stronami zewnętrznymi poprzez http lub https
class KlimRuntimeApiException extends KlimRuntimeException
{
	protected $facility = "api";
}

// zdarzenie losowe, ograniczone do problemów z logowaniem
// do stron zewnętrznych wskutek złych danych do logowania
class KlimRuntimeApiLoginException extends KlimRuntimeApiException
{
}

