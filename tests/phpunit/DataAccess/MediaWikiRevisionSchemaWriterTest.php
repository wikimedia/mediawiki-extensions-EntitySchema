<?php

namespace Wikibase\Schema\Tests\DataAccess;

use CommentStoreComment;
use DomainException;
use InvalidArgumentException;
use MediaWiki\Storage\RevisionRecord;
use Message;
use MessageLocalizer;
use \RuntimeException;
use MediaWiki\Storage\PageUpdater;
use stdClass;
use Wikibase\Schema\DataAccess\EditConflict;
use Wikibase\Schema\DataAccess\MediaWikiPageUpdaterFactory;
use Wikibase\Schema\DataAccess\MediaWikiRevisionSchemaWriter;
use Wikibase\Schema\DataAccess\WatchlistUpdater;
use Wikibase\Schema\Domain\Model\SchemaId;
use Wikibase\Schema\Domain\Storage\IdGenerator;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;

/**
 * @covers \Wikibase\Schema\DataAccess\MediaWikiRevisionSchemaWriter
 * @license GPL-2.0-or-later
 */
class MediaWikiRevisionSchemaWriterTest extends \PHPUnit_Framework_TestCase {
	use \PHPUnit4And6Compat;

	public function testInsertSchema() {
		$language = 'en';
		$label = 'test_label';
		$description = 'test_description';
		$aliases = [ 'test_alias1', 'testalias_2' ];
		$schemaText = '#some fake schema {}';
		$id = 'O123';

		$expectedContent = new WikibaseSchemaContent(
			json_encode(
				[
					'id' => $id,
					'serializationVersion' => '3.0',
					'labels' => [
						$language => $label
					],
					'descriptions' => [
						$language => $description
					],
					'aliases' => [
						$language => $aliases
					],
					'schemaText' => $schemaText,
					'type' => 'ShExC',
				]
			)
		);

		$pageUpdaterFactory = $this
			->getPageUpdaterFactoryProvidingAndExpectingContent( $expectedContent );

		$idGenerator = $this->createMock( IdGenerator::class );
		$idGenerator->method( 'getNewId' )->willReturn( 123 );

		$writer = new MediaWikiRevisionSchemaWriter(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater( 'optionallyWatchNewSchema' ),
			$idGenerator
		);

		$writer->insertSchema( $language,
			$label,
			$description,
			$aliases,
			$schemaText
		);
	}

	public function testInsertSchema_commentWithCleanedUpParameters() {
		$expectedComment = CommentStoreComment::newUnsavedComment(
			'/* ' . MediaWikiRevisionSchemaWriter::AUTOCOMMENT_NEWSCHEMA . ' */ test label',
			[
				'key' => 'wikibaseschema-summary-newschema-nolabel',
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

		$writer = new MediaWikiRevisionSchemaWriter(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater( 'optionallyWatchNewSchema' ),
			$idGenerator
		);

		$writer->insertSchema(
			'en',
			'   test label  ',
			'  test description ',
			[ 'test alias', ' test alias ', '  ' ],
			'  test schema text '
		);
	}

	public function testInsertSchema_saveFails() {
		$writer = $this->newMediaWikiRevisionSchemaWriterFailingToSave();

		$this->setExpectedException(
			RuntimeException::class,
			'The revision could not be saved'
		);
		$writer->insertSchema(
			'en',
			'',
			'test description',
			[ 'abc' ],
			'test schema text'
		);
	}

	private function getPageUpdaterFactoryProvidingAndExpectingContent(
		WikibaseSchemaContent $expectedContent,
		WikibaseSchemaContent $existingContent = null
	): MediaWikiPageUpdaterFactory {
		$pageUpdater = $this->createMock( PageUpdater::class );
		if ( $existingContent !== null ) {
			$revisionRecord = $this->createMockRevisionRecord( $existingContent );
			$pageUpdater->method( 'grabParentRevision' )->willReturn( $revisionRecord );
		}
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
		CommentStoreComment $expectedComment,
		WikibaseSchemaContent $existingContent = null
	): MediaWikiPageUpdaterFactory {
		$pageUpdater = $this->createMock( PageUpdater::class );
		if ( $existingContent !== null ) {
			$revisionRecord = $this->createMock( RevisionRecord::class );
			$revisionRecord->method( 'getContent' )->willReturn( $existingContent );
			$pageUpdater->method( 'grabParentRevision' )->willReturn( $revisionRecord );
		}
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

	private function getMessageLocalizer(): MessageLocalizer {
		$msgLocalizer = $this->createMock( MessageLocalizer::class );
		$msgLocalizer->method( 'msg' )->willReturn( new Message( '' ) );

		return $msgLocalizer;
	}

	private function newMediaWikiRevisionSchemaWriterFailingToSave(): MediaWikiRevisionSchemaWriter {
		$existingContent = new WikibaseSchemaContent( '{}' );
		$revisionRecord = $this->createMockRevisionRecord( $existingContent );

		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'wasSuccessful' )->willReturn( false );
		$pageUpdater->method( 'grabParentRevision' )->willReturn( $revisionRecord );

		$idGenerator = $this->createMock( IdGenerator::class );
		$idGenerator->method( 'getNewId' )->willReturn( 123 );

		return new MediaWikiRevisionSchemaWriter(
			$this->getPageUpdaterFactory( $pageUpdater ),
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater(),
			$idGenerator
		);
	}

	public function testOverwriteWholeSchema_throwsForNonExistantPage() {
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdaterFactory = $this->getPageUpdaterFactory( $pageUpdater );
		$idGenerator = $this->createMock( IdGenerator::class );
		$writer = new MediaWikiRevisionSchemaWriter(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater(),
			$idGenerator
		);

		$this->expectException( RuntimeException::class );
		$writer->overwriteWholeSchema(
			new SchemaId( 'O123456999999999' ),
			[],
			[],
			[],
			'',
			1
		);
	}

	public function provideBadParameters() {
		$langExceptionMsg = 'language codes must be valid!';
		$typeExceptionMsg = 'language, label, description and schemaText must be strings '
			. 'and aliases must be an array of strings';
		return [
			'language is not supported' => [ 'not a real langcode', '', '', [], '', $langExceptionMsg ],
			'label is not string' => [ 'de', new StdClass(), '', [], '', $typeExceptionMsg ],
			'description is not string' => [ 'en', '', new StdClass(), [], '', $typeExceptionMsg ],
			'aliases is non-string array' => [ 'fr', '', '', [ new stdClass() ], '', $typeExceptionMsg ],
			'aliases is mixed array' => [ 'ar', '', '', [ new stdClass(), 'foo' ], '', $typeExceptionMsg ],
			'aliases is associative array' => [ 'hu', '', '', [ 'en' => 'foo' ], '', $typeExceptionMsg ],
			'schema text is not string' => [ 'he', '', '', [], new StdClass(), $typeExceptionMsg ],
		];
	}

	/**
	 * @dataProvider provideBadParameters
	 */
	public function testOverwriteWholeSchema_throwsForInvalidParams(
		$testLanguage,
		$testLabel,
		$testDescription,
		$testAliases,
		$testSchemaText,
		$exceptionMessage
	) {
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'grabParentRevision' )->willReturn(
			$this->createMockRevisionRecord()
		);
		$pageUpdaterFactory = $this->getPageUpdaterFactory( $pageUpdater );

		$writer = new MediaWikiRevisionSchemaWriter(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater()
		);
		$this->setExpectedException( InvalidArgumentException::class, $exceptionMessage );
		$writer->overwriteWholeSchema(
			new SchemaId( 'O1' ),
			[ $testLanguage => $testLabel ],
			[ $testLanguage => $testDescription ],
			[ $testLanguage => $testAliases ],
			$testSchemaText,
			1
		);
	}

	public function testOverwriteWholeSchema_WritesExpectedContentForOverwritingMonoLingualSchema() {
		$id = 'O1';
		$language = 'en';
		$label = 'englishLabel';
		$description = 'englishDescription';
		$aliases = [ 'englishAlias' ];
		$schemaText = '#some schema about goats';
		$existingContent = new WikibaseSchemaContent( '' );
		$expectedContent = new WikibaseSchemaContent( json_encode(
			[
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => [
					$language => $label
				],
				'descriptions' => [
					$language => $description
				],
				'aliases' => [
					$language => $aliases
				],
				'schemaText' => $schemaText,
				'type' => 'ShExC',
			]
		) );
		$pageUpdaterFactory = $this
			->getPageUpdaterFactoryProvidingAndExpectingContent( $expectedContent, $existingContent );
		$writer = new MediaWikiRevisionSchemaWriter(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' )
		);
		$writer->overwriteWholeSchema(
			new SchemaId( 'O1' ),
			[ 'en' => 'englishLabel' ],
			[ 'en' => 'englishDescription' ],
			[ 'en' => $aliases ],
			$schemaText,
			1
		);
	}

	public function testOverwriteWholeSchema_saveFails() {
		$writer = $this->newMediaWikiRevisionSchemaWriterFailingToSave();

		$this->setExpectedException(
			RuntimeException::class,
			'The revision could not be saved'
		);
		$writer->overwriteWholeSchema(
			new SchemaId( 'O1' ),
			[],
			[],
			[],
			'lalalala',
			1
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

	public function testUpdateSchemaText_throwsForInvalidParams() {
		$pageUpdaterFactory = $this->getPageUpdaterFactory();
		$idGenerator = $this->createMock( IdGenerator::class );
		$writer = new MediaWikiRevisionSchemaWriter(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater(),
			$idGenerator
		);

		$this->expectException( InvalidArgumentException::class );
		$writer->updateSchemaText(
			new SchemaId( 'O1' ),
			null,
			1
		);
	}

	public function testUpdateSchemaText_throwsForUnknownSerializationVersion() {
		$revisionRecord = $this->createMockRevisionRecord(
			new WikibaseSchemaContent( json_encode( [
				'serializationVersion' => '4.0',
				'schema' => [
					'replacing this' => 'with the new text',
					'would be a' => 'grave mistake',
				],
				'schemaText' => [
					'same goes' => 'for this',
				],
			] ) )
		);
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'grabParentRevision' )->willReturn( $revisionRecord );
		$pageUpdaterFactory = $this->getPageUpdaterFactory( $pageUpdater );
		$idGenerator = $this->createMock( IdGenerator::class );
		$writer = new MediaWikiRevisionSchemaWriter(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater(),
			$idGenerator
		);

		$this->expectException( DomainException::class );
		$writer->updateSchemaText( new SchemaId( 'O1' ), '', 1 );
	}

	public function testUpdateSchemaText_throwsForEditConflict() {
		$revisionRecord = $this->createMockRevisionRecord( null, 2 );
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'grabParentRevision' )->willReturn( $revisionRecord );
		$pageUpdaterFactory = $this->getPageUpdaterFactory( $pageUpdater );
		$idGenerator = $this->createMock( IdGenerator::class );
		$writer = new MediaWikiRevisionSchemaWriter(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater(),
			$idGenerator
		);

		$this->expectException( EditConflict::class );
		$writer->updateSchemaText( new SchemaId( 'O1' ), '', 1 );
	}

	public function testUpdateSchemaText_WritesExpectedContentForOverwritingSchemaText() {
		$id = 'O1';
		$language = 'en';
		$labels = [ $language => 'englishLabel' ];
		$descriptions = [ $language => 'englishDescription' ];
		$aliases = [ $language => [ 'englishAlias' ] ];
		$existingContent = new WikibaseSchemaContent( json_encode( [
			'id' => $id,
			'serializationVersion' => '3.0',
			'labels' => $labels,
			'descriptions' => $descriptions,
			'aliases' => $aliases,
			'schemaText' => '# some schema about goats',
			'type' => 'ShExC',
		] ) );
		$newSchemaText = '# some schema about cats';
		$expectedContent = new WikibaseSchemaContent( json_encode( [
			'id' => $id,
			'serializationVersion' => '3.0',
			'labels' => $labels,
			'descriptions' => $descriptions,
			'aliases' => $aliases,
			'schemaText' => $newSchemaText,
			'type' => 'ShExC',
		] ) );

		$pageUpdaterFactory = $this
			->getPageUpdaterFactoryProvidingAndExpectingContent( $expectedContent, $existingContent );
		$writer = new MediaWikiRevisionSchemaWriter(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' )
		);

		$writer->updateSchemaText(
			new SchemaId( $id ),
			$newSchemaText,
			1
		);
	}

	public function testUpdateSchemaText_saveFails() {
		$writer = $this->newMediaWikiRevisionSchemaWriterFailingToSave();

		$this->setExpectedException(
			RuntimeException::class,
			'The revision could not be saved'
		);
		$writer->updateSchemaText(
			new SchemaId( 'O1' ),
			'qwerty',
			1
		);
	}

	public function testUpdateSchemaText_comment() {
		$expectedComment = CommentStoreComment::newUnsavedComment(
			'/* ' . MediaWikiRevisionSchemaWriter::AUTOCOMMENT_UPDATED_SCHEMATEXT . ' */ user given',
			[
				'key' => 'wikibaseschema-summary-update-schema-text',
				'schemaText_truncated' => 'new schema text',
				'userSummary' => 'user given',
			]
		);

		$id = new SchemaId( 'O1' );
		$existingContent = new WikibaseSchemaContent( json_encode( [
			'id' => $id,
			'serializationVersion' => '3.0',
			'labels' => [],
			'descriptions' => [],
			'aliases' => [],
			'schemaText' => '# some schema about goats',
			'type' => 'ShExC',
		] ) );

		$pageUpdaterFactory = $this->getPageUpdaterFactoryExpectingComment(
			$expectedComment,
			$existingContent
		);
		$writer = new MediaWikiRevisionSchemaWriter(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' )
		);

		$writer->updateSchemaText(
			$id,
			'new schema text',
			null,
			'user given'
		);
	}

	public function testUpdateSchemaNameBadgeSuccess() {
		$id = 'O1';
		$language = 'en';
		$labels = [ $language => 'englishLabel' ];
		$descriptions = [ $language => 'englishDescription' ];
		$aliases = [ $language => [ 'englishAlias' ] ];
		$existingContent = new WikibaseSchemaContent( json_encode( [
			'id' => $id,
			'serializationVersion' => '3.0',
			'labels' => [ 'en' => 'Cat' ],
			'descriptions' => [ 'en' => 'This is what a cat look like' ],
			'aliases' => [ 'en' => [ 'Tiger', 'Lion' ] ],
			'schemaText' => '# some schema about goats',
			'type' => 'ShExC',
		] ) );
		$expectedContent = new WikibaseSchemaContent( json_encode( [
			'id' => $id,
			'serializationVersion' => '3.0',
			'labels' => $labels,
			'descriptions' => $descriptions,
			'aliases' => $aliases,
			'schemaText' => '# some schema about goats',
			'type' => 'ShExC',
		] ) );

		$pageUpdaterFactory = $this
			->getPageUpdaterFactoryProvidingAndExpectingContent( $expectedContent, $existingContent );
		$writer = new MediaWikiRevisionSchemaWriter(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' )
		);

		$writer->updateSchemaNameBadge(
			new SchemaId( $id ),
			$language,
			$labels['en'],
			$descriptions['en'],
			$aliases['en'],
			1
		);
	}

	public function testUpdateMultiLingualSchemaNameBadgeSuccess() {
		$id = 'O1';
		$language = 'en';
		$englishLabel = 'Goat';
		$englishDescription = 'This is what a goat looks like';
		$englishAliases = [ 'Capra' ];
		$existingContent = new WikibaseSchemaContent( json_encode( [
			'id' => $id,
			'serializationVersion' => '3.0',
			'labels' => [
				'en' => 'Cat',
				'de' => 'Ziege',
			],
			'descriptions' => [
				'en' => 'This is what a cat looks like',
				'de' => 'Wichtigste Eigenschaften einer Ziege'
			],
			'aliases' => [
				'en' => [ 'Tiger', 'Lion' ],
				'de' => [ 'Capra', 'Hausziege' ]
			],
			'schemaText' => '# some schema about goats',
			'type' => 'ShExC',
		] ) );
		$expectedContent = new WikibaseSchemaContent( json_encode( [
			'id' => $id,
			'serializationVersion' => '3.0',
			'labels' => [
				'en' => $englishLabel,
				'de' => 'Ziege',
			],
			'descriptions' => [
				'en' => $englishDescription,
				'de' => 'Wichtigste Eigenschaften einer Ziege'
			],
			'aliases' => [
				'en' => $englishAliases,
				'de' => [ 'Capra', 'Hausziege' ]
			],
			'schemaText' => '# some schema about goats',
			'type' => 'ShExC',
		] ) );

		$pageUpdaterFactory = $this
			->getPageUpdaterFactoryProvidingAndExpectingContent( $expectedContent, $existingContent );
		$writer = new MediaWikiRevisionSchemaWriter(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' )
		);

		$writer->updateSchemaNameBadge(
			new SchemaId( $id ),
			$language,
			$englishLabel,
			$englishDescription,
			$englishAliases,
			1
		);
	}

	public function testUpdateSchemaNameBadge_throwsForEditConflict() {
		$revisionRecord = $this->createMockRevisionRecord( null, 2 );
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'grabParentRevision' )->willReturn( $revisionRecord );
		$pageUpdaterFactory = $this->getPageUpdaterFactory( $pageUpdater );
		$idGenerator = $this->createMock( IdGenerator::class );
		$writer = new MediaWikiRevisionSchemaWriter(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater(),
			$idGenerator
		);

		$this->expectException( EditConflict::class );
		$writer->updateSchemaNameBadge(
			new SchemaId( 'O1' ),
			'en',
			'test label',
			'test description',
			[ 'test alias' ],
			1
		);
	}

	public function testUpdateSchemaNameBadge_saveFails() {
		$writer = $this->newMediaWikiRevisionSchemaWriterFailingToSave();

		$this->setExpectedException(
			RuntimeException::class,
			'The revision could not be saved'
		);
		$writer->updateSchemaNameBadge(
			new SchemaId( 'O1' ),
			'en',
			'test label',
			'test description',
			[ 'test alias' ],
			1
		);
	}

	private function createMockRevisionRecord(
		WikibaseSchemaContent $content = null,
		$id = 1
	): RevisionRecord {
		$revisionRecord = $this->createMock( RevisionRecord::class );
		$revisionRecord->method( 'getContent' )->willReturn( $content );
		$revisionRecord->method( 'getId' )->willReturn( $id );
		return $revisionRecord;
	}

}
