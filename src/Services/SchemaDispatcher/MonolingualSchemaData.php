<?php

namespace Wikibase\Schema\Services\SchemaDispatcher;

/**
 * @license GPL-2.0-or-later
 */
class MonolingualSchemaData {

	/** @var NameBadge */
	public $nameBadge;

	/** @var string */
	public $schemaText;

	public function __construct( NameBadge $nameBadge, $schemaText ) {
		$this->nameBadge = $nameBadge;
		$this->schemaText = $schemaText;
	}

}
