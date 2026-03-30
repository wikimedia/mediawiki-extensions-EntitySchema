<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\Wikibase\Hooks;

use EntitySchema\Wikibase\DataValues\EntitySchemaValue;
use EntitySchema\Wikibase\Hooks\WikibaseRepoControllersHookHandler;
use EntitySchema\Wikibase\Search\EntitySchemaSearchHelperFactory;
use MediaWikiIntegrationTestCase;
use Wikibase\Repo\ControllerRegistry;

/**
 * @covers \EntitySchema\Wikibase\Hooks\WikibaseRepoControllersHookHandler
 * @license GPL-2.0-or-later
 */
class WikibaseRepoControllersHookHandlerTest extends MediaWikiIntegrationTestCase {

	public function testOnWikibaseRepoControllers_wbcsEnabled(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseRepository' );
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseCirrusSearch' );

		$controller = static fn () => null;
		$controllers = [ 'unrelated' => $controller ];
		$handler = new WikibaseRepoControllersHookHandler(
			true,
			$this->createStub( EntitySchemaSearchHelperFactory::class )
		);
		$handler->onWikibaseRepoControllers( $controllers );
		$this->assertArrayHasKey( 'unrelated', $controllers );
		$this->assertArrayHasKey( EntitySchemaSearchHelperFactory::ENTITY_TYPE, $controllers );
		$this->assertArrayHasKey(
			ControllerRegistry::WB_SEARCH_ENTITIES_CONTROLLER,
			$controllers[EntitySchemaValue::TYPE]
		);
	}

	public function testOnWikibaseRepoControllers_notInRepoContext(): void {
		$handler = new WikibaseRepoControllersHookHandler( false, null );
		$controllers = [];
		$handler->onWikibaseRepoControllers( $controllers );
		$this->assertEquals( [], $controllers );
	}
}
