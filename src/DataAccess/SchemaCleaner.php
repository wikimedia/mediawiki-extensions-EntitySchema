<?php

namespace Wikibase\Schema\DataAccess;

/**
 * @license GPL-2.0-or-later
 */
class SchemaCleaner {

	/**
	 * @param string[] $labels
	 * @param string[] $descriptions
	 * @param array<string,string[]> $aliasGroups
	 * @param string $schemaText
	 */
	public static function cleanupParameters(
		array &$labels,
		array &$descriptions,
		array &$aliasGroups,
		&$schemaText
	) {
		self::trimStartAndEnd( $labels, $descriptions, $aliasGroups, $schemaText );
		$labels = self::filterEmptyStrings( $labels );
		ksort( $labels );
		$descriptions = self::filterEmptyStrings( $descriptions );
		ksort( $descriptions );
		foreach ( $aliasGroups as $languageCode => &$aliasGroup ) {
			$aliasGroup = array_values( array_unique( $aliasGroup ) );
			if ( $aliasGroup === [] ) {
				unset( $aliasGroups[$languageCode] );
			}
		}
		ksort( $aliasGroups );
	}

	/**
	 * @return void
	 */
	private static function trimStartAndEnd(
		array &$labels,
		array &$descriptions,
		array &$aliasGroups,
		&$schemaText
	) {
		foreach ( $labels as &$label ) {
			$label = self::trimWhitespaceAndControlChars( $label );
		}
		foreach ( $descriptions as &$description ) {
			$description = self::trimWhitespaceAndControlChars( $description );
		}
		foreach ( $aliasGroups as &$aliasGroup ) {
			$aliasGroup = self::filterEmptyStrings( array_map(
				[ self::class, 'trimWhitespaceAndControlChars' ],
				$aliasGroup
			) );
		}
		$schemaText = self::trimWhitespaceAndControlChars( $schemaText );
	}

	/**
	 * @param string $string The string to trim
	 *
	 * @return string The trimmed string after applying the regex
	 */
	private static function trimWhitespaceAndControlChars( $string ) {
		return preg_replace( '/^[\p{Z}\p{Cc}\p{Cf}]+|[\p{Z}\p{Cc}\p{Cf}]+$/u', '', $string );
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
