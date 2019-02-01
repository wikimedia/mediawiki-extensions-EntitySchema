<?php

namespace Wikibase\Schema\Serialization;

use Wikibase\DataModel\Deserializers\AliasGroupListDeserializer;
use Wikibase\DataModel\Deserializers\TermDeserializer;
use Wikibase\DataModel\Deserializers\TermListDeserializer;

/**
 * Create Deserializers
 *
 * @license GPL-2.0-or-later
 */
class DeserializerFactory {

	public static function newSchemaDeserializer() {
		return new SchemaDeserializer(
			new TermListDeserializer( new TermDeserializer() ),
			new AliasGroupListDeserializer()
		);
	}

}
