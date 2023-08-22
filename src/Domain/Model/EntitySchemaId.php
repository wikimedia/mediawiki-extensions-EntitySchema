<?php

declare( strict_types = 1 );

namespace EntitySchema\Domain\Model;

use InvalidArgumentException;

/**
 * @license GPL-2.0-or-later
 */
class EntitySchemaId {

	private string $id;

	public const PATTERN = '/^E[1-9][0-9]*\z/';

	public function __construct( string $id ) {
		if ( !preg_match( self::PATTERN, $id ) ) {
			throw new InvalidArgumentException( 'ID must match ' . self::PATTERN );
		}

		$this->id = $id;
	}

	public function getId(): string {
		return $this->id;
	}

}
