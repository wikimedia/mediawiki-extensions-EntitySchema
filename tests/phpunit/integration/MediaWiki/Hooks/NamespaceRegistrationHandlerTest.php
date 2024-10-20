<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Hooks;

use EntitySchema\MediaWiki\Hooks\NamespaceRegistrationHandler;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWikiIntegrationTestCase;

/**
 * @covers \EntitySchema\MediaWiki\Hooks\NamespaceRegistrationHandler
 *
 * @license GPL-2.0-or-later
 * @group Database
 */
class NamespaceRegistrationHandlerTest extends MediaWikiIntegrationTestCase {

	public function testOnCanonicalNamespaces() {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'WikibaseRepository' ) ) {
			$this->markTestSkipped( 'WikibaseRepo not enabled' );
		}

		$namespaces = [
			NS_MAIN => '',
		];
		$this->overrideConfigValue( 'EntitySchemaIsRepo', true );

		$handler = new NamespaceRegistrationHandler();
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

		( new NamespaceRegistrationHandler )->onCanonicalNamespaces( $namespaces );

		$this->assertSame( [ NS_MAIN => '' ], $namespaces );
	}
}
