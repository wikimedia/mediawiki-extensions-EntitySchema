<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Wikibase\Hooks;

use EntitySchema\Wikibase\Hooks\WikibaseRepoSearchableEntityScopesMessagesHookHandler;
use EntitySchema\Wikibase\Search\EntitySchemaSearchHelperFactory;
use MediaWikiUnitTestCase;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers \EntitySchema\Wikibase\Hooks\WikibaseRepoSearchableEntityScopesMessagesHookHandler
 * @license GPL-2.0-or-later
 */
class WikibaseRepoSearchableEntityScopesMessagesHookHandlerTest extends MediaWikiUnitTestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( !class_exists( WikibaseRepo::class ) ) {
			$this->markTestSkipped( 'WikibaseRepo not enabled' );
		}
	}

	public function testOnWikibaseRepoSearchableEntityScopesHookHandlerAddsMessage() {
		$handler = new WikibaseRepoSearchableEntityScopesMessagesHookHandler();

		$messages = [];
		$handler->onWikibaseRepoSearchableEntityScopesMessages( $messages );
		$this->assertEquals(
			WikibaseRepoSearchableEntityScopesMessagesHookHandler::ENTITY_SCHEMA_SCOPE_MESSAGE,
			$messages[EntitySchemaSearchHelperFactory::ENTITY_TYPE]
		);
	}

}
