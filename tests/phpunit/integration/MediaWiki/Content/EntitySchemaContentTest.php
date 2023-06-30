<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Content;

use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use MediaWikiIntegrationTestCase;

/**
 * @covers \EntitySchema\MediaWiki\Content\EntitySchemaContent
 *
 * @license GPL-2.0-or-later
 */
class EntitySchemaContentTest extends MediaWikiIntegrationTestCase {

	public function testGetTextForSearchIndex() {
		$content = new EntitySchemaContent( json_encode( [
			'labels' => [ 'de' => 'german label', 'en' => 'english label' ],
			'descriptions' => [ 'en' => 'english description' ],
			'aliases' => [ 'en' => [ 'first', 'second' ] ],
			'schemaText' => 'Schema text for search index',
			'serializationVersion' => '3.0',
			'type' => 'ShExC',
		] ) );
		$actualSearchIndexText = $content->getTextForSearchIndex();

		$expectedText = <<<TEXT
german label
english label
english description
first, second
Schema text for search index
TEXT;

		$this->assertSame( $expectedText, $actualSearchIndexText );
	}

}
