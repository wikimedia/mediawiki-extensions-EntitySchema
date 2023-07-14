<?php

declare( strict_types = 1 );

namespace phpunit\unit\MediaWiki\Hooks;

use EntitySchema\MediaWiki\Hooks\TitleGetRestrictionTypesHookHandler;
use MediaWiki\Title\Title;
use MediaWikiUnitTestCase;

/**
 * @license GPL-2.0-or-later
 *
 * @covers \EntitySchema\MediaWiki\Hooks\TitleGetRestrictionTypesHookHandler
 */
class TitleGetRestrictionTypesHookHandlerTest extends MediaWikiUnitTestCase {
	public function testRemovesCreateAndMoveFromRestrictionTypes(): void {
		$hookHandler = new TitleGetRestrictionTypesHookHandler();
		$types = [ 'delete', 'create', 'move' ];
		$expectedOutput = [ 'delete' ];
		$hookHandler->onTitleGetRestrictionTypes( Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E1' ), $types );
		$this->assertSame( $expectedOutput, $types );
	}

	public function testDoesNothingForDifferentNamespaces(): void {
		$hookHandler = new TitleGetRestrictionTypesHookHandler();
		$types = [ 'delete', 'create', 'move' ];
		$expectedOutput = [ 'delete', 'create', 'move' ];
		$hookHandler->onTitleGetRestrictionTypes( Title::makeTitle( NS_MEDIAWIKI, 'M1' ), $types );
		$this->assertSame( $expectedOutput, $types );
	}
}
