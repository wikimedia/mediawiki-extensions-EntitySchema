<?php

declare( strict_types = 1 );

namespace EntitySchema\Services\SchemaConverter;

/**
 * @license GPL-2.0-or-later
 */
class PersistenceSchemaData {
	/** @var string[] */
	public array $labels = [];
	/** @var string[] */
	public array $descriptions = [];
	/** @var string[][] */
	public array $aliases = [];
	public string $schemaText = '';
}
