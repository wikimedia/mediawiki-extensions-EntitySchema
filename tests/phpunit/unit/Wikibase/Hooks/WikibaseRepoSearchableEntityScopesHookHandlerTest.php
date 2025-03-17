<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Wikibase\Hooks;

use EntitySchema\Wikibase\Hooks\WikibaseRepoSearchableEntityScopesHookHandler;
use EntitySchema\Wikibase\Search\EntitySchemaSearchHelperFactory;
use MediaWikiUnitTestCase;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers \EntitySchema\Wikibase\Hooks\WikibaseRepoSearchableEntityScopesHookHandler
 * @license GPL-2.0-or-later
 */
class WikibaseRepoSearchableEntityScopesHookHandlerTest extends MediaWikiUnitTestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( !class_exists( WikibaseRepo::class ) ) {
			$this->markTestSkipped( 'WikibaseRepo not enabled' );
		}
	}

	public function testOnWikibaseRepoSearchableEntityScopesHookHandlerAddsScope() {
		$handler = new WikibaseRepoSearchableEntityScopesHookHandler();

		$scopes = [];
		$handler->onWikibaseRepoSearchableEntityScopes( $scopes );
		$this->assertEquals( NS_ENTITYSCHEMA_JSON, $scopes[EntitySchemaSearchHelperFactory::ENTITY_TYPE] );
	}

	public function testOnWikibaseRepoSearchableEntityScopesHookHandlerPreservesExistingScope() {
		$handler = new WikibaseRepoSearchableEntityScopesHookHandler();

		$scopes = [ EntitySchemaSearchHelperFactory::ENTITY_TYPE => 1 ];
		$handler->onWikibaseRepoSearchableEntityScopes( $scopes );
		$this->assertSame( 1, $scopes[EntitySchemaSearchHelperFactory::ENTITY_TYPE] );
	}
}
