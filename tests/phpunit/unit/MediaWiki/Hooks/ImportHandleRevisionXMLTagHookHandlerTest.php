<?php

declare( strict_types = 1 );

namespace phpunit\unit\MediaWiki\Hooks;

use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\MediaWiki\Hooks\ImportHandleRevisionXMLTagHookHandler;
use MediaWikiUnitTestCase;
use RuntimeException;
use WikiImporter;

/**
 * @license GPL-2.0-or-later
 *
 * @covers \EntitySchema\MediaWiki\Hooks\ImportHandleRevisionXMLTagHookHandler
 */
class ImportHandleRevisionXMLTagHookHandlerTest extends MediaWikiUnitTestCase {

	public function testBlocksSchemaImport(): void {
		$hookHandler = new ImportHandleRevisionXMLTagHookHandler();
		$reader = $this->createMock( WikiImporter::class );
		$pageInfo = [];
		$revisionInfo = [ 'model' => EntitySchemaContent::CONTENT_MODEL_ID ];
		$this->expectException( RuntimeException::class );
		$hookHandler->onImportHandleRevisionXMLTag( $reader, $pageInfo, $revisionInfo );
	}
}
