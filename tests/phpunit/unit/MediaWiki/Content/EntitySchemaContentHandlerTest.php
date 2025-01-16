<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\MediaWiki\Content;

use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\MediaWiki\Content\EntitySchemaContentHandler;
use MediaWiki\HookContainer\HookContainer;
use MediaWikiUnitTestCase;
use Wikibase\Lib\LanguageNameLookupFactory;
use Wikimedia\ObjectFactory\ObjectFactory;

/**
 * @covers \EntitySchema\MediaWiki\Content\EntitySchemaContentHandler
 *
 * @license GPL-2.0-or-later
 */
class EntitySchemaContentHandlerTest extends MediaWikiUnitTestCase {

	public function testSupportsDirectApiEditing() {
		$contentHandler = new EntitySchemaContentHandler(
			EntitySchemaContent::CONTENT_MODEL_ID,
			$this->createMock( LabelLookup::class ),
			$this->createMock( LanguageNameLookupFactory::class ),
			$this->createMock( ObjectFactory::class ),
			$this->createMock( HookContainer::class ),
			null,
			null
		);

		$this->assertFalse( $contentHandler->supportsDirectApiEditing() );
	}

	public function testIsParserCacheSupported() {
		$contentHandler = new EntitySchemaContentHandler(
			EntitySchemaContent::CONTENT_MODEL_ID,
			$this->createMock( LabelLookup::class ),
			$this->createMock( LanguageNameLookupFactory::class ),
			$this->createMock( ObjectFactory::class ),
			$this->createMock( HookContainer::class ),
			null,
			null
		);

		$this->assertTrue( $contentHandler->isParserCacheSupported() );
	}

}
