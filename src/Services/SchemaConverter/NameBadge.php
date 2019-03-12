<?php

namespace Wikibase\Schema\Services\SchemaConverter;

/**
 * @license GPL-2.0-or-later
 */
class NameBadge {

	/** @var string */
	public $label;

	/** @var string */
	public $description;

	/** string[] */
	public $aliases;

	public function __construct( $label, $description, array $aliases ) {
		$this->label = $label;
		$this->description = $description;
		$this->aliases = $aliases;
	}

}
