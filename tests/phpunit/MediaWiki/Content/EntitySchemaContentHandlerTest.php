<?php

namespace EntitySchema\Tests\MediaWiki\Content;

use PHPUnit\Framework\TestCase;
use EntitySchema\MediaWiki\Content\EntitySchemaContentHandler;

/**
 * @covers \EntitySchema\MediaWiki\Content\EntitySchemaContentHandler
 *
 * @license GPL-2.0-or-later
 */
class EntitySchemaContentHandlerTest extends TestCase {

	public function testSupportsDirectApiEditing() {
		$contentHandler = new EntitySchemaContentHandler();

		$this->assertFalse( $contentHandler->supportsDirectApiEditing() );
	}

	public function testIsParserCacheSupported() {
		$contentHandler = new EntitySchemaContentHandler();

		$this->assertTrue( $contentHandler->isParserCacheSupported() );
	}

}
