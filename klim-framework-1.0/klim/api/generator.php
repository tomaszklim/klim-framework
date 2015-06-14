<?php
/**
 * Klasa skanująca formularz i generująca jego konfigurację
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


class KlimApiGenerator
{
	protected function fetchUrl( $url, $tidy = false )
	{
		$http = new KlimHttp();
		// $http->setVerbose();
		$http->setFollowLocation( 1 );
		$http->setReturnHeader( 0 );
		$http->setAgent( "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.2; SV1; .NET CLR 1.1.4322)" );

		$html = $http->execute( $url );

		if ( $http->getErrno() ) {
			throw new KlimRuntimeApiException( "curl internal error in url $url", $http->getError() );
		}

		$code = $http->getResponseCode();

		if ( $code != 200 ) {
			throw new KlimRuntimeApiException( "http code $code in url $url" );
		}

		return array (
			"url" => $http->getEffectiveUrl(),
			"html" => $tidy ? KlimApiFormatter::tidy($html) : $html,
		);
	}

	/**
	 * Parsuje arraya ze spłaszczoną hierarchią znaczników formularza.
	 *
	 * Ciekawe strony do testowania tej metody:
	 *   http://www.google.com/advanced_image_search
	 */
	protected function parseFormFields( $data )
	{
		$labels = array();
		$fields = array();

		if ( isset($data["label"]) ) {
			foreach ( $data["label"] as $label ) {
				$id = $label["_attr"]["for"];

				if ( isset($label["_value"]) ) {
					$labels[$id] = $label["_value"];
				} else if ( isset($label["_attr"]["value"]) ) {
					KlimLogger::debug( "api", "label value found in _attr - check this out" );
					$labels[$id] = $label["_attr"]["value"];
				}
			}
		}

		if ( isset($data["input"]) ) {
			foreach ( $data["input"] as $input ) {
				$attr = $input["_attr"];
				if ( isset($attr["name"]) && empty($attr["disabled"]) ) {

					$name = $attr["name"];
					$value = @$attr["value"];
					$type = !empty($attr["type"]) ? $attr["type"] : "text";

					$id = @$attr["id"];
					$title_label = ( $id && isset($labels[$id]) ? $labels[$id] : false );

					switch ( $type ) {
						case "hidden":
							$fields["const"][$name] = $value;
							break;
						case "text":
						case "password":
							if ( !empty($attr["readonly"]) ) {
								$fields["const"][$name] = $value;
							} else {
								$unified_title = $title_label ? $title_label : @$attr["title"];
								$fields["text"][$name] = array( $value, (int)@$attr["maxlength"], $unified_title );
							}
							break;
						case "radio":
						case "checkbox":
							$unified_title = $title_label ? $title_label : @$input["_value"];
							$fields[$type][$name][] = array( $value, (int)isset($attr["checked"]), $unified_title );
							break;
						case "file":
							$fields["file"][] = $name;
							break;
						case "image":
							$fields["image"][$name] = array( @$attr["src"], @$attr["alt"], @$attr["width"], @$attr["height"] );
							break;
						case "submit":
							$fields["submit"][$name] = $value;
							break;
					}
				}
			}
		}

		if ( isset($data["textarea"]) ) {
			foreach ( $data["textarea"] as $textarea ) {
				$attr = $textarea["_attr"];
				if ( isset($attr["name"]) && empty($attr["disabled"]) ) {

					$name = $attr["name"];
					$value = @$textarea["_value"];

					$id = @$attr["id"];
					$unified_title = ( $id && isset($labels[$id]) ? $labels[$id] : @$attr["title"] );

					$fields["text"][$name] = array( $value, 0, $unified_title );
				}
			}
		}

		if ( isset($data["select"]) ) {
			foreach ( $data["select"] as $select ) {
				$attr = $select["_attr"];
				if ( isset($attr["name"]) && empty($attr["disabled"]) ) {
					$name = $attr["name"];
					foreach ( $select["option"] as $option ) {
						$attr = $option["_attr"];

						$value = $attr["value"];
						$title = @$option["_value"];
						$selected = (int)isset($attr["selected"]);

						$fields["select"][$name][] = array( $value, $selected, $title );
					}
				}
			}
		}

		if ( isset($data["button"]) ) {
			foreach ( $data["button"] as $button ) {
				$attr = $button["_attr"];
				if ( isset($attr["name"]) && isset($attr["type"]) && empty($attr["disabled"]) && !strcasecmp($attr["type"],"submit") ) {

					$name = $attr["name"];
					$fields["submit"][$name] = "";
				}
			}
		}

		return $fields;
	}

	/**
	 * Generuje kod PHP z konfiguracją formularza w oparciu o podaną
	 * tablicę danych dla tego formularza, adres strony, na której on
	 * wystąpił, oraz wynik metody KlimApiParserLinker::parentUrl()
	 * dla tej strony.
	 */
	protected function generateFormConfigCode( $data, $url, $parent, $provider, $provider_method, $version, $form_id )
	{
		$fields = $this->parseFormFields( $data );
		$attr = $data["_attr"];

		$enctype = strtolower( @$attr["enctype"] );

		$method = strtoupper( @$attr["method"] );
		$methods = array( "GET", "POST" );

		if ( !in_array($method, $methods, true) ) {
			$method = "GET";
		}

		$action = KlimApiParserLinker::absoluteUrl( $parent, $attr["action"] );

		list( $url1, $get1 ) = KlimApiParserLinker::extractGetParams( $url );
		list( $url2, $get2 ) = KlimApiParserLinker::extractGetParams( $action );

		/**
		 * TODO: tą funkcjonalność ewentualnie zmodyfikować/rozszerzyć
		 * w trakcie implementacji klasy korzystającej z konfiguracji
		 * formularza - oprócz samego pobrania pierwszego urla, trzeba
		 * będzie go jakoś sparsować i coś z niego wyciągnąć, np. token.
		 * Fajnie by było umieszczać konfigurację takiego parsowania
		 * również w konfiguracji samego formularza.
		 */
		$get_first = !empty( $get1 );

		$config = array (
			"provider"        => $provider,
			"provider_method" => $provider_method ,
			"version"         => $version,
			"form_id"         => $form_id,
			"get_first_url"   => $get_first,
			"discover_tokens" => false,
			"url_orig"        => $url1,
			"url_action"      => $url2,
			"get_orig"        => $get1,
			"get_action"      => $get2,
			"method"          => $method,
			"enctype"         => $enctype,
			"fields"          => $fields,
		);

		$dump = "<?php\n\n";
		$dump .= KlimExport::generate( $config, "config" );
		return $dump;
	}

	public function execute( $url, $provider, $method, $version, $htmlfile = false )
	{
		if ( !$htmlfile ) {
			$data = $this->fetchUrl( $url, false );

			if ( !empty($data["url"]) ) {
				$url = $data["url"];
			}

			$html = $data["html"];
		} else {
			$html = file_get_contents( $htmlfile );
		}

		$arr = KlimApiParserDom::parse( $html );
		$tree = KlimApiParserDom::analyze( $arr, array("form") );

		$path = Bootstrap::getInstanceRoot()."/include/config/api/forms/$provider/$method/v$version";
		@mkdir( $path, 0775, true );

		$dump = "<?php\n\n";
		$dump .= KlimExport::generate( $url, "url" );
		$dump .= KlimExport::generate( $arr, "dom_array" );
		$dump .= KlimExport::generate( $tree, "form_tree" );
		file_put_contents( "$path/dom.php", $dump );

		if ( !$htmlfile ) {
			file_put_contents( "$path/html.php", $html );
			file_put_contents( "$path/tidy.php", KlimApiFormatter::tidy($html) );
		}

		$methods = array( "GET", "POST" );
		$parent = KlimApiParserLinker::parentUrl( $url );

		if ( isset($tree["form"]) ) {
			foreach ( $tree["form"] as $form_id => $form_data ) {
				if ( isset($form_data["_attr"]["action"]) && strncmp($form_data["_attr"]["action"], "javascript:", 11) ) {

					if ( empty($form_data["_attr"]["action"]) ) {
						$form_data["_attr"]["action"] = $url;
					}

					$dump = $this->generateFormConfigCode( $form_data, $url, $parent, $provider, $method, $version, $form_id );
					file_put_contents( "$path/$form_id.php", $dump );
				}
			}
		}
	}
}

