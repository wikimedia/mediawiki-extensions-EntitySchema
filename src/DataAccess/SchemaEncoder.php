<?php

namespace EntitySchema\DataAccess;

use InvalidArgumentException;
use Language;
use MediaWiki\MediaWikiServices;
use EntitySchema\Domain\Model\SchemaId;

/**
 * @license GPL-2.0-or-later
 */
class SchemaEncoder {

	/**
	 * @param SchemaId $id
	 * @param array    $labels       labels  with langCode as key, e.g. [ 'en' => 'Cat' ]
	 * @param array    $descriptions descriptions with langCode as key, e.g. [ 'en' => 'A cat' ]
	 * @param array    $aliases      aliases with langCode as key, e.g. [ 'en' => [ 'tiger' ], ]
	 * @param string   $schemaText
	 *
	 * @throws InvalidArgumentException if bad parameters are passed
	 *
	 * @return string
	 */
	public static function getPersistentRepresentation(
		SchemaId $id,
		array $labels,
		array $descriptions,
		array $aliases,
		$schemaText
	) {
		self::validateParameters(
			$labels,
			$descriptions,
			$aliases,
			$schemaText
		);
		SchemaCleaner::cleanupParameters(
			$labels,
			$descriptions,
			$aliases,
			$schemaText
		);
		return json_encode(
			[
				'id' => $id->getId(),
				'serializationVersion' => '3.0',
				'labels' => $labels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $schemaText,
				'type' => 'ShExC',
			]
		);
	}

	/**
	 * @param string[] $labels
	 * @param string[] $descriptions
	 * @param array<string,string[]> $aliasGroups
	 * @param string $schemaText
	 */
	private static function validateParameters(
		array $labels,
		array $descriptions,
		array $aliasGroups,
		$schemaText
	) {
		self::validateLangCodes( $labels, $descriptions, $aliasGroups );
		self::validateParameterTypes(
			$labels,
			$descriptions,
			$aliasGroups,
			$schemaText
		);
		self::validateIdentifyingInfoMaxLength(
			$labels,
			$descriptions,
			$aliasGroups
		);
		self::validateSchemaMaxLength( $schemaText );
	}

	private static function validateLangCodes(
		array $labels,
		array $descriptions,
		array $aliasGroups
	) {
		$providedLangCodes = array_unique(
			array_merge(
				array_keys( $labels ),
				array_keys( $descriptions ),
				array_keys( $aliasGroups )
			)
		);
		$invalidLangCodes = array_filter(
			$providedLangCodes,
			function( $langCode ) {
				return !Language::isSupportedLanguage( $langCode );
			}
		);
		if ( count( $invalidLangCodes ) > 0 ) {
			throw new InvalidArgumentException( 'language codes must be valid!' );
		}
	}

	private static function validateParameterTypes(
		array $labels,
		array $descriptions,
		array $aliasGroups,
		$schemaText
	) {
		if ( count( array_filter( $labels, 'is_string' ) ) !== count( $labels )
			|| count( array_filter( $descriptions, 'is_string' ) ) !== count( $descriptions )
			|| !is_string( $schemaText )
			|| count( array_filter( $aliasGroups, [ self::class, 'isSequentialArrayOfStrings' ] ) )
			!== count( $aliasGroups )
		) {
			throw new InvalidArgumentException(
				'language, label, description and schemaText must be strings '
				. 'and aliases must be an array of strings'
			);
		}
	}

	private static function validateIdentifyingInfoMaxLength(
		array $labels,
		array $descriptions,
		array $aliasGroups
	) {
		foreach ( $labels as $label ) {
			self::validateLDAMaxLength( $label );
		}

		foreach ( $descriptions as $description ) {
			self::validateLDAMaxLength( $description );
		}

		foreach ( $aliasGroups as $aliasGroup ) {
			self::validateLDAMaxLength( implode( '', $aliasGroup ) );
		}
	}

	private static function validateLDAMaxLength( $localizedString ) {
		$maxLengthChars = MediaWikiServices::getInstance()->getMainConfig()
			->get( 'EntitySchemaNameBadgeMaxSizeChars' );
		if ( mb_strlen( $localizedString ) > $maxLengthChars ) {
			throw new InvalidArgumentException(
				'Identifying information is longer than the allowed max of ' . $maxLengthChars . ' characters!'
			);
		}
	}

	private static function validateSchemaMaxLength( $schemaText ) {
		$maxLengthBytes = MediaWikiServices::getInstance()->getMainConfig()
			->get( 'EntitySchemaSchemaTextMaxSizeBytes' );
		if ( strlen( $schemaText ) > $maxLengthBytes ) {
			throw new InvalidArgumentException(
				'Schema text is longer than the allowed max of ' . $maxLengthBytes . ' bytes!'
			);
		}
	}

	private static function isSequentialArrayOfStrings( $array ) {
		if ( !is_array( $array ) ) {
			return false;
		}
		$values = array_values( $array );
		if ( $array !== $values ) {
			// Array is associative or sparse. Fast solution from
			// https://stackoverflow.com/questions/173400/how-to-check-if-php-array-is-associative-or-sequential
			return false;
		}
		foreach ( $values as $value ) {
			if ( !is_string( $value ) ) {
				return false;
			}
		}
		return true;
	}

}
