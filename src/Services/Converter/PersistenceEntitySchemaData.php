<?php

declare( strict_types = 1 );

namespace EntitySchema\Services\Converter;

/**
 * @license GPL-2.0-or-later
 */
class PersistenceEntitySchemaData {
	/** @var string[] */
	public array $labels = [];
	/** @var string[] */
	public array $descriptions = [];
	/** @var string[][] */
	public array $aliases = [];
	public string $schemaText = '';
}
