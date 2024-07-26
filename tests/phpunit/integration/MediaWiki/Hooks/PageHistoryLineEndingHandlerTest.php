<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Hooks;

use Article;
use EntitySchema\DataAccess\MediaWikiRevisionEntitySchemaInserter;
use EntitySchema\DataAccess\MediaWikiRevisionEntitySchemaUpdater;
use EntitySchema\DataAccess\WatchlistUpdater;
use EntitySchema\Domain\Storage\IdGenerator;
use EntitySchema\MediaWiki\EntitySchemaServices;
use ExtensionRegistry;
use Language;
use MediaWiki\Context\RequestContext;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\Request\FauxRequest;
use MediaWikiIntegrationTestCase;

/**
 * @covers \EntitySchema\MediaWiki\Hooks\PageHistoryLineEndingHandler
 *
 * @license GPL-2.0-or-later
 * @group Database
 */
class PageHistoryLineEndingHandlerTest extends MediaWikiIntegrationTestCase {

	public function testOneRestoreLink(): void {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'WikibaseRepository' ) ) {
			$this->markTestSkipped( 'WikibaseRepo not enabled' );
		}

		$context = new RequestContext();
		$context->setRequest( new FauxRequest() );

		$services = $this->getServiceContainer();
		$updaterFactory = EntitySchemaServices::getMediaWikiPageUpdaterFactory( $services );
		$watchListUpdater = $this->createMock( WatchlistUpdater::class );
		$language = $this->createConfiguredMock( Language::class,
			[ 'truncateForVisual' => '' ] );
		$languageFactory = $this->createConfiguredMock( LanguageFactory::class,
			[ 'getLanguage' => $language ] );
		$hookContainer = $this->createConfiguredMock( HookContainer::class,
			[ 'run' => true ] );
		$schemaInserter = new MediaWikiRevisionEntitySchemaInserter(
			$updaterFactory,
			$watchListUpdater,
			$this->createConfiguredMock( IdGenerator::class, [ 'getNewId' => 1 ] ),
			$context,
			$languageFactory,
			$hookContainer
		);
		$status = $schemaInserter->insertSchema( 'en' );
		$this->assertStatusGood( $status );
		$schemaId = $status->getEntitySchemaId();

		$revisionLookup = $services->getRevisionLookup();
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$updaterFactory,
			$watchListUpdater,
			$context,
			$revisionLookup,
			$languageFactory,
			$hookContainer
		);
		$schemaTitle = $services
			->getTitleFactory()
			->makeTitleSafe( NS_ENTITYSCHEMA_JSON, $schemaId->getId() );
		$context->setTitle( $schemaTitle );
		$baseRevId = $revisionLookup->getKnownCurrentRevision( $schemaTitle )->getId();
		$schemaUpdater->updateSchemaText( $schemaId, 'a', $baseRevId );

		$action = $services
			->getActionFactory()
			->getAction(
				'history',
				Article::newFromTitle( $schemaTitle, $context ),
				$context
			);
		$action->show();
		$html = $action->getOutput()->getHTML();

		$this->assertSame( 1, substr_count( $html, 'restore=' ),
			'expecting exactly one restore= link in HTML: ' . $html );
	}

}
