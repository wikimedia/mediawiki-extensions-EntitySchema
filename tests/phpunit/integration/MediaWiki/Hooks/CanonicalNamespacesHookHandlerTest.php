<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Hooks;

use EntitySchema\MediaWiki\Hooks\CanonicalNamespacesHookHandler;
use MediaWikiIntegrationTestCase;

/**
 * @covers \EntitySchema\MediaWiki\Hooks\CanonicalNamespacesHookHandler
 *
 * @license GPL-2.0-or-later
 * @group Database
 */
class CanonicalNamespacesHookHandlerTest extends MediaWikiIntegrationTestCase {

	public function testOnCanonicalNamespaces() {
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseRepository' );

		$namespaces = [
			NS_MAIN => '',
		];
		$this->overrideConfigValue( 'EntitySchemaIsRepo', true );

		$handler = new CanonicalNamespacesHookHandler();
		$handler->onCanonicalNamespaces( $namespaces );
		// This should not mess up state when invoked multiple times.
		$handler->onCanonicalNamespaces( $namespaces );

		$this->assertSame( '', $namespaces[NS_MAIN] );
		$this->assertSame( 'EntitySchema', $namespaces[640] );
		$this->assertSame( 'EntitySchema_talk', $namespaces[641] );
	}

	public function testOnCanonicalNamespaces_client() {
		$namespaces = [
			NS_MAIN => '',
		];
		$this->overrideConfigValue( 'EntitySchemaIsRepo', false );

		( new CanonicalNamespacesHookHandler )->onCanonicalNamespaces( $namespaces );

		$this->assertSame( [ NS_MAIN => '' ], $namespaces );
	}
}
