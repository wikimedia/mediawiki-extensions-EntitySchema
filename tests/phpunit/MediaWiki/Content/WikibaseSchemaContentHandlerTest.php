<?php

namespace EntitySchema\Tests\MediaWiki\Content;

use PHPUnit\Framework\TestCase;
use EntitySchema\MediaWiki\Content\WikibaseSchemaContentHandler;

/**
 * @covers EntitySchema\MediaWiki\Content\WikibaseSchemaContentHandler
 *
 * @license GPL-2.0-or-later
 */
class WikibaseSchemaContentHandlerTest extends TestCase {

	public function testSupportsDirectApiEditing() {
		$contentHandler = new WikibaseSchemaContentHandler();

		$this->assertFalse( $contentHandler->supportsDirectApiEditing() );
	}

	public function testIsParserCacheSupported() {
		$contentHandler = new WikibaseSchemaContentHandler();

		$this->assertTrue( $contentHandler->isParserCacheSupported() );
	}

}
