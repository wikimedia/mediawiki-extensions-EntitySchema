<?php declare( strict_types=1 );

namespace EntitySchema\Wikibase\DataValues;

use DataValues\DataValueObject;
use EntitySchema\Domain\Model\EntitySchemaId;
use Wikibase\DataModel\Entity\EntityIdValue;

/**
 * @license GPL-2.0-or-later
 */
class EntitySchemaValue extends DataValueObject {

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

	/** @inheritDoc */
	public function getArrayValue(): array {
		return [
			'id' => $this->id->getId(),
			'type' => 'entityschema',
		];
	}

	public function getSchemaId(): string {
		return $this->id->getId();
	}
}
