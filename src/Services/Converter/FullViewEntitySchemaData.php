<?php

declare( strict_types = 1 );

namespace EntitySchema\Services\Converter;

/**
 * @license GPL-2.0-or-later
 */
class FullViewEntitySchemaData {

	/** @var NameBadge[] */
	public array $nameBadges;

	public string $schemaText;

	public function __construct( array $nameBadges, $schemaText ) {
		$this->nameBadges = $nameBadges;
		$this->schemaText = $schemaText;
	}

}
