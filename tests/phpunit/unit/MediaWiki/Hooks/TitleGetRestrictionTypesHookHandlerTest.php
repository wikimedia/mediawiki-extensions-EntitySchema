<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\MediaWiki\Hooks;

use EntitySchema\MediaWiki\Hooks\TitleGetRestrictionTypesHookHandler;
use EntitySchema\Tests\Unit\EntitySchemaUnitTestCaseTrait;
use MediaWiki\Title\Title;
use MediaWikiUnitTestCase;

/**
 * @license GPL-2.0-or-later
 *
 * @covers \EntitySchema\MediaWiki\Hooks\TitleGetRestrictionTypesHookHandler
 */
class TitleGetRestrictionTypesHookHandlerTest extends MediaWikiUnitTestCase {
	use EntitySchemaUnitTestCaseTrait;

	public function testRemovesCreateAndMoveFromRestrictionTypes(): void {
		$hookHandler = new TitleGetRestrictionTypesHookHandler( true );
		$types = [ 'delete', 'create', 'move' ];
		$expectedOutput = [ 'delete' ];
		$hookHandler->onTitleGetRestrictionTypes( Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E1' ), $types );
		$this->assertSame( $expectedOutput, $types );
	}

	public function testDoesNothingForDifferentNamespaces(): void {
		$hookHandler = new TitleGetRestrictionTypesHookHandler( true );
		$types = [ 'delete', 'create', 'move' ];
		$expectedOutput = [ 'delete', 'create', 'move' ];
		$hookHandler->onTitleGetRestrictionTypes( Title::makeTitle( NS_MEDIAWIKI, 'M1' ), $types );
		$this->assertSame( $expectedOutput, $types );
	}

	public function testDoesNothingIfRepoDisabled(): void {
		$hookHandler = new TitleGetRestrictionTypesHookHandler( false );
		$types = [ 'delete', 'create', 'move' ];
		$expectedOutput = [ 'delete', 'create', 'move' ];
		$hookHandler->onTitleGetRestrictionTypes( Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E1' ), $types );
		$this->assertSame( $expectedOutput, $types );
	}
}
