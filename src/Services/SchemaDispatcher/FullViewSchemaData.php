<?php

namespace Wikibase\Schema\Services\SchemaDispatcher;

/**
 * @license GPL-2.0-or-later
 */
class FullViewSchemaData {

	/** @var NameBadge[] */
	public $nameBadges;

	/** @var string */
	public $schemaText;

	public function __construct( array $nameBadges, $schemaText ) {
		$this->nameBadges = $nameBadges;
		$this->schemaText = $schemaText;
	}

}
