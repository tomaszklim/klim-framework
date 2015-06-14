<?php
/**
 * Uniwersalny parser HTML
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


class KlimApiParserDom
{

	/**
	 * Główna metoda parsująca przekazany kod html, za pomocą rozszerzenia
	 * DOM. Zamienia podany na arraya o strukturze zoptymalizowanej pod
	 * kątem dalszego przetwarzania rekurencyjnego. W przypadku błędu
	 * zwraca false.
	 */
	public static function parse( $html )
	{
		$dom = new DomDocument();
		$ret = @$dom->loadHTML( $html );

		if ( $ret === false ) {
			return false;
		}

		// $dom->preserveWhiteSpace = false;

		$arr = self::parseDom( $dom );
		unset( $dom );
		return $arr;
	}

	/**
	 * Metoda pomocnicza dla parsera, przekształcająca drzewo DOM (czyli
	 * kod html załadowany do obiektu DomDocument) na arraya o strukturze
	 * zoptymalizowanej pod kątem dalszego przetwarzania rekurencyjnego.
	 */
	protected static function parseDom( $root )
	{
		$result = array();

		if ( $root->hasAttributes() ) {
			foreach ( $root->attributes as $i => $attr ) {
				$result["attributes"][$attr->name] = $attr->value;
			}
		}

		$children = $root->childNodes;

		if ( !is_object($children) ) {
			return $result;
		}

		if ( $children->length == 1 ) {
			$child = $children->item(0);

			if ( $child->nodeType == XML_TEXT_NODE ) {
				$result["value"] = $child->nodeValue;
				return $result;
			}
		}

		for ( $i = 0; $i < $children->length; $i++ ) {
			$child = $children->item($i);
			$result["children"][] = $child->nodeName;
			$result["nodes"][][$child->nodeName] = self::parseDom( $child );
		}

		return $result;
	}

	/**
	 * Analizuje drzewo zwrócone przez metodę parse() i wyciąga z niego
	 * hierarchię, w której najwyższymi węzłami są znaczniki podane w
	 * drugim parametrze, oraz do której należą znaczniki wg zależności
	 * zdefiniowanych w metodzie getChildTags().
	 */
	public static function analyze( $root, $search_tags )
	{
		if ( !isset($root["children"]) ) {
			return array();
		}

		$structure = array();
		$cnt = 0;
		foreach ( $root["children"] as $child_id => $child_tag ) {
			$child_node = $root["nodes"][$child_id][$child_tag];

			if ( is_array($search_tags) && in_array($child_tag, $search_tags, true) ) {
				$child_search_tags = self::getChildTags( $child_tag );

				$structure[$child_tag][$cnt] = self::analyze( $child_node, $child_search_tags );

				if ( !empty($child_node["attributes"]) ) {
					$structure[$child_tag][$cnt]["_attr"] = $child_node["attributes"];
				}

				if ( !empty($child_node["value"]) ) {
					$structure[$child_tag][$cnt]["_value"] = $child_node["value"];
				}

				$cnt++;

			} else {
				$tmp = self::analyze( $child_node, $search_tags );
				$structure = array_merge_recursive( $structure, $tmp );
			}
		}

		return $structure;
	}

	/**
	 * Metoda pomocnicza dla analizatora struktury DOM, zwracająca
	 * znaczniki podrzędne w hierarchii do znacznika podanego.
	 */
	protected static function getChildTags( $tag )
	{
		switch ( $tag ) {
			case "form":   return array("input", "select", "textarea", "label", "button");
			case "select": return array("option");
			case "table":  return array("tr");
			case "tr":     return array("th", "td");
			default:       return false;
		}
	}
}

