<?php
declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\Wikibase\Search;

use EntitySchema\DataAccess\DescriptionLookup;
use EntitySchema\DataAccess\SchemaDataResolvingLabelLookup;
use EntitySchema\Wikibase\Search\EntitySchemaIdSearchHelper;
use EntitySchema\Wikibase\Search\EntitySchemaSearchHelperFactory;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\TitleFactory;
use MediaWikiIntegrationTestCase;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Repo\Api\CombinedEntitySearchHelper;
use Wikibase\Search\Elastic\WikibaseSearchConfig;

/**
 * @covers \EntitySchema\Wikibase\Hooks\WikibaseRepoControllersHookHandler
 * @license GPL-2.0-or-later
 */
class EntitySchemaSearchHelperFactoryTest extends MediaWikiIntegrationTestCase {

	public function testWbcsEnabled(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseRepository' );
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseCirrusSearch' );

		$configFactory = $this->createConfiguredMock( ConfigFactory::class, [
			'getConfigNames' => [ 'OtherExtension', 'WikibaseCirrusSearch' ],
			'makeConfig' => $this->createConfiguredMock( WikibaseSearchConfig::class, [
				'enabled' => true,
			] ),
		] );

		$this->assertInstanceOf( CombinedEntitySearchHelper::class,
			$this->newFactory( $configFactory )->newForLanguage( 'en' )
		);
	}

	public function testWbcsDisabled(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseRepository' );
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseCirrusSearch' );

		$configFactory = $this->createConfiguredMock( ConfigFactory::class, [
			'getConfigNames' => [ 'OtherExtension', 'WikibaseCirrusSearch' ],
			'makeConfig' => $this->createConfiguredMock( WikibaseSearchConfig::class, [
				'enabled' => false,
			] ),
		] );

		$this->assertInstanceOf( EntitySchemaIdSearchHelper::class,
			$this->newFactory( $configFactory )->newForLanguage( 'en' )
		);
	}

	public function testWbcsNotLoaded(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseRepository' );

		$configFactory = $this->createConfiguredMock( ConfigFactory::class, [
			'getConfigNames' => [ 'OtherExtension' /* not 'WikibaseCirrusSearch' */ ],
		] );

		$this->assertInstanceOf( EntitySchemaIdSearchHelper::class,
			$this->newFactory( $configFactory )->newForLanguage( 'en' )
		);
	}

	private function newFactory( ConfigFactory $configFactory ): EntitySchemaSearchHelperFactory {
		return new EntitySchemaSearchHelperFactory(
			$configFactory,
			$this->createMock( TitleFactory::class ),
			$this->createMock( WikiPageFactory::class ),
			$this->createMock( LanguageFallbackChainFactory::class ),
			'https://wiki.example/',
			$this->createMock( DescriptionLookup::class ),
			$this->createMock( SchemaDataResolvingLabelLookup::class )
		);
	}
}
