<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

/**
 * @license GPL-2.0-or-later
 */
class EntitySchemaCleaner {

	/**
	 * @param string[] &$labels
	 * @param string[] &$descriptions
	 * @param array<string,string[]> &$aliasGroups
	 * @param string &$schemaText
	 */
	public static function cleanupParameters(
		array &$labels,
		array &$descriptions,
		array &$aliasGroups,
		string &$schemaText
	): void {
		$labels = self::cleanupArrayOfStrings( $labels );
		$descriptions = self::cleanupArrayOfStrings( $descriptions );
		$aliasGroups = self::cleanAliasGroups( $aliasGroups );
		$schemaText = self::trimWhitespaceAndControlChars( $schemaText );
	}

	public static function cleanupArrayOfStrings( array $arrayOfStrings ): array {
		foreach ( $arrayOfStrings as &$string ) {
			$string = self::trimWhitespaceAndControlChars( $string );
		}
		$arrayOfStrings = self::filterEmptyStrings( $arrayOfStrings );
		ksort( $arrayOfStrings );
		return $arrayOfStrings;
	}

	/**
	 * @param string $string The string to trim
	 *
	 * @return string The trimmed string after applying the regex
	 */
	public static function trimWhitespaceAndControlChars( string $string ): string {
		return preg_replace( '/^[\p{Z}\p{Cc}\p{Cf}]+|[\p{Z}\p{Cc}\p{Cf}]+$/u', '', $string );
	}

	private static function cleanAliasGroups( array $aliasGroups ): array {
		foreach ( $aliasGroups as &$aliasGroup ) {
			$aliasGroup = self::cleanupArrayOfStrings( $aliasGroup );
		}
		unset( $aliasGroup );
		foreach ( $aliasGroups as $languageCode => &$aliasGroup ) {
			$aliasGroup = array_values( array_unique( $aliasGroup ) );
			if ( $aliasGroup === [] ) {
				unset( $aliasGroups[$languageCode] );
			}
		}
		ksort( $aliasGroups );
		return $aliasGroups;
	}

	/**
	 * Remove keys with empty values/strings from the array
	 *
	 * Note: This does not renumber numeric keys!
	 *
	 * @param string[] $array
	 *
	 * @return string[]
	 */
	private static function filterEmptyStrings( array $array ): array {
		foreach ( $array as $key => $value ) {
			if ( $value === '' ) {
				unset( $array[$key] );
			}
		}
		return $array;
	}

}
