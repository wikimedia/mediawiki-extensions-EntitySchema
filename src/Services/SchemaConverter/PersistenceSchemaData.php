<?php

namespace EntitySchema\Services\SchemaConverter;

/**
 * @license GPL-2.0-or-later
 */
class PersistenceSchemaData {
	/** @var string[] */
	public $labels = [];
	/** @var string[] */
	public $descriptions = [];
	/** @var string[][] */
	public $aliases = [];
	/** @var string */
	public $schemaText = '';
}
