<?php declare( strict_types=1 );

namespace EntitySchema\Wikibase\DataValues;

use DataValues\DataValueObject;
use DataValues\IllegalValueException;
use EntitySchema\Domain\Model\EntitySchemaId;
use InvalidArgumentException;
use Wikibase\DataModel\Entity\EntityIdValue;

/**
 * @license GPL-2.0-or-later
 */
class EntitySchemaValue extends DataValueObject {

	public const TYPE = 'entity-schema';

	private EntitySchemaId $id;

	public function __construct( EntitySchemaId $id ) {
		$this->id = $id;
	}

	public function __serialize(): array {
		return [ 'entityId' => $this->id ];
	}

	/**
	 * @inheritDoc
	 * Serialization is required by SnakList to compare two snak values
	 * by the hash of their serialization. These values are not saved anywhere
	 * so Unserialize is never required.
	 */
	public function serialize() {
		return serialize( $this->id );
	}

	/**
	 * @param array $data The array representation of the object
	 * @return never-returns
	 */
	public function __unserialize( array $data ) {
		throw new \LogicException( 'Method not implemented' );
	}

	/**
	 * @param string $data The serialized representation of the object
	 * @return never-returns
	 */
	public function unserialize( $data ): void {
		throw new \LogicException( 'Method not implemented' );
	}

	/** @inheritDoc */
	public static function getType() {
		return EntityIdValue::getType();
	}

	/** @inheritDoc */
	public function getValue() {
		return $this;
	}

	/**
	 * Constructs a new instance from the provided data. Required for @see DataValueDeserializer.
	 * This is expected to round-trip with @see getArrayValue.
	 *
	 * @param array $value
	 * @return self
	 */
	public static function newFromArray( $value ): self {
		if ( !is_array( $value ) ) {
			throw new IllegalValueException( 'The value supplied must be an array' );
		}
		if ( !array_key_exists( 'id', $value ) ) {
			throw new IllegalValueException( 'The value must contain an "id" key' );
		}
		if ( !is_string( $value['id'] ) ) {
			throw new IllegalValueException( 'The "id" element must be a string' );
		}

		try {
			return new self( new EntitySchemaId( $value['id'] ) );
		} catch ( InvalidArgumentException $e ) {
			throw new IllegalValueException( $e->getMessage(), 0, $e );
		}
	}

	/** @inheritDoc */
	public function getArrayValue(): array {
		// similar to EntityIdValue::getArrayValue() but without deprecated numeric-id
		return [
			'id' => $this->id->getId(),
			'entity-type' => self::TYPE,
		];
	}

	public function getSchemaId(): string {
		return $this->id->getId();
	}
}
