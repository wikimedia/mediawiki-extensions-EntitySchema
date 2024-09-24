<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\DataAccess;

use Content;
use EntitySchema\DataAccess\MediaWikiPageUpdaterFactory;
use EntitySchema\DataAccess\MediaWikiRevisionEntitySchemaInserter;
use EntitySchema\DataAccess\WatchlistUpdater;
use EntitySchema\Domain\Storage\IdGenerator;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\MediaWiki\EntitySchemaServices;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use MediaWiki\Storage\PageUpdater;
use MediaWiki\Storage\PageUpdateStatus;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use WikiPage;

/**
 * @license GPL-2.0-or-later
 *
 * @group Database
 *
 * @covers \EntitySchema\DataAccess\MediaWikiRevisionEntitySchemaInserter
 */
class MediaWikiRevisionEntitySchemaInserterTest extends MediaWikiIntegrationTestCase {

	use TempUserTestTrait;

	public function testInsertSchema() {
		$language = 'en';
		$label = 'test_label';
		$description = 'test_description';
		$aliases = [ 'test_alias1', 'testalias_2' ];
		$schemaText = '#some fake schema {}';
		$id = 'E123';

		$expectedContent = new EntitySchemaContent(
			json_encode(
				[
					'id' => $id,
					'serializationVersion' => '3.0',
					'labels' => [
						$language => $label,
					],
					'descriptions' => [
						$language => $description,
					],
					'aliases' => [
						$language => $aliases,
					],
					'schemaText' => $schemaText,
					'type' => 'ShExC',
				]
			)
		);

		$pageUpdaterFactory = $this
			->getPageUpdaterFactoryExpectingContent( $expectedContent );

		$idGenerator = $this->createMock( IdGenerator::class );
		$idGenerator->method( 'getNewId' )->willReturn( 123 );

		$inserter = new MediaWikiRevisionEntitySchemaInserter(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchNewSchema' ),
			$idGenerator,
			new RequestContext(),
			$this->getServiceContainer()->getLanguageFactory(),
			$this->createConfiguredMock( HookContainer::class, [ 'run' => true ] )
		);

		$this->assertStatusGood( $inserter->insertSchema( $language,
			$label,
			$description,
			$aliases,
			$schemaText
		) );
	}

	public function testInsertSchema_commentWithCleanedUpParameters() {
		$expectedComment = CommentStoreComment::newUnsavedComment(
			'/* ' . MediaWikiRevisionEntitySchemaInserter::AUTOCOMMENT_NEWSCHEMA . ' */test label',
			[
				'key' => 'entityschema-summary-newschema-nolabel',
				'language' => 'en',
				'label' => 'test label',
				'description' => 'test description',
				'aliases' => [ 'test alias' ],
				'schemaText_truncated' => 'test schema text',
			]
		);
		$pageUpdaterFactory = $this->getPageUpdaterFactoryExpectingComment( $expectedComment );

		$idGenerator = $this->createMock( IdGenerator::class );
		$idGenerator->method( 'getNewId' )->willReturn( 123 );

		$inserter = new MediaWikiRevisionEntitySchemaInserter(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchNewSchema' ),
			$idGenerator,
			new RequestContext(),
			$this->getServiceContainer()->getLanguageFactory(),
			$this->createConfiguredMock( HookContainer::class, [ 'run' => true ] )
		);

		$this->assertStatusGood( $inserter->insertSchema(
			'en',
			'   test label  ',
			'  test description ',
			[ 'test alias', ' test alias ', '  ' ],
			'  test schema text '
		) );
	}

	public function testInsertSchema_saveFails() {
		$inserter = $this->newInserterFailingToSave();

		$status = $inserter->insertSchema(
			'en',
			'',
			'test description',
			[ 'abc' ],
			'test schema text'
		);

		$this->assertStatusError( __CLASS__, $status );
	}

	public function testInsertSchema_rejectedByEditFilter() {
		$originalRequest = new FauxRequest();
		$originalUser = $this->getTestUser()->getUser();
		$originalContext = new RequestContext();
		$originalContext->setRequest( $originalRequest );
		$originalContext->setUser( $originalUser );
		$pageIdentity = new PageIdentityValue( 1, NS_ENTITYSCHEMA_JSON, 'E123', false );
		$this->setTemporaryHook(
			'EditFilterMergedContent',
			function (
				IContextSource $context,
				Content $content,
				Status $status,
				string $summary,
				User $user,
				bool $minoredit
			) use ( $originalRequest, $originalUser, $pageIdentity ) {
				$this->assertSame( $originalRequest, $context->getRequest() );
				$this->assertSame( $originalUser, $user );
				$this->assertTrue( $context->getTitle()->isSamePageAs( $pageIdentity ) );
				$this->assertInstanceOf( EntitySchemaContent::class, $content );
				$this->assertSame(
					'/* ' . MediaWikiRevisionEntitySchemaInserter::AUTOCOMMENT_NEWSCHEMA . ' */test label',
					$summary
				);
				$this->assertFalse( $minoredit );

				$status->fatal( __CLASS__ );
				return false;
			}
		);
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'getPage' )
			->willReturn( $pageIdentity );
		$pageUpdater->expects( $this->never() )
			->method( 'saveRevision' );
		$inserter = new MediaWikiRevisionEntitySchemaInserter(
			$this->getPageUpdaterFactory( $pageUpdater ),
			$this->getMockWatchlistUpdater(),
			$this->createConfiguredMock( IdGenerator::class, [ 'getNewId' => 123 ] ),
			$originalContext,
			$this->getServiceContainer()->getLanguageFactory(),
			$this->getServiceContainer()->getHookContainer()
		);

		$status = $inserter->insertSchema( 'en', 'test label' );

		$this->assertStatusError( __CLASS__, $status );
	}

	public function testInsertSchema_createTempUser(): void {
		$this->enableAutoCreateTempUser();
		$services = $this->getServiceContainer();
		$inserter = new MediaWikiRevisionEntitySchemaInserter(
			EntitySchemaServices::getMediaWikiPageUpdaterFactory( $services ),
			EntitySchemaServices::getWatchlistUpdater( $services ),
			$this->createConfiguredMock( IdGenerator::class, [ 'getNewId' => 123 ] ),
			new RequestContext(),
			$services->getLanguageFactory(),
			$this->createConfiguredMock( HookContainer::class, [ 'run' => true ] )
		);

		$status = $inserter->insertSchema( 'en' );
		$this->assertStatusGood( $status );
		$savedTempUser = $status->getSavedTempUser();
		$newContext = $status->getContext();

		$this->assertTrue( $services->getUserIdentityUtils()->isTemp( $savedTempUser ) );
		$this->assertSame( $savedTempUser, $newContext->getUser() );
	}

	private function getPageUpdaterFactoryExpectingContent(
		EntitySchemaContent $expectedContent
	): MediaWikiPageUpdaterFactory {
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'wasSuccessful' )->willReturn( true );
		$pageUpdater->expects( $this->once() )
			->method( 'setContent' )
			->with(
				SlotRecord::MAIN,
				$expectedContent
			);

		return $this->getPageUpdaterFactory( $pageUpdater );
	}

	private function getPageUpdaterFactoryExpectingComment(
		CommentStoreComment $expectedComment
	): MediaWikiPageUpdaterFactory {
		$pageUpdater = $this->createMock( PageUpdater::class );

		$pageUpdater->method( 'wasSuccessful' )->willReturn( true );
		$pageUpdater->expects( $this->once() )
			->method( 'saveRevision' )
			->with( $expectedComment );
		$pageUpdater->method( 'wasSuccessful' )->willReturn( true );

		return $this->getPageUpdaterFactory( $pageUpdater );
	}

	private function getPageUpdaterFactory( PageUpdater $pageUpdater = null ): MediaWikiPageUpdaterFactory {
		$wikiPage = $this->createConfiguredMock( WikiPage::class, [
			'newPageUpdater' => $pageUpdater ?? $this->createMock( PageUpdater::class ),
		] );
		$wikiPageFactory = $this->createConfiguredMock( WikiPageFactory::class, [
			'newFromTitle' => $wikiPage,
		] );
		$this->setService( 'WikiPageFactory', $wikiPageFactory );
		return EntitySchemaServices::getMediaWikiPageUpdaterFactory( $this->getServiceContainer() );
	}

	private function newInserterFailingToSave(): MediaWikiRevisionEntitySchemaInserter {
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'getStatus' )->willReturn( PageUpdateStatus::newFatal( __CLASS__ ) );

		$idGenerator = $this->createMock( IdGenerator::class );
		$idGenerator->method( 'getNewId' )->willReturn( 123 );

		return new MediaWikiRevisionEntitySchemaInserter(
			$this->getPageUpdaterFactory( $pageUpdater ),
			$this->getMockWatchlistUpdater(),
			$idGenerator,
			new RequestContext(),
			$this->getServiceContainer()->getLanguageFactory(),
			$this->createConfiguredMock( HookContainer::class, [ 'run' => true ] )
		);
	}

	private function getMockWatchlistUpdater( ?string $methodToExpect = null ): WatchlistUpdater {
		$mockWatchlistUpdater = $this->getMockBuilder( WatchlistUpdater::class )
			->disableOriginalConstructor()
			->getMock();
		if ( $methodToExpect === null ) {
			$mockWatchlistUpdater->expects( $this->never() )->method( $this->anything() );
		} else {
			$mockWatchlistUpdater->expects( $this->once() )->method( $methodToExpect );
		}
		return $mockWatchlistUpdater;
	}

}
