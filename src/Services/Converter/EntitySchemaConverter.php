<?php

declare( strict_types = 1 );

namespace EntitySchema\Services\Converter;

use DomainException;
use LogicException;
use Wikibase\DataModel\Term\AliasGroupList;
use Wikibase\DataModel\Term\TermList;

/**
 * Convert schema data for different uses from the persistence format
 *
 * @license GPL-2.0-or-later
 */
class EntitySchemaConverter {

	/**
	 * @param string $schemaJSON
	 *
	 * @return FullViewEntitySchemaData
	 *
	 * @throws LogicException
	 */
	public function getFullViewSchemaData(
		string $schemaJSON
	): FullViewEntitySchemaData {
		$schema = json_decode( $schemaJSON, true );

		return new FullViewEntitySchemaData(
			$this->getNameBadgesFromSchema( $schema ),
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

	public function getFullArraySchemaData( string $schemaJSON ): FullArrayEntitySchemaData {
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

		return new FullArrayEntitySchemaData( $data );
	}

	public function getPersistenceSchemaData( string $schemaJSON ): PersistenceEntitySchemaData {
		$schema = json_decode( $schemaJSON, true );
		$persistenceSchemaData = new PersistenceEntitySchemaData();
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

	public function getSearchEntitySchemaAdapter( string $schemaJSON ): SearchEntitySchemaAdapter {
		$viewData = $this->getFullViewSchemaData( $schemaJSON );
		$labels = new TermList();
		$descriptions = new TermList();
		$aliases = new AliasGroupList();
		foreach ( $viewData->nameBadges as $lang => $nameBadge ) {
			if ( $nameBadge->label !== '' ) {
				$labels->setTextForLanguage( $lang, $nameBadge->label );
			}
			if ( $nameBadge->description !== '' ) {
				$descriptions->setTextForLanguage( $lang, $nameBadge->description );
			}
			$aliases->setAliasesForLanguage( $lang, $nameBadge->aliases );
		}
		return new SearchEntitySchemaAdapter( $labels, $descriptions, $aliases );
	}

	/**
	 * Returns an array of NameBadges containing label, description and alias
	 * data for the schema in each language for which data is available.
	 *
	 * @param array $schema
	 *
	 * @return NameBadge[]
	 *
	 * @throws DomainException
	 */
	private function getNameBadgesFromSchema( array $schema ): array {
		$langs = $this->getSchemaLanguages( $schema );
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
	 * Return an array of language codes for all languages that are present
	 * in the schema
	 *
	 * @param array $schema
	 *
	 * @return string[]
	 */
	private function getSchemaLanguages( array $schema ): array {
		$langs = [];
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
