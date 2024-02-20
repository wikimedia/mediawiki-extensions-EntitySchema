<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\MediaWiki\Hooks;

use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\MediaWiki\Hooks\ContentModelCanBeUsedOnHookHandler;
use MediaWiki\Title\Title;
use MediaWikiUnitTestCase;

/**
 * @license GPL-2.0-or-later
 *
 * @covers \EntitySchema\MediaWiki\Hooks\ContentModelCanBeUsedOnHookHandler
 */
class ContentModelCanBeUsedOnHookHandlerTest extends MediaWikiUnitTestCase {
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		if ( !defined( 'NS_ENTITYSCHEMA_JSON' ) ) {
			// defined through extension.json, which is not loaded in unit tests
			define( 'NS_ENTITYSCHEMA_JSON', 640 );
		}
	}

	public function testAllowsEntitySchemaContentModel(): void {
		$hookHandler = new ContentModelCanBeUsedOnHookHandler();
		$ok = true;
		$result = $hookHandler->onContentModelCanBeUsedOn( EntitySchemaContent::CONTENT_MODEL_ID,
			Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E1' ), $ok );
		$this->assertNull( $result );
		$this->assertTrue( $ok );
	}

	public function testBlocksContentModelsOtherThanEntitySchema(): void {
		$hookHandler = new ContentModelCanBeUsedOnHookHandler();
		$ok = true;
		$result = $hookHandler->onContentModelCanBeUsedOn( CONTENT_MODEL_WIKITEXT,
			Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E1' ), $ok );
		$this->assertFalse( $result );
		$this->assertFalse( $ok );
	}

	public function testDoesNothingForDifferentNamespaces(): void {
		$hookHandler = new ContentModelCanBeUsedOnHookHandler();
		$ok = true;
		$result = $hookHandler->onContentModelCanBeUsedOn( CONTENT_MODEL_WIKITEXT,
			Title::makeTitle( NS_MEDIAWIKI, 'M1' ), $ok );
		$this->assertNull( $result );
		$this->assertTrue( $ok );
	}
}
