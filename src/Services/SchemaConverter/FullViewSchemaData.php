<?php

declare( strict_types = 1 );

namespace EntitySchema\Services\SchemaConverter;

/**
 * @license GPL-2.0-or-later
 */
class FullViewSchemaData {

	/** @var NameBadge[] */
	public array $nameBadges;

	public string $schemaText;

	public function __construct( array $nameBadges, $schemaText ) {
		$this->nameBadges = $nameBadges;
		$this->schemaText = $schemaText;
	}

}
