<?php

declare( strict_types = 1 );

namespace EntitySchema\Services\Converter;

/**
 * @license GPL-2.0-or-later
 */
class NameBadge {

	public string $label;

	public string $description;

	/** @var string[] */
	public array $aliases;

	public function __construct( string $label, string $description, array $aliases ) {
		$this->label = $label;
		$this->description = $description;
		$this->aliases = $aliases;
	}

}
