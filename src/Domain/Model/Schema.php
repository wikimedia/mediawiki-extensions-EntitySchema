<?php

namespace Wikibase\Schema\Domain\Model;

use InvalidArgumentException;
use Wikibase\DataModel\Term\AliasGroup;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\DataModel\Term\Term;

/**
 * Representation of a Schema
 *
 * @license GPL-2.0-or-later
 */
class Schema {

	/** @var SchemaId|null the schema ID */
	private $id;
	/** @var string the actual ShEx schema string */
	private $schema;
	/** @var Fingerprint the labels, descriptions and aliases of the schema */
	private $fingerprint;

	/**
	 * Schema constructor.
	 *
	 * @param SchemaId|null $id
	 * @param string $schema the actual ShEx schema string
	 * @param Fingerprint $fingerprint
	 */
	public function __construct(
		SchemaId $id = null,
		$schema = '',
		Fingerprint $fingerprint = null
	) {
		if ( !is_string( $schema ) ) {
			// TODO: replace with a signature type hint as soon as we have PHP 7
			throw new InvalidArgumentException( '$schema must be a string' );
		}

		$this->id = $id;
		$this->schema = $schema;
		$this->fingerprint = $fingerprint ?: new Fingerprint();
	}

	/**
	 * @return SchemaId|null
	 */
	public function getId() {
		return $this->id;
	}

	public function setId( SchemaId $id ) {
		$this->id = $id;
	}

	public function getFingerprint() {
		return $this->fingerprint;
	}

	/**
	 * @return string
	 */
	public function getSchema() {
		return $this->schema;
	}

	public function setSchema( $schema ) {
		if ( !is_string( $schema ) ) {
			// TODO: replace with a signature type hint as soon as we have PHP 7
			throw new InvalidArgumentException( '$schema must be a string' );
		}
		$this->schema = $schema;
	}

	/**
	 * @param string $languageCode
	 *
	 * @return \Wikibase\DataModel\Term\Term
	 */
	public function getLabel( $languageCode ) {
		if ( !$this->fingerprint->hasLabel( $languageCode ) ) {
			return new Term( $languageCode, '' );
		}
		return $this->fingerprint->getLabel( $languageCode );
	}

	/**
	 * @param string $languageCode
	 *
	 * @return \Wikibase\DataModel\Term\Term
	 */
	public function getDescription( $languageCode ) {
		if ( !$this->fingerprint->hasDescription( 'en' ) ) {
			return new Term( $languageCode, '' );
		}
		return $this->fingerprint->getDescription( $languageCode );
	}

	/**
	 * @param string $languageCode
	 *
	 * @return \Wikibase\DataModel\Term\AliasGroup
	 */
	public function getAliasGroup( $languageCode ) {
		if ( !$this->fingerprint->hasAliasGroup( 'en' ) ) {
			return new AliasGroup( $languageCode, [] );
		}
		return $this->fingerprint->getAliasGroup( $languageCode );
	}

	/**
	 * @param string $languageCode
	 * @param string $value
	 *
	 * @throws InvalidArgumentException
	 */
	public function setLabel( $languageCode, $value ) {
		$this->fingerprint->setLabel( $languageCode, $value );
	}

	/**
	 * @param string $languageCode
	 * @param string $value
	 *
	 * @throws InvalidArgumentException
	 */
	public function setDescription( $languageCode, $value ) {
		$this->fingerprint->setDescription( $languageCode, $value );
	}

	/**
	 * @param string $languageCode
	 * @param string[] $aliases
	 *
	 * @throws InvalidArgumentException
	 */
	public function setAliasGroup( $languageCode, array $aliases ) {
		$this->fingerprint->setAliasGroup( $languageCode, $aliases );
	}

}
