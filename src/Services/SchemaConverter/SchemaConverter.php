<?php

declare( strict_types = 1 );

namespace EntitySchema\Services\SchemaConverter;

use DomainException;
use LogicException;

/**
 * Convert schema data for different uses from the persistence format
 *
 * @license GPL-2.0-or-later
 */
class SchemaConverter {

	/**
	 * @param string $schemaJSON
	 * @param string[] $preferredLanguages Name badges for these language codes will always be present,
	 * even if there is no data for them, and they will also be ordered before other languages.
	 *
	 * @return FullViewSchemaData
	 *
	 * @throws LogicException
	 */
	public function getFullViewSchemaData(
		string $schemaJSON,
		array $preferredLanguages
	): FullViewSchemaData {
		$schema = json_decode( $schemaJSON, true );

		return new FullViewSchemaData(
			$this->getNameBadgesFromSchema( $schema, $preferredLanguages ),
			$this->getSchemaTextFromSchema( $schema )
		);
	}

	public function getMonolingualNameBadgeData( string $schemaData, string $langCode ): NameBadge {
		$schema = json_decode( $schemaData, true );

		return new NameBadge(
			$this->getLabelFromSchema( $schema, $langCode ),
			$this->getDescriptionFromSchema( $schema, $langCode ),
			$this->getAliasGroupFromSchema( $schema, $langCode )
		);
	}

	public function getFullArraySchemaData( string $schemaJSON ): FullArraySchemaData {
		$schema = json_decode( $schemaJSON, true );

		$data = [
			'labels' => [],
			'descriptions' => [],
			'aliases' => [],
			'schemaText' => $this->getSchemaTextFromSchema( $schema ),
		];

		foreach ( $this->getSchemaLanguages( $schema ) as $languageCode ) {
			$label = $this->getLabelFromSchema( $schema, $languageCode );
			if ( $label ) {
				$data['labels'][$languageCode] = $label;
			}
			$description = $this->getDescriptionFromSchema( $schema, $languageCode );
			if ( $description ) {
				$data['descriptions'][$languageCode] = $description;
			}
			$aliases = $this->getAliasGroupFromSchema( $schema, $languageCode );
			if ( $aliases ) {
				$data['aliases'][$languageCode] = $aliases;
			}
		}

		return new FullArraySchemaData( $data );
	}

	public function getPersistenceSchemaData( string $schemaJSON ): PersistenceSchemaData {
		$schema = json_decode( $schemaJSON, true );
		$persistenceSchemaData = new PersistenceSchemaData();
		$persistenceSchemaData->schemaText = $this->getSchemaTextFromSchema( $schema );

		foreach ( $this->getSchemaLanguages( $schema ) as $languageCode ) {
			$label = $this->getLabelFromSchema( $schema, $languageCode );
			if ( $label ) {
				$persistenceSchemaData->labels[$languageCode] = $label;
			}
			$description = $this->getDescriptionFromSchema( $schema, $languageCode );
			if ( $description ) {
				$persistenceSchemaData->descriptions[$languageCode] = $description;
			}
			$aliases = $this->getAliasGroupFromSchema( $schema, $languageCode );
			if ( $aliases ) {
				$persistenceSchemaData->aliases[$languageCode] = $aliases;
			}
		}

		return $persistenceSchemaData;
	}

	public function getSchemaID( string $schemaJSON ) {
		return $this->getIdFromSchema( json_decode( $schemaJSON, true ) );
	}

	private function getIdFromSchema( array $schema ) {
		if ( !isset( $schema['serializationVersion'] ) ) {
			return null;
		}

		switch ( $schema['serializationVersion'] ) {
			case '1.0':
			case '2.0':
			case '3.0':
				return $schema['id'] ?? null;
			default:
				throw new DomainException(
					'Unknown schema serialization version ' . $schema['serializationVersion']
				);
		}
	}

	public function getSchemaText( string $schemaJSON ): string {
		$schema = json_decode( $schemaJSON, true );
		return $this->getSchemaTextFromSchema( $schema );
	}

	private function getSchemaTextFromSchema( array $schema ): string {
		if ( !isset( $schema['serializationVersion'] ) ) {
			return '';
		}

		switch ( $schema['serializationVersion'] ) {
			case '1.0':
			case '2.0':
				return $schema['schema'] ?? '';
			case '3.0':
				return $schema['schemaText'] ?? '';
			default:
				throw new DomainException(
					'Unknown schema serialization version ' . $schema['serializationVersion']
				);
		}
	}

	/**
	 * @param array $schema
	 * @param string[] $preferredLanguages
	 *
	 * @return NameBadge[]
	 *
	 * @throws DomainException
	 */
	private function getNameBadgesFromSchema( array $schema, array $preferredLanguages ): array {
		$langs = $this->getSchemaLanguages( $schema, $preferredLanguages );
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

	/**
	 * @param array $schema
	 * @param string[] $preferredLanguages
	 *
	 * @return string[]
	 */
	private function getSchemaLanguages( array $schema, array $preferredLanguages = [] ): array {
		$langs = $preferredLanguages;
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
	private function getLabelFromSchema( array $schema, $langCode ): string {
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
			case '3.0':
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
	private function getDescriptionFromSchema( array $schema, $langCode ): string {
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
			case '3.0':
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
	private function getAliasGroupFromSchema( array $schema, $langCode ): array {
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
			case '3.0':
				return $schema['aliases'][$langCode] ?? [];
			default:
				throw new DomainException(
					'Unknown schema serialization version ' . $schema['serializationVersion']
				);
		}
	}

}
