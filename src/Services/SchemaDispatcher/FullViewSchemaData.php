<?php

namespace Wikibase\Schema\Services\SchemaDispatcher;

/**
 * @license GPL-2.0-or-later
 */
class FullViewSchemaData {

	/** @var NameBadge[] */
	public $nameBadges;

	/** @var string */
	public $schema;

	public function __construct( array $nameBadges, $schema ) {
		$this->nameBadges = $nameBadges;
		$this->schema = $schema;
	}

}
