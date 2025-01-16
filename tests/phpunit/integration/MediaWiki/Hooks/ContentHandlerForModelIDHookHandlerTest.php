<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Wikibase\Hooks;

use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\MediaWiki\Content\EntitySchemaContentHandler;
use EntitySchema\MediaWiki\Hooks\ContentHandlerForModelIDHookHandler;
use MediaWikiIntegrationTestCase;
use Wikibase\Lib\LanguageNameLookupFactory;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \EntitySchema\MediaWiki\Hooks\ContentHandlerForModelIDHookHandler
 * @group EntitySchemaClient
 * @license GPL-2.0-or-later
 */
class ContentHandlerForModelIDHookHandlerTest extends MediaWikiIntegrationTestCase {

	public function testOnGetContentModels() {
		$services = $this->getServiceContainer();
		$handler = new ContentHandlerForModelIDHookHandler(
			$services->getConfigFactory(),
			$services->getHookContainer(),
			$services->getLanguageNameUtils(),
			$services->getObjectFactory(),
			true,
			$this->createMock( LabelLookup::class ),
			$this->createMock( LanguageNameLookupFactory::class )
		);

		$contentHandler = null;
		$handler->onContentHandlerForModelID( 'EntitySchema', $contentHandler );
		$this->assertInstanceOf( EntitySchemaContentHandler::class, $contentHandler );

		$contentHandler = null;
		$handler->onContentHandlerForModelID( 'something', $contentHandler );
		$this->assertNull( $contentHandler );
	}

	public function testOnGetContentModels_client() {
		$services = $this->getServiceContainer();
		$handler = new ContentHandlerForModelIDHookHandler(
			$services->getConfigFactory(),
			$services->getHookContainer(),
			$services->getLanguageNameUtils(),
			$services->getObjectFactory(),
			false,
			null,
			null,
		);

		$contentHandler = null;
		$handler->onContentHandlerForModelID( 'EntitySchema', $contentHandler );
		$this->assertNull( $contentHandler );
	}

	public function testOnGetContentModels_fieldDefinitions(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseCirrusSearch' );
		$services = $this->getServiceContainer();
		$handler = new ContentHandlerForModelIDHookHandler(
			$services->getConfigFactory(),
			$services->getHookContainer(),
			$services->getLanguageNameUtils(),
			$services->getObjectFactory(),
			true,
			$this->createMock( LabelLookup::class ),
			$this->createMock( LanguageNameLookupFactory::class )
		);

		$contentHandler = null;
		$handler->onContentHandlerForModelID( 'EntitySchema', $contentHandler );
		$contentHandlerWrapper = TestingAccessWrapper::newFromObject( $contentHandler );

		$this->assertNotNull( $contentHandlerWrapper->labelsFieldDefinitions );
		$this->assertNotNull( $contentHandlerWrapper->descriptionsFieldDefinitions );
	}

}
