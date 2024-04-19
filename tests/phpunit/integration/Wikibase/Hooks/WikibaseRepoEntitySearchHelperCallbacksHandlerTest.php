<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\Wikibase\Hooks;

use EntitySchema\DataAccess\DescriptionLookup;
use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\Wikibase\Hooks\WikibaseRepoEntitySearchHelperCallbacksHandler;
use EntitySchema\Wikibase\Search\EntitySchemaSearchHelper;
use EntitySchema\Wikibase\Search\EntitySchemaSearchHelperFactory;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\TitleFactory;
use MediaWikiIntegrationTestCase;
use WebRequest;

/**
 * @covers \EntitySchema\Wikibase\Hooks\WikibaseRepoEntitySearchHelperCallbacksHandler
 * @covers \EntitySchema\Wikibase\Search\EntitySchemaSearchHelperFactory
 * @license GPL-2.0-or-later
 */
class WikibaseRepoEntitySearchHelperCallbacksHandlerTest extends MediaWikiIntegrationTestCase {

	public function testOnWikibaseRepoEntitySearchHelperCallbacks(): void {
		$callback1 = fn () => null;
		$callbacks = [
			'unrelated' => $callback1,
		];
		$factory = new EntitySchemaSearchHelperFactory(
			$this->createMock( TitleFactory::class ),
			$this->createMock( WikiPageFactory::class ),
			'https://wiki.example/',
			$this->createMock( DescriptionLookup::class ),
			$this->createMock( LabelLookup::class )
		);

		( new WikibaseRepoEntitySearchHelperCallbacksHandler( $factory ) )
			->onWikibaseRepoEntitySearchHelperCallbacks( $callbacks );

		$this->assertSame( $callback1, $callbacks['unrelated'] );
		$this->assertArrayHasKey( EntitySchemaSearchHelper::ENTITY_TYPE, $callbacks );
		$callback2 = $callbacks[EntitySchemaSearchHelper::ENTITY_TYPE];
		$request = $this->createMock( WebRequest::class );
		$this->assertInstanceOf( EntitySchemaSearchHelper::class, $callback2( $request ) );
	}

}
