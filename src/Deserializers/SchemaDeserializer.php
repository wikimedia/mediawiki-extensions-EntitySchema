<?php

namespace Wikibase\Schema\Deserializers;

use Deserializers\Deserializer;
use Deserializers\Exceptions\DeserializationException;
use Deserializers\Exceptions\InvalidAttributeException;
use Wikibase\DataModel\Term\AliasGroupList;
use Wikibase\DataModel\Term\TermList;
use Wikibase\Schema\DataModel\Schema;

/**
 * Serialize a PHP array into a Schema
 *
 * @license GPL-2.0-or-later
 */
class SchemaDeserializer implements Deserializer {

	private $termListDeserializer;
	private $aliasGroupListDeserializer;

	public function __construct(
		Deserializer $termListDeserializer,
		Deserializer $aliasGroupListDeserializer
	) {
		$this->termListDeserializer = $termListDeserializer;
		$this->aliasGroupListDeserializer = $aliasGroupListDeserializer;
	}

	/**
	 * @see Deserializer::deserialize
	 *
	 * @param array $serialization
	 *
	 * @throws DeserializationException
	 * @return Schema
	 */
	public function deserialize( $serialization ) {
		$schema = new Schema();

		$this->setSchemaFromSerialization( $serialization, $schema );
		$this->setTermsFromSerialization( $serialization, $schema );

		return $schema;
	}

	private function setSchemaFromSerialization( array $serialization, Schema $schema ) {
		if ( !array_key_exists( 'schema', $serialization ) ) {
			return;
		}
		if ( !is_string( $serialization[ 'schema' ] ) ) {
			throw new InvalidAttributeException(
				'schema',
				$serialization[ 'schema' ],
				"The type of attribute 'schema' needs to be string!"
			);
		}

		$schema->setSchema( $serialization[ 'schema' ] );
	}

	private function setTermsFromSerialization( array $serialization, Schema $schema ) {
		if ( array_key_exists( 'labels', $serialization ) ) {
			$this->assertIsArray( $serialization, 'labels' );
			/** @var TermList $labels */
			$labels = $this->termListDeserializer->deserialize( $serialization[ 'labels' ] );
			$schema->getFingerprint()->setLabels( $labels );
		}

		if ( array_key_exists( 'descriptions', $serialization ) ) {
			$this->assertIsArray( $serialization, 'descriptions' );
			/** @var TermList $descriptions */
			$descriptions = $this->termListDeserializer->deserialize( $serialization[ 'descriptions' ] );
			$schema->getFingerprint()->setDescriptions( $descriptions );
		}

		if ( array_key_exists( 'aliases', $serialization ) ) {
			$this->assertIsArray( $serialization, 'aliases' );
			/** @var AliasGroupList $aliases */
			$aliases = $this->aliasGroupListDeserializer->deserialize( $serialization[ 'aliases' ] );
			$schema->getFingerprint()->setAliasGroups( $aliases );
		}
	}

	private function assertIsArray( array $serialization, $key ) {
		if ( is_array( $serialization[ $key ] ) ) {
			return;
		}
		throw new InvalidAttributeException(
			$key,
			$serialization[ $key ],
			"The type of attribute '$key' needs to be array!"
		);
	}

}
