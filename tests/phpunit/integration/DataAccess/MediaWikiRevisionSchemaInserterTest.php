<?php

namespace EntitySchema\Tests\Integration\DataAccess;

use CommentStoreComment;
use MediaWiki\Storage\PageUpdater;
use MediaWikiTestCase;
use RuntimeException;
use EntitySchema\DataAccess\MediaWikiPageUpdaterFactory;
use EntitySchema\DataAccess\MediaWikiRevisionSchemaInserter;
use EntitySchema\DataAccess\WatchlistUpdater;
use EntitySchema\Domain\Storage\IdGenerator;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;

/**
 * @license GPL-2.0-or-later
 *
 * @covers \EntitySchema\DataAccess\MediaWikiRevisionSchemaInserter
 */
class MediaWikiRevisionSchemaInserterTest extends MediaWikiTestCase {

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
			$idGenerator
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
			$idGenerator
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

		$this->setExpectedException(
			RuntimeException::class,
			'The revision could not be saved'
		);
		$inserter->insertSchema(
			'en',
			'',
			'test description',
			[ 'abc' ],
			'test schema text'
		);
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

	private function getPageUpdaterFactory( PageUpdater $pageUpdater = null )
	: MediaWikiPageUpdaterFactory {
		$pageUpdaterFactory = $this->createMock( MediaWikiPageUpdaterFactory::class );
		if ( $pageUpdater !== null ) {
			$pageUpdaterFactory->method( 'getPageUpdater' )->willReturn( $pageUpdater );
		}
		return $pageUpdaterFactory;
	}

	private function newMediaWikiRevisionSchemaInserterFailingToSave()
	: MediaWikiRevisionSchemaInserter {

		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'wasSuccessful' )->willReturn( false );

		$idGenerator = $this->createMock( IdGenerator::class );
		$idGenerator->method( 'getNewId' )->willReturn( 123 );

		return new MediaWikiRevisionSchemaInserter(
			$this->getPageUpdaterFactory( $pageUpdater ),
			$this->getMockWatchlistUpdater(),
			$idGenerator
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
