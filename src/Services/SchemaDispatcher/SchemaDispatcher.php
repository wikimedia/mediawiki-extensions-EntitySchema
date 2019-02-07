<?php

namespace Wikibase\Schema\Services\SchemaDispatcher;

use DomainException;
use LogicException;

/**
 * @license GPL-2.0-or-later
 */
class SchemaDispatcher {

	/**
	 * @param string $schemaJSON
	 * @param string $interfaceLanguage
	 *
	 * @return FullViewSchemaData
	 *
	 * @throws LogicException
	 */
	public function getFullViewSchemaData( $schemaJSON, $interfaceLanguage ): FullViewSchemaData {
		$schema = json_decode( $schemaJSON, true );
		$schemaCode = $schema['schema'] ?? '';

		return new FullViewSchemaData(
			$this->getNameBadgesFromSchema( $schema, $interfaceLanguage ),
			$schemaCode
		);
	}

	/**
	 * @param array $schema
	 * @param string $interfaceLanguage
	 *
	 * @return NameBadge[]
	 *
	 * @throws DomainException
	 */
	private function getNameBadgesFromSchema( array $schema, $interfaceLanguage ): array {
		$langs = $this->getSchemaLanguages( $schema, $interfaceLanguage );
		$nameBadges = [];
		foreach ( $langs as $langCode ) {
			$nameBadges[$langCode] = new NameBadge(
				$this->getLabelFromSchema( $schema, $langCode ),
				$this->getDescriptionFromSchema( $schema, $langCode ),
				$this->getAliasGroupFromSchema( $schema, $langCode )
			);
		}
		return $nameBadges;
	}

	private function getSchemaLanguages( $schema, $interfaceLanguage ) {
		$langs = [ $interfaceLanguage ];
		if ( !empty( $schema['labels'] ) ) {
			$langs = array_merge(
				$langs,
				array_keys( $schema['labels'] )
			);
		}
		if ( !empty( $schema['descriptions'] ) ) {
			$langs = array_merge(
				$langs,
				array_keys( $schema['descriptions'] )
			);
		}
		if ( !empty( $schema['aliases'] ) ) {
			$langs = array_merge(
				$langs,
				array_keys( $schema['aliases'] )
			);
		}
		return array_unique( $langs );
	}

	/**
	 * @param array $schema
	 * @param string $langCode
	 *
	 * @return string
	 *
	 * @throws DomainException
	 */
	private function getLabelFromSchema( array $schema, $langCode ) {
		if ( empty( $schema['labels'] ) ) {
			return '';
		}

		if ( !isset( $schema['serializationVersion'] ) ) {
			return '';
		}

		switch ( $schema['serializationVersion'] ) {
			case '1.0':
				if ( isset( $schema['labels'][$langCode] ) ) {
					return $schema['labels'][$langCode]['value'];
				}
				return '';
			case '2.0':
				return $schema['labels'][$langCode] ?? '';
			default:
				throw new DomainException(
					'Unknown schema serialization version ' . $schema['serializationVersion']
				);
		}
	}

	/**
	 * @param array $schema
	 * @param string $langCode
	 *
	 * @return string
	 *
	 * @throws DomainException
	 */
	private function getDescriptionFromSchema( array $schema, $langCode ) {
		if ( empty( $schema['descriptions'] ) ) {
			return '';
		}

		if ( !isset( $schema['serializationVersion'] ) ) {
			return '';
		}

		switch ( $schema['serializationVersion'] ) {
			case '1.0':
				if ( isset( $schema['descriptions'][$langCode] ) ) {
					return $schema['descriptions'][$langCode]['value'];
				}
				return '';
			case '2.0':
				return $schema['descriptions'][$langCode] ?? '';
			default:
				throw new DomainException(
					'Unknown schema serialization version ' . $schema['serializationVersion']
				);
		}
	}

	/**
	 * @param array $schema
	 * @param string $langCode
	 *
	 * @return array
	 *
	 * @throws DomainException
	 */
	private function getAliasGroupFromSchema( array $schema, $langCode ) {
		if ( empty( $schema['aliases'] ) ) {
			return [];
		}

		if ( !isset( $schema['serializationVersion'] ) ) {
			return [];
		}

		switch ( $schema['serializationVersion'] ) {
			case '1.0':
				if ( isset( $schema['aliases'][$langCode] ) ) {
					return array_column( $schema['aliases'][$langCode], 'value' );
				}
				return [];
			case '2.0':
				return $schema['aliases'][$langCode] ?? [];
			default:
				throw new DomainException(
					'Unknown schema serialization version ' . $schema['serializationVersion']
				);
		}
	}

}
