<?php

namespace EntitySchema\Services\SchemaConverter;

/**
 * @license GPL-2.0-or-later
 */
class NameBadge {

	/** @var string */
	public $label;

	/** @var string */
	public $description;

	/** @var string[] */
	public $aliases;

	public function __construct( string $label, string $description, array $aliases ) {
		$this->label = $label;
		$this->description = $description;
		$this->aliases = $aliases;
	}

}
