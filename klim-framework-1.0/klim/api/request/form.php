<?php
/**
 * Klasa kliencka API do formularzy http(s)
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


class KlimApiRequestForm extends KlimApiRequest
{
	protected $userfields = array();
	protected $method = "get";
	protected $enctype = "";
	protected $action = "";
	protected $submit_exists = false;
	protected $config;

	protected function setup( $data )
	{
		list( $provider, $method, $version, $id ) = $data;
		$config = KlimApiConfig::getForm( $provider, $method, $version, $id );

		$this->config = $config;
		$this->method = strtolower( $config["method"] );
		$this->enctype = $config["enctype"];
		$this->action = $config["url_action"];
		$this->userfields["get"] = array();
		$this->userfields["post"] = array();
		$this->userfields["file"] = array();
	}

	protected function addField( $type, $name, $value )
	{
		if ( $this->output ) {
			throw new KlimApplicationException( "tried to add $type param $name to already executed form $this->action" );
		}

		if ( $type != "get" && $this->method == "get" ) {
			throw new KlimApplicationException( "tried to add $type param $name in get mode to form $this->action" );
		} else {
			$this->userfields[$type][$name] = $value;
		}
	}

	public function addGet( $name, $value )
	{
		$this->addField( "get", $name, $value );
	}

	public function addPost( $name, $value )
	{
		$this->addField( "post", $name, $value );
	}

	public function addFile( $name, $value )
	{
		$this->addField( "file", $name, $value );
	}


	public function execute()
	{
		if ( $this->output ) {
			throw new KlimApplicationException( "tried to execute already executed form $this->action" );
		}

		if ( $this->config["get_first_url"] ) {
			$this->executeTokenized();
		} else {
			$this->executeSimple();
		}
	}

	/**
	 * Ta metoda działa tak, że:
	 *
	 *  - pobiera pierwszy adres zwykłym GET-em (parametry GET z klucza
	 *    "get_orig" wysyłane są bez modyfikacji)
	 *
	 *  - surowy wynik (html itp.) przepuszcza przez tablicę callbacków
	 *    (format: "pole" => "callback_function") - każdy callback musi
	 *    zwrócić albo false (logując błąd), albo pojedynczą wartość do
	 *    wstawienia do wysyłanego formularza
	 *
	 *  - wykonuje executeSimple(), przekazując wynikowe tablice tokenów,
	 *    oraz url - przy czym url pobiera z klienta http, zatem jeśli
	 *    nastąpiło przekierowanie http w tle, przekazany zostanie nowy
	 *    adres
	 */
	protected function executeTokenized()
	{
		$url = $this->config["url_orig"];
		$result = $this->context->http->execute( $url, $this->config["get_orig"] );

		if ( $this->context->http->getErrno() ) {
			throw new KlimRuntimeApiException( "curl internal error in url $url", $this->context->http->getError() );
		}

		$this->context->lastUrl = $this->context->http->getEffectiveUrl();

		$code = $this->context->http->getResponseCode();

		if ( $code != 200 ) {
			throw new KlimRuntimeApiException( "http code $code in url $url" );
		}

		$methods = array( "get", "post" );
		$tokens = array();

		/**
		 * Wyciągnięcie tokenów statycznych.
		 */
		foreach ( $methods as $method ) {
			$tokens[$method] = array();
			if ( !empty($this->config["tokens"][$method]) ) {
				foreach ( $this->config["tokens"][$method] as $name => $data ) {
					if ( is_array($data) ) {
						switch ( $data[0] ) {

							case "method":
								$class = $data[1];
								$file = KlimCamel::decode( $class );
								$path = str_replace( "_", "/", $file );

								if ( !include_once("$path.php") ) {
									throw new KlimApplicationException( "cannot load api response parser class $class" );
								} else if ( !method_exists($class, $data[2]) ) {
									throw new KlimApplicationException( "token $name requires unknown method, $url" );
								} else {
									$tokens[$method][$name] = call_user_func( array($class, $data[2]), $result );
								}
								break;

							case "mask":
								if ( !preg_match($data[1], $result, $ret) ) {
									throw new KlimRuntimeApiException( "mask for token $name not matching, $url" );
								} else if ( !isset($data[2]) || !isset($ret[$data[2]]) ) {
									throw new KlimApplicationException( "invalid regular expression index" );
								} else {
									$tokens[$method][$name] = $ret[$data[2]];
								}
								break;

							default:
								throw new KlimApplicationException( "unknown token $name type, $url" );
						}
					}
				}
			}
		}

		/**
		 * Wykrycie i wyciągnięcie tokenów dynamicznych.
		 */
		if ( $this->config["discover_tokens"] ) {
			$id = $this->config["form_id"];
			$arr = KlimApiParserDom::parse( $result );
			$tree = KlimApiParserDom::analyze( $arr, array("form") );

			if ( isset($tree["form"][$id]["input"]) ) {
				foreach ( $tree["form"][$id]["input"] as $input ) {
					$attr = $input["_attr"];
					if ( strtolower(@$attr["type"]) == "hidden" && !empty($attr["name"]) && !empty($attr["value"]) ) {
						$tokens[$this->method][$attr["name"]] = $attr["value"];
					}
				}
			}
		}

		$this->executeSimple( $tokens, $this->context->lastUrl );
	}

	/**
	 * GET
	 *   - niedopuszczalne post/file
	 *   - addGet - parametry muszą być walidowane przeciwko tablicy konfiguracyjnej + tablicy get_action
	 *
	 * POST
	 *   - dopuszczalne post/file
	 *   - addGet - parametry nie muszą być walidowane
	 *   - addPost - parametry muszą być walidowane przeciwko tablicy konfiguracyjnej
	 *   - addFile - parametry muszą być walidowane przeciwko liście pól input type=file
	 *
	 * WSPÓLNE
	 *   - do get wchodzą parametry domyślne z tablicy get_action
	 *   - do $this->method wchodzą parametry domyślne z tablicy konfiguracyjnej
	 */
	protected function executeSimple( $tokens = array(), $referer = false )
	{
		/**
		 * Lista pól z konfiguracji formularza
		 */
		$cfgfields = empty($this->config["fields"]) ? false : $this->config["fields"];

		/**
		 * Kontener na docelową tablicę parametrów formularza
		 * (domyślnie przyjmuje parametry zakodowane w "action")
		 */
		$allfields = array();
		$allfields["get"] = $this->config["get_action"];

		/**
		 * Lista pól, do których wchodzą konkretne wartości - są
		 * one pomijane przy przydzielaniu wartości domyślnych.
		 */
		$set = array();

		/**
		 * Tworzymy docelową tablicę pól do wysłania. Faza 1:
		 * walidujemy wszystkie pola dodane przez aplikację pod kątem tego,
		 * czy występują w definicji formularza - jeśli nie, zgłaszamy błąd.
		 */
		if ( $this->method == "get" ) {
			foreach ( $this->userfields["get"] as $name => $value ) {

				if ( isset($this->config["get_action"][$name]) ) {
					$allfields["get"][$name] = $value;
					$set[] = $name;
					continue;
				}

				if ( $this->validateUserField($cfgfields, $name, $value) ) {
					$allfields["get"][$name] = $value;
					$set[] = $name;
					continue;
				}

				if ( isset($this->config["optional"]["get"]) && in_array($name, $this->config["optional"]["get"], true) ) {
					$allfields["get"][$name] = $value;
					$set[] = $name;
					continue;
				}

				throw new KlimApplicationException( "invalid get field: $name" );
			}

		} else {
			foreach ( $this->userfields["get"] as $name => $value ) {

				if ( isset($this->config["get_action"][$name]) ) {
					$allfields["get"][$name] = $value;
					$set[] = $name;
					continue;
				}

				if ( isset($this->config["optional"]["get"]) && in_array($name, $this->config["optional"]["get"], true) ) {
					$allfields["get"][$name] = $value;
					$set[] = $name;
					continue;
				}

				throw new KlimApplicationException( "invalid get field: $name" );
			}

			foreach ( $this->userfields["post"] as $name => $value ) {

				if ( $this->validateUserField($cfgfields, $name, $value) ) {
					$allfields["post"][$name] = $value;
					$set[] = $name;
					continue;
				}

				if ( isset($this->config["optional"]["post"]) && in_array($name, $this->config["optional"]["post"], true) ) {
					$allfields["post"][$name] = $value;
					$set[] = $name;
					continue;
				}

				throw new KlimApplicationException( "invalid post field: $name" );
			}

			// http://curl.haxx.se/libcurl/php/examples/httpfileupload.html
			foreach ( $this->userfields["file"] as $name => $value ) {
				if ( isset($cfgfields["file"][$name]) && file_exists($value) ) {
					$allfields["post"][$name] = "@" . $value;
					$set[] = $name;
				} else {
					throw new KlimApplicationException( "invalid file field: $name" );
				}
			}
		}

		/**
		 * Faza 2: przelatujemy tablicę $cfgfields i ustawiamy dla wszystich
		 * znalezionych zmiennych (poza tymi w $set) wartości domyślne.
		 */
		$this->fillDefaultFields( $set, $this->method, $cfgfields, $allfields );

		/**
		 * Faza 3: dodajemy tokeny.
		 */
		if ( !empty($tokens) ) {
			foreach ( $tokens["get"] as $name => $value ) {
				if ( !in_array($name, $set, true) ) {
					$allfields["get"][$name] = $value;
				}
			}
			foreach ( $tokens["post"] as $name => $value ) {
				if ( !in_array($name, $set, true) ) {
					$allfields["post"][$name] = $value;
				}
			}
		}

		/**
		 * Jeśli przypadkiem tak się stało, że nie ma żadnych pól do wysłania
		 * POST (czyli m.in. lista pól w konfiguracji formularza jest pusta),
		 * wymuszamy tryb formularza GET.
		 */
		if ( empty($allfields["post"]) ) {
			$this->method = "get";
		}

		/**
		 * Pierwszy url - referer dla właściwego formularza. Jeśli podano go
		 * w parametrze, przyjmujemy ten parametr - w przypadku ściągania
		 * strony z tokenem mogło nastąpić przekierowanie http na inny adres,
		 * który może zostać sprawdzony przez aplikację odbierającą formularz.
		 */
		if ( !$referer ) {
			$referer = KlimHttpRequest::buildUrl( $this->config["url_orig"], $this->config["get_orig"] );
		}

		$this->context->http->setReferer( $referer );

		/**
		 * Wykonujemy request do drugiego, docelowego urla.
		 */
		if ( $this->method == "get" ) {
			$result = $this->context->http->execute( $this->action, $allfields["get"] );
		} else if ( empty($this->userfields["file"]) ) {
			$result = $this->context->http->execute( $this->action, $allfields["get"], $allfields["post"], KlimHttp::POST_FORM );
		} else {
			$result = $this->context->http->execute( $this->action, $allfields["get"], $allfields["post"], KlimHttp::POST_BINARY );
		}

		$this->context->http->setReferer( false );

		if ( $this->context->http->getErrno() ) {
			throw new KlimRuntimeApiException( "curl internal error in form $this->action", $this->context->http->getError() );
		}

		$this->context->lastUrl = $this->context->http->getEffectiveUrl();

		$code = $this->context->http->getResponseCode();

		if ( $code != 200 ) {
			throw new KlimRuntimeApiException( "http code $code in form $this->action" );
		}

		$this->output = $result;
	}

	/**
	 * Metoda sprawdzająca, czy przekazane pole (które zostało dodane metodą
	 * addGet lub addPost) znajduje się w konfiguracji pól formularza.
	 *
	 * W przypadku pól o zamkniętej liście wartości (radio itp.), sprawdzana
	 * jest także wartość i jeśli nie pasuje ona do żadnej z dopuszczalnych
	 * wartości, pole jest uważane za nieprzekazane.
	 */
	protected function validateUserField( $cfgfields, $name, $value )
	{
		if ( isset($cfgfields["text"][$name]) || isset($cfgfields["const"][$name]) ) {
			return true;
		}

		if ( isset($cfgfields["submit"][$name]) || isset($cfgfields["image"][$name]) ) {
			$this->submit_exists = true;
			return true;
		}

		/**
		 * Ten kod umożliwia przekazywanie tylko pojedynczych wartości
		 * dla checkboxów i select multiple.
		 */
		$multi = array( "radio", "checkbox", "select" );
		foreach ( $multi as $type ) {
			if ( isset($cfgfields[$type][$name]) ) {
				foreach ( $cfgfields[$type][$name] as $option ) {
					if ( (string)$value === (string)$option[0] ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Metoda wypełniająca wszystkie pola formularza, które nie zostały
	 * przekazane przez aplikację, wartościami domyślnymi.
	 */
	protected function fillDefaultFields( $set, $method, $cfgfields, &$allfields )
	{
		if ( !$this->submit_exists ) {
			$found = false;
			if ( !empty($cfgfields["submit"]) ) {
				foreach ( $cfgfields["submit"] as $name => $value ) {
					$allfields[$method][$name] = $value;
					$found = true;
					break;
				}
			}

			if ( !$found && !empty($cfgfields["image"]) ) {
				foreach ( $cfgfields["image"] as $name => $value ) {
					$allfields[$method][$name] = $value;
					break;
				}
			}
		}

		if ( !empty($cfgfields["const"]) ) {
			foreach ( $cfgfields["const"] as $name => $value ) {
				if ( !in_array($name, $set, true) ) {
					$allfields[$method][$name] = $value;
				}
			}
		}


		if ( !empty($cfgfields["text"]) ) {
			foreach ( $cfgfields["text"] as $name => $data ) {
				if ( !in_array($name, $set, true) ) {
					$allfields[$method][$name] = $data[0];
				}
			}
		}

		/**
		 * W przypadku radio nie powinno być problemów - mogą natomiast się
		 * pojawić dla checkboxów i select multi, gdy domyślnie wybrany jest
		 * więcej niż 1 element - tutaj zostanie wysłany tylko pierwszy z
		 * zaznaczonych, zamiast wszystkich.
		 */
		$multi = array( "radio", "checkbox", "select" );
		foreach ( $multi as $type ) {
			if ( !empty($cfgfields[$type]) ) {
				foreach ( $cfgfields[$type] as $name => $options ) {
					if ( !in_array($name, $set, true) ) {
						foreach ( $options as $option ) {
							if ( $option[1] ) {
								$allfields[$method][$name] = $option[0];
								break;
							}
						}
					}
				}
			}
		}
	}
}

