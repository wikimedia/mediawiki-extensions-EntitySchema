<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\Wikibase\Hooks;

use EntitySchema\DataAccess\DescriptionLookup;
use EntitySchema\DataAccess\SchemaDataResolvingLabelLookup;
use EntitySchema\Wikibase\Hooks\WikibaseRepoEntitySearchHelperCallbacksHookHandler;
use EntitySchema\Wikibase\Search\EntitySchemaIdSearchHelper;
use EntitySchema\Wikibase\Search\EntitySchemaSearchHelperFactory;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Request\WebRequest;
use MediaWiki\Title\TitleFactory;
use MediaWikiIntegrationTestCase;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Repo\Api\CombinedEntitySearchHelper;
use Wikibase\Search\Elastic\WikibaseSearchConfig;

/**
 * @covers \EntitySchema\Wikibase\Hooks\WikibaseRepoEntitySearchHelperCallbacksHookHandler
 * @covers \EntitySchema\Wikibase\Search\EntitySchemaSearchHelperFactory
 * @license GPL-2.0-or-later
 */
class WikibaseRepoEntitySearchHelperCallbacksHookHandlerTest extends MediaWikiIntegrationTestCase {

	public function testOnWikibaseRepoEntitySearchHelperCallbacks_wbcsEnabled(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseRepository' );
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseCirrusSearch' );

		$configFactory = $this->createConfiguredMock( ConfigFactory::class, [
			'getConfigNames' => [ 'OtherExtension', 'WikibaseCirrusSearch' ],
			'makeConfig' => $this->createConfiguredMock( WikibaseSearchConfig::class, [
				'enabled' => true,
			] ),
		] );
		$callback1 = fn () => null;
		$callbacks = [
			'unrelated' => $callback1,
		];
		$factory = new EntitySchemaSearchHelperFactory(
			$configFactory,
			$this->createMock( TitleFactory::class ),
			$this->createMock( WikiPageFactory::class ),
			$this->createMock( LanguageFallbackChainFactory::class ),
			'https://wiki.example/',
			$this->createMock( DescriptionLookup::class ),
			$this->createMock( SchemaDataResolvingLabelLookup::class )
		);

		( new WikibaseRepoEntitySearchHelperCallbacksHookHandler( true, $factory ) )
			->onWikibaseRepoEntitySearchHelperCallbacks( $callbacks );

		$this->assertSame( $callback1, $callbacks['unrelated'] );
		$this->assertArrayHasKey( EntitySchemaSearchHelperFactory::ENTITY_TYPE, $callbacks );
		$callback2 = $callbacks[EntitySchemaSearchHelperFactory::ENTITY_TYPE];
		$request = $this->createMock( WebRequest::class );
		$helper = $callback2( $request );
		$this->assertInstanceOf( CombinedEntitySearchHelper::class, $helper );
	}

	public function testOnWikibaseRepoEntitySearchHelperCallbacks_wbcsDisabled(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseRepository' );
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseCirrusSearch' );

		$configFactory = $this->createConfiguredMock( ConfigFactory::class, [
			'getConfigNames' => [ 'OtherExtension', 'WikibaseCirrusSearch' ],
			'makeConfig' => $this->createConfiguredMock( WikibaseSearchConfig::class, [
				'enabled' => false,
			] ),
		] );
		$callbacks = [];
		$factory = new EntitySchemaSearchHelperFactory(
			$configFactory,
			$this->createMock( TitleFactory::class ),
			$this->createMock( WikiPageFactory::class ),
			$this->createMock( LanguageFallbackChainFactory::class ),
			'https://wiki.example/',
			$this->createMock( DescriptionLookup::class ),
			$this->createMock( SchemaDataResolvingLabelLookup::class )
		);

		( new WikibaseRepoEntitySearchHelperCallbacksHookHandler( true, $factory ) )
			->onWikibaseRepoEntitySearchHelperCallbacks( $callbacks );

		$this->assertArrayHasKey( EntitySchemaSearchHelperFactory::ENTITY_TYPE, $callbacks );
		$callback = $callbacks[EntitySchemaSearchHelperFactory::ENTITY_TYPE];
		$request = $this->createMock( WebRequest::class );
		$helper = $callback( $request );
		$this->assertInstanceOf( EntitySchemaIdSearchHelper::class, $helper );
	}

	public function testOnWikibaseRepoEntitySearchHelperCallbacks_wbcsNotLoaded(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseRepository' );

		$configFactory = $this->createConfiguredMock( ConfigFactory::class, [
			'getConfigNames' => [ 'OtherExtension' /* not 'WikibaseCirrusSearch' */ ],
		] );
		$callbacks = [];
		$factory = new EntitySchemaSearchHelperFactory(
			$configFactory,
			$this->createMock( TitleFactory::class ),
			$this->createMock( WikiPageFactory::class ),
			$this->createMock( LanguageFallbackChainFactory::class ),
			'https://wiki.example/',
			$this->createMock( DescriptionLookup::class ),
			$this->createMock( SchemaDataResolvingLabelLookup::class )
		);

		( new WikibaseRepoEntitySearchHelperCallbacksHookHandler( true, $factory ) )
			->onWikibaseRepoEntitySearchHelperCallbacks( $callbacks );

		$this->assertArrayHasKey( EntitySchemaSearchHelperFactory::ENTITY_TYPE, $callbacks );
		$callback = $callbacks[EntitySchemaSearchHelperFactory::ENTITY_TYPE];
		$request = $this->createMock( WebRequest::class );
		$helper = $callback( $request );
		$this->assertInstanceOf( EntitySchemaIdSearchHelper::class, $helper );
	}

}
