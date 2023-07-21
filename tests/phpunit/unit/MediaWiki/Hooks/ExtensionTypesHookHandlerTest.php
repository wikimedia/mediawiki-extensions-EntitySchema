<?php

declare( strict_types = 1 );

namespace phpunit\unit\MediaWiki\Hooks;

use EntitySchema\MediaWiki\Hooks\ExtensionTypesHookHandler;
use MediaWikiUnitTestCase;

/**
 * @license GPL-2.0-or-later
 *
 * @covers \EntitySchema\MediaWiki\Hooks\ExtensionTypesHookHandler
 */
class ExtensionTypesHookHandlerTest extends MediaWikiUnitTestCase {
	public function testAddsWikibaseToExtensionTypes(): void {
		$hookHandler = new ExtensionTypesHookHandler();
		$extTypes = [];
		$hookHandler->onExtensionTypes( $extTypes );
		$this->assertSame( [ 'wikibase' => 'Wikibase' ], $extTypes );
	}

	public function testDoesNothingIfWikibaseIsSet(): void {
		$hookHandler = new ExtensionTypesHookHandler();
		$extTypes = [ 'wikibase' => 'test' ];
		$hookHandler->onExtensionTypes( $extTypes );
		$this->assertSame( [ 'wikibase' => 'test' ], $extTypes );
	}
}
