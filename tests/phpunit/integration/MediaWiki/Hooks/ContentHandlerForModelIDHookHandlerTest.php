<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Wikibase\Hooks;

use EntitySchema\MediaWiki\Content\EntitySchemaContentHandler;
use EntitySchema\MediaWiki\Hooks\ContentHandlerForModelIDHookHandler;
use MediaWikiIntegrationTestCase;

/**
 * @covers \EntitySchema\MediaWiki\Hooks\ContentHandlerForModelIDHookHandler
 * @group EntitySchemaClient
 * @license GPL-2.0-or-later
 */
class ContentHandlerForModelIDHookHandlerTest extends MediaWikiIntegrationTestCase {

	public function testOnGetContentModels() {
		$handler = new ContentHandlerForModelIDHookHandler(
			true
		);

		$contentHandler = null;
		$handler->onContentHandlerForModelID( 'EntitySchema', $contentHandler );
		$this->assertInstanceOf( EntitySchemaContentHandler::class, $contentHandler );

		$contentHandler = null;
		$handler->onContentHandlerForModelID( 'something', $contentHandler );
		$this->assertNull( $contentHandler );
	}

	public function testOnGetContentModels_client() {
		$handler = new ContentHandlerForModelIDHookHandler(
			false
		);

		$contentHandler = null;
		$handler->onContentHandlerForModelID( 'EntitySchema', $contentHandler );
		$this->assertNull( $contentHandler );
	}

}
