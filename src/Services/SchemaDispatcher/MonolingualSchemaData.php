<?php

namespace Wikibase\Schema\Services\SchemaDispatcher;

/**
 * @license GPL-2.0-or-later
 */
class MonolingualSchemaData {

	/** @var NameBadge */
	public $nameBadge;

	/** @var string */
	public $schema;

	public function __construct( NameBadge $nameBadge, $schema ) {
		$this->nameBadge = $nameBadge;
		$this->schema = $schema;
	}

}
