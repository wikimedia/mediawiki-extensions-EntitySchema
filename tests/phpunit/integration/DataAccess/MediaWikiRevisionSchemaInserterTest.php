<?php

namespace EntitySchema\Tests\Integration\DataAccess;

use CommentStoreComment;
use Content;
use EntitySchema\DataAccess\MediaWikiPageUpdaterFactory;
use EntitySchema\DataAccess\MediaWikiRevisionSchemaInserter;
use EntitySchema\DataAccess\WatchlistUpdater;
use EntitySchema\Domain\Storage\IdGenerator;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use FauxRequest;
use IContextSource;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Storage\PageUpdater;
use MediaWikiIntegrationTestCase;
use RequestContext;
use RuntimeException;
use Status;
use User;

/**
 * @license GPL-2.0-or-later
 *
 * @group Database
 *
 * @covers \EntitySchema\DataAccess\MediaWikiRevisionSchemaInserter
 */
class MediaWikiRevisionSchemaInserterTest extends MediaWikiIntegrationTestCase {

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

		$inserter = new MediaWikiRevisionSchemaInserter(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchNewSchema' ),
			$idGenerator,
			new RequestContext(),
			$this->createConfiguredMock( HookContainer::class, [ 'run' => true ] ),
			$this->getServiceContainer()->getTitleFactory()
		);

		$inserter->insertSchema( $language,
			$label,
			$description,
			$aliases,
			$schemaText
		);
	}

	public function testInsertSchema_commentWithCleanedUpParameters() {
		$expectedComment = CommentStoreComment::newUnsavedComment(
			'/* ' . MediaWikiRevisionSchemaInserter::AUTOCOMMENT_NEWSCHEMA . ' */test label',
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

		$inserter = new MediaWikiRevisionSchemaInserter(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchNewSchema' ),
			$idGenerator,
			new RequestContext(),
			$this->createConfiguredMock( HookContainer::class, [ 'run' => true ] ),
			$this->getServiceContainer()->getTitleFactory()
		);

		$inserter->insertSchema(
			'en',
			'   test label  ',
			'  test description ',
			[ 'test alias', ' test alias ', '  ' ],
			'  test schema text '
		);
	}

	public function testInsertSchema_saveFails() {
		$inserter = $this->newMediaWikiRevisionSchemaInserterFailingToSave();

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'The revision could not be saved' );
		$inserter->insertSchema(
			'en',
			'',
			'test description',
			[ 'abc' ],
			'test schema text'
		);
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
					'/* ' . MediaWikiRevisionSchemaInserter::AUTOCOMMENT_NEWSCHEMA . ' */test label',
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
		$inserter = new MediaWikiRevisionSchemaInserter(
			$this->getPageUpdaterFactory( $pageUpdater ),
			$this->getMockWatchlistUpdater(),
			$this->createConfiguredMock( IdGenerator::class, [ 'getNewId' => 123 ] ),
			$originalContext,
			$this->getServiceContainer()->getHookContainer(),
			$this->getServiceContainer()->getTitleFactory()
		);

		$this->expectException( RuntimeException::class );
		$inserter->insertSchema( 'en', 'test label' );
	}

	private function getPageUpdaterFactoryExpectingContent(
		EntitySchemaContent $expectedContent
	): MediaWikiPageUpdaterFactory {
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'wasSuccessful' )->willReturn( true );
		$pageUpdater->expects( $this->once() )
			->method( 'setContent' )
			->with(
				'main',
				$this->equalTo( $expectedContent )
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
		$pageUpdaterFactory = $this->createMock( MediaWikiPageUpdaterFactory::class );
		if ( $pageUpdater !== null ) {
			$pageUpdaterFactory->method( 'getPageUpdater' )->willReturn( $pageUpdater );
		}
		return $pageUpdaterFactory;
	}

	private function newMediaWikiRevisionSchemaInserterFailingToSave(): MediaWikiRevisionSchemaInserter {

		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'wasSuccessful' )->willReturn( false );

		$idGenerator = $this->createMock( IdGenerator::class );
		$idGenerator->method( 'getNewId' )->willReturn( 123 );

		return new MediaWikiRevisionSchemaInserter(
			$this->getPageUpdaterFactory( $pageUpdater ),
			$this->getMockWatchlistUpdater(),
			$idGenerator,
			new RequestContext(),
			$this->createConfiguredMock( HookContainer::class, [ 'run' => true ] ),
			$this->getServiceContainer()->getTitleFactory()
		);
	}

	/**
	 * @param string|null $methodToExpect
	 *
	 * @return WatchlistUpdater
	 */
	private function getMockWatchlistUpdater( $methodToExpect = null ): WatchlistUpdater {
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
