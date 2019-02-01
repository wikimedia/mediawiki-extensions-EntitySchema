<?php

namespace Wikibase\Schema\Serialization;

use Serializers\Exceptions\SerializationException;
use Serializers\Exceptions\UnsupportedObjectException;
use Serializers\Serializer;
use Wikibase\Schema\Domain\Model\Schema;

/**
 * Serialize a Schema into a PHP array
 *
 * @license GPL-2.0-or-later
 */
class SchemaSerializer implements Serializer {
	private $termListSerializer;
	private $aliasGroupListSerializer;

	public function __construct(
		Serializer $termListSerializer,
		Serializer $aliasGroupListSerializer
	) {
		$this->termListSerializer = $termListSerializer;
		$this->aliasGroupListSerializer = $aliasGroupListSerializer;
	}

	/**
	 * @see Serializer::serialize
	 *
	 * @param Schema $object
	 *
	 * @throws SerializationException
	 * @return array
	 */
	public function serialize( $object ) {
		if ( !$object instanceof Schema ) {
			throw new UnsupportedObjectException(
				$object,
				'SchemaSerializer can only serialize Schema objects.'
			);
		}

		return $this->getSerialized( $object );
	}

	private function getSerialized( Schema $schema ) {
		$serialization = [
			'serializationVersion' => '1.0'
		];

		$this->addSchemaToSerialization( $schema, $serialization );
		$this->addTermsToSerialization( $schema, $serialization );

		return $serialization;
	}

	private function addTermsToSerialization( Schema $schema, array &$serialization ) {
		$fingerprint = $schema->getFingerprint();

		$serialization[ 'labels' ] = $this->termListSerializer->serialize( $fingerprint->getLabels() );
		$serialization[ 'descriptions' ] = $this->termListSerializer->serialize(
			$fingerprint->getDescriptions()
		);
		$serialization[ 'aliases' ] = $this->aliasGroupListSerializer->serialize(
			$fingerprint->getAliasGroups()
		);
	}

	private function addSchemaToSerialization( Schema $schema, array &$serialization ) {
		$serialization[ 'schema' ] = $schema->getSchema();
		$serialization[ 'type' ] = 'ShExC';
	}

}
