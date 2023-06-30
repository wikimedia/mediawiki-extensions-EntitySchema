<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\MediaWiki\Content;

use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\MediaWiki\Content\EntitySchemaContentHandler;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWikiUnitTestCase;

/**
 * @covers \EntitySchema\MediaWiki\Content\EntitySchemaContentHandler
 *
 * @license GPL-2.0-or-later
 */
class EntitySchemaContentHandlerTest extends MediaWikiUnitTestCase {

	public function testSupportsDirectApiEditing() {
		$contentHandler = new EntitySchemaContentHandler(
			EntitySchemaContent::CONTENT_MODEL_ID,
			$this->createMock( IContentHandlerFactory::class )
		);

		$this->assertFalse( $contentHandler->supportsDirectApiEditing() );
	}

	public function testIsParserCacheSupported() {
		$contentHandler = new EntitySchemaContentHandler(
			EntitySchemaContent::CONTENT_MODEL_ID,
			$this->createMock( IContentHandlerFactory::class )
		);

		$this->assertTrue( $contentHandler->isParserCacheSupported() );
	}

}
