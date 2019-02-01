<?php

namespace Wikibase\Schema\Serialization;

use Wikibase\DataModel\Serializers\AliasGroupListSerializer;
use Wikibase\DataModel\Serializers\AliasGroupSerializer;
use Wikibase\DataModel\Serializers\TermListSerializer;
use Wikibase\DataModel\Serializers\TermSerializer;

/**
 * Create Serializers
 *
 * @license GPL-2.0-or-later
 */
class SerializerFactory {

	public static function newSchemaSerializer() {
		return new SchemaSerializer(
			new TermListSerializer( new TermSerializer(), false ),
			new AliasGroupListSerializer( new AliasGroupSerializer(), false )
		);
	}

}
