<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\MediaWiki\Hooks;

use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\MediaWiki\Hooks\ContentModelCanBeUsedOnHookHandler;
use EntitySchema\Tests\Unit\EntitySchemaUnitTestCaseTrait;
use MediaWiki\Title\Title;
use MediaWikiUnitTestCase;

/**
 * @license GPL-2.0-or-later
 *
 * @covers \EntitySchema\MediaWiki\Hooks\ContentModelCanBeUsedOnHookHandler
 */
class ContentModelCanBeUsedOnHookHandlerTest extends MediaWikiUnitTestCase {
	use EntitySchemaUnitTestCaseTrait;

	public function testAllowsEntitySchemaContentModel(): void {
		$hookHandler = new ContentModelCanBeUsedOnHookHandler( true );
		$ok = true;
		$result = $hookHandler->onContentModelCanBeUsedOn( EntitySchemaContent::CONTENT_MODEL_ID,
			Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E1' ), $ok );
		$this->assertNull( $result );
		$this->assertTrue( $ok );
	}

	public function testBlocksContentModelsOtherThanEntitySchema(): void {
		$hookHandler = new ContentModelCanBeUsedOnHookHandler( true );
		$ok = true;
		$result = $hookHandler->onContentModelCanBeUsedOn( CONTENT_MODEL_WIKITEXT,
			Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E1' ), $ok );
		$this->assertFalse( $result );
		$this->assertFalse( $ok );
	}

	public function testDoesNothingForDifferentNamespaces(): void {
		$hookHandler = new ContentModelCanBeUsedOnHookHandler( true );
		$ok = true;
		$result = $hookHandler->onContentModelCanBeUsedOn( CONTENT_MODEL_WIKITEXT,
			Title::makeTitle( NS_MEDIAWIKI, 'M1' ), $ok );
		$this->assertNull( $result );
		$this->assertTrue( $ok );
	}

	public function testDoesNothingIfRepoDisabled(): void {
		$hookHandler = new ContentModelCanBeUsedOnHookHandler( false );
		$ok = true;
		$result = $hookHandler->onContentModelCanBeUsedOn( CONTENT_MODEL_WIKITEXT,
			Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E1' ), $ok );
		$this->assertNull( $result );
		$this->assertTrue( $ok );
	}
}
