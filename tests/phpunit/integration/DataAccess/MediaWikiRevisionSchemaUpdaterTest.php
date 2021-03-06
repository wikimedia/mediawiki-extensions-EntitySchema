<?php

namespace EntitySchema\Tests\Integration\DataAccess;

use CommentStoreComment;
use DomainException;
use EntitySchema\DataAccess\EditConflict;
use EntitySchema\DataAccess\MediaWikiPageUpdaterFactory;
use EntitySchema\DataAccess\MediaWikiRevisionSchemaUpdater;
use EntitySchema\DataAccess\WatchlistUpdater;
use EntitySchema\Domain\Model\SchemaId;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\Services\SchemaConverter\NameBadge;
use InvalidArgumentException;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\PageUpdater;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \EntitySchema\DataAccess\MediaWikiRevisionSchemaUpdater
 * @covers \EntitySchema\DataAccess\SchemaUpdateGuard
 * @license GPL-2.0-or-later
 */
class MediaWikiRevisionSchemaUpdaterTest extends TestCase {

	/** @var RevisionRecord|null */
	private $baseRevision;

	/** @var RevisionRecord|null */
	private $parentRevision;

	protected function tearDown() : void {
		$this->baseRevision = null;
		$this->parentRevision = null;
	}

	/**
	 * @param EntitySchemaContent $expectedContent The content to expect in a setContent() call.
	 * @param EntitySchemaContent|null $existingContent Used to override $this->parentRevision
	 * if not null. grabParentRevision() is only mocked if a page revision is available.
	 *
	 * @return MediaWikiPageUpdaterFactory
	 */
	private function getPageUpdaterFactoryProvidingAndExpectingContent(
		EntitySchemaContent $expectedContent,
		EntitySchemaContent $existingContent = null
	): MediaWikiPageUpdaterFactory {
		$pageUpdater = $this->createMock( PageUpdater::class );
		if ( $existingContent !== null ) {
			$this->parentRevision = $this->createMockRevisionRecord( $existingContent );
		}
		if ( $this->parentRevision !== null ) {
			$pageUpdater->method( 'grabParentRevision' )->willReturn( $this->parentRevision );
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
		EntitySchemaContent $existingContent = null
	): MediaWikiPageUpdaterFactory {
		$pageUpdater = $this->createMock( PageUpdater::class );
		if ( $existingContent !== null ) {
			$this->parentRevision = $this->createMockRevisionRecord( $existingContent );
			$pageUpdater->method( 'grabParentRevision' )->willReturn( $this->parentRevision );
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

	private function createMockRevisionLookup( array $revisionRecords = [] ) {
		$revisionRecordMap = [];
		foreach ( $revisionRecords as $revisionRecord ) {
			$revisionRecordMap[$revisionRecord->getId()] = $revisionRecord;
		}
		$mockRevLookup = $this->getMockForAbstractClass( RevisionLookup::class );
		$mockRevLookup->method( 'getRevisionById' )
			->willReturnCallback( static function ( $id, $flags = 0 ) use ( $revisionRecordMap ) {
				return $revisionRecordMap[$id] ?? null;
			} );
		return $mockRevLookup;
	}

	private function newMediaWikiRevisionSchemaUpdaterFailingToSave(): MediaWikiRevisionSchemaUpdater {
		$existingContent = new EntitySchemaContent( '{}' );
		$this->parentRevision = $this->createMockRevisionRecord( $existingContent );

		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'wasSuccessful' )->willReturn( false );
		$pageUpdater->method( 'grabParentRevision' )->willReturn( $this->parentRevision );

		$mockRevLookup = $this->createMockRevisionLookup( [ $this->parentRevision ] );

		return new MediaWikiRevisionSchemaUpdater(
			$this->getPageUpdaterFactory( $pageUpdater ),
			$this->getMockWatchlistUpdater(),
			$mockRevLookup
		);
	}

	public function testOverwriteWholeSchema_throwsForNonExistantPage() {
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdaterFactory = $this->getPageUpdaterFactory( $pageUpdater );

		$mockRevLookup = $this->createMockRevisionLookup();

		$schmeaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater(),
			$mockRevLookup
		);

		$this->expectException( RuntimeException::class );
		$schmeaUpdater->overwriteWholeSchema(
			new SchemaId( 'E123456999999999' ),
			[],
			[],
			[],
			'',
			1,
			CommentStoreComment::newUnsavedComment( '' )
		);
	}

	public function provideBadParameters() {
		$langExceptionMsg = 'language codes must be valid!';
		$typeExceptionMsg = 'language, label, description and schemaText must be strings '
			. 'and aliases must be an array of strings';
		return [
			'language is not supported' => [ 'not a real langcode', '', '', [], '', $langExceptionMsg ],
			'label is not string' => [ 'de', (object)[], '', [], '', $typeExceptionMsg ],
			'description is not string' => [ 'en', '', (object)[], [], '', $typeExceptionMsg ],
			'aliases is non-string array' => [ 'fr', '', '', [ (object)[] ], '', $typeExceptionMsg ],
			'aliases is mixed array' => [ 'ar', '', '', [ (object)[], 'foo' ], '', $typeExceptionMsg ],
			'aliases is associative array' => [ 'hu', '', '', [ 'en' => 'foo' ], '', $typeExceptionMsg ],
			'schema text is not string' => [ 'he', '', '', [], (object)[], $typeExceptionMsg ],
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
		$this->parentRevision = $this->createMockRevisionRecord();
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'grabParentRevision' )->willReturn( $this->parentRevision );
		$pageUpdaterFactory = $this->getPageUpdaterFactory( $pageUpdater );
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->parentRevision ] );

		$schmeaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater(),
			$mockRevLookup
		);
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( $exceptionMessage );
		$schmeaUpdater->overwriteWholeSchema(
			new SchemaId( 'E1' ),
			[ $testLanguage => $testLabel ],
			[ $testLanguage => $testDescription ],
			[ $testLanguage => $testAliases ],
			$testSchemaText,
			$this->parentRevision->getId(),
			CommentStoreComment::newUnsavedComment( '' )
		);
	}

	public function testOverwriteWholeSchema_WritesExpectedContentForOverwritingMonoLingualSchema() {
		$id = 'E1';
		$language = 'en';
		$label = 'englishLabel';
		$description = 'englishDescription';
		$aliases = [ 'englishAlias' ];
		$schemaText = '#some schema about goats';
		$existingContent = new EntitySchemaContent( '' );
		$expectedContent = new EntitySchemaContent( json_encode(
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
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->parentRevision ] );
		$schmeaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			$mockRevLookup
		);
		$schmeaUpdater->overwriteWholeSchema(
			new SchemaId( 'E1' ),
			[ 'en' => 'englishLabel' ],
			[ 'en' => 'englishDescription' ],
			[ 'en' => $aliases ],
			$schemaText,
			$this->parentRevision->getId(),
			CommentStoreComment::newUnsavedComment( '' )
		);
	}

	public function testOverwriteWholeSchema_saveFails() {
		$schmeaUpdater = $this->newMediaWikiRevisionSchemaUpdaterFailingToSave();

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'The revision could not be saved' );
		$schmeaUpdater->overwriteWholeSchema(
			new SchemaId( 'E1' ),
			[],
			[],
			[],
			'lalalala',
			1,
			CommentStoreComment::newUnsavedComment( '' )
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
		$mockRevLookup = $this->createMockRevisionLookup();
		$pageUpdaterFactory = $this->getPageUpdaterFactory();
		$schmeaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater(),
			$mockRevLookup
		);

		$this->expectException( InvalidArgumentException::class );
		$schmeaUpdater->updateSchemaText(
			new SchemaId( 'E1' ),
			null,
			1
		);
	}

	public function testUpdateSchemaText_throwsForUnknownSerializationVersion() {
		$this->parentRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
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
		$pageUpdater->method( 'grabParentRevision' )->willReturn( $this->parentRevision );
		$pageUpdaterFactory = $this->getPageUpdaterFactory( $pageUpdater );
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->parentRevision ] );
		$schmeaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater(),
			$mockRevLookup
		);

		$this->expectException( DomainException::class );
		$schmeaUpdater->updateSchemaText(
			new SchemaId( 'E1' ),
			'',
			$this->parentRevision->getId()
		);
	}

	public function testUpdateSchemaText_throwsForEditConflict() {
		$this->parentRevision = $this->createMockRevisionRecord( new EntitySchemaContent(
			'{
		"serializationVersion": "3.0",
		"schemaText": "conflicting text"
		}' ), 2 );
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'grabParentRevision' )->willReturn( $this->parentRevision );
		$pageUpdaterFactory = $this->getPageUpdaterFactory( $pageUpdater );
		$this->baseRevision = $this->createMockRevisionRecord( new EntitySchemaContent(
			'{
		"serializationVersion": "3.0",
		"schemaText": "original text"
		}' ) );
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->baseRevision, $this->parentRevision ] );
		$schmeaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater(),
			$mockRevLookup
		);

		$this->expectException( EditConflict::class );
		$schmeaUpdater->updateSchemaText(
			new SchemaId( 'E1' ),
			'',
			$this->baseRevision->getId()
		);
	}

	public function testUpdateSchemaText_WritesExpectedContentForOverwritingSchemaText() {
		$id = 'E1';
		$language = 'en';
		$labels = [ $language => 'englishLabel' ];
		$descriptions = [ $language => 'englishDescription' ];
		$aliases = [ $language => [ 'englishAlias' ] ];
		$existingContent = new EntitySchemaContent( json_encode( [
			'id' => $id,
			'serializationVersion' => '3.0',
			'labels' => $labels,
			'descriptions' => $descriptions,
			'aliases' => $aliases,
			'schemaText' => '# some schema about goats',
			'type' => 'ShExC',
		] ) );
		$newSchemaText = '# some schema about cats';
		$expectedContent = new EntitySchemaContent( json_encode( [
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
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->parentRevision ] );
		$schmeaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			$mockRevLookup
		);

		$schmeaUpdater->updateSchemaText(
			new SchemaId( $id ),
			$newSchemaText,
			$this->parentRevision->getId()
		);
	}

	public function testUpdateSchemaText_mergesChangesInNameBadge() {
		$id = 'E1';
		$oldLabels = [ 'en' => 'old label' ];
		$newLabels = [ 'en' => 'new label' ];
		$descriptions = [ 'en' => 'description' ];
		$aliases = [ 'en' => [ 'alias' ] ];
		$oldSchemaText = 'old schema text';
		$newSchemaText = 'new schema text';

		$this->baseRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $oldLabels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $oldSchemaText,
				'type' => 'ShExC',
			] ) )
		);
		$this->parentRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $newLabels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $oldSchemaText,
				'type' => 'ShExC',
			] ) ),
			2
		);
		$pageUpdaterFactory = $this->getPageUpdaterFactoryProvidingAndExpectingContent(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $newLabels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $newSchemaText,
				'type' => 'ShExC',
			] ) )
		);
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->baseRevision, $this->parentRevision ] );

		$schemaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			$mockRevLookup
		);
		$schemaUpdater->updateSchemaText(
			new SchemaId( $id ),
			$newSchemaText,
			$this->baseRevision->getId()
		);
	}

	public function testUpdateSchemaText_mergesChangesInSchemaText() {
		$id = 'E1';
		$labels = [ 'en' => 'label' ];
		$descriptions = [ 'en' => 'description' ];
		$aliases = [ 'en' => [ 'alias' ] ];
		$baseSchemaText = <<< 'SHEXC'
<:foo> {
  :bar .;
}

<:abc> {
  :xyz .;
}
SHEXC;
		$parentSchemaText = <<< 'SHEXC'
<:foo> {
  :bar IRI;
}

<:abc> {
  :xyz .;
}
SHEXC;
		$userSchemaText = <<< 'SHEXC'
<:foo> {
  :bar .;
}

<:abc> {
  :xyz IRI;
}
SHEXC;
		$finalSchemaText = <<< 'SHEXC'
<:foo> {
  :bar IRI;
}

<:abc> {
  :xyz IRI;
}
SHEXC;

		$this->baseRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $labels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $baseSchemaText,
				'type' => 'ShExC',
			] ) )
		);
		$this->parentRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $labels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $parentSchemaText,
				'type' => 'ShExC',
			] ) ),
			2
		);
		$pageUpdaterFactory = $this->getPageUpdaterFactoryProvidingAndExpectingContent(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $labels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $finalSchemaText,
				'type' => 'ShExC',
			] ) )
		);
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->baseRevision, $this->parentRevision ] );

		$schemaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			$mockRevLookup
		);
		$schemaUpdater->updateSchemaText(
			new SchemaId( $id ),
			$userSchemaText,
			$this->baseRevision->getId()
		);
	}

	public function testUpdateSchemaText_saveFails() {
		$schmeaUpdater = $this->newMediaWikiRevisionSchemaUpdaterFailingToSave();

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'The revision could not be saved' );
		$schmeaUpdater->updateSchemaText(
			new SchemaId( 'E1' ),
			'qwerty',
			1
		);
	}

	public function testUpdateSchemaText_comment() {
		$expectedComment = CommentStoreComment::newUnsavedComment(
			'/* ' . MediaWikiRevisionSchemaUpdater::AUTOCOMMENT_UPDATED_SCHEMATEXT . ' */user given',
			[
				'key' => 'entityschema-summary-update-schema-text',
				'schemaText_truncated' => 'new schema text',
				'userSummary' => 'user given',
			]
		);

		$id = new SchemaId( 'E1' );
		$existingContent = new EntitySchemaContent( json_encode( [
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
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->parentRevision ] );
		$schmeaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			$mockRevLookup
		);

		$schmeaUpdater->updateSchemaText(
			$id,
			'new schema text',
			$this->parentRevision->getId(),
			'user given'
		);
	}

	public function testUpdateSchemaText_onlySerializationVersionChanges() {
		$this->parentRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'serializationVersion' => '2.0',
				'schema' => 'schema text',
			] ) )
		);
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'grabParentRevision' )
			->willReturn( $this->parentRevision );
		$pageUpdater->expects( $this->never() )->method( 'setContent' );
		$pageUpdater->expects( $this->never() )->method( 'saveRevision' );
		$pageUpdaterFactory = $this->getPageUpdaterFactory( $pageUpdater );
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->parentRevision ] );
		$schemaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater(),
			$mockRevLookup
		);

		$schemaUpdater->updateSchemaText(
			new SchemaId( 'E1' ),
			'schema text',
			$this->parentRevision->getId()
		);
	}

	public function testUpdateSchemaNameBadgeSuccess() {
		$id = 'E1';
		$language = 'en';
		$labels = [ $language => 'englishLabel' ];
		$descriptions = [ $language => 'englishDescription' ];
		$aliases = [ $language => [ 'englishAlias' ] ];
		$existingContent = new EntitySchemaContent( json_encode( [
			'id' => $id,
			'serializationVersion' => '3.0',
			'labels' => [ 'en' => 'Cat' ],
			'descriptions' => [ 'en' => 'This is what a cat look like' ],
			'aliases' => [ 'en' => [ 'Tiger', 'Lion' ] ],
			'schemaText' => '# some schema about goats',
			'type' => 'ShExC',
		] ) );
		$expectedContent = new EntitySchemaContent( json_encode( [
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
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->parentRevision ] );
		$schmeaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			$mockRevLookup
		);

		$schmeaUpdater->updateSchemaNameBadge(
			new SchemaId( $id ),
			$language,
			$labels['en'],
			$descriptions['en'],
			$aliases['en'],
			$this->parentRevision->getId()
		);
	}

	public function testUpdateMultiLingualSchemaNameBadgeSuccess() {
		$id = 'E1';
		$language = 'en';
		$englishLabel = 'Goat';
		$englishDescription = 'This is what a goat looks like';
		$englishAliases = [ 'Capra' ];
		$existingContent = new EntitySchemaContent( json_encode( [
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
		$expectedContent = new EntitySchemaContent( json_encode( [
			'id' => $id,
			'serializationVersion' => '3.0',
			'labels' => [
				'de' => 'Ziege',
				'en' => $englishLabel,
			],
			'descriptions' => [
				'de' => 'Wichtigste Eigenschaften einer Ziege',
				'en' => $englishDescription,
			],
			'aliases' => [
				'de' => [ 'Capra', 'Hausziege' ],
				'en' => $englishAliases,
			],
			'schemaText' => '# some schema about goats',
			'type' => 'ShExC',
		] ) );

		$pageUpdaterFactory = $this
			->getPageUpdaterFactoryProvidingAndExpectingContent( $expectedContent, $existingContent );
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->parentRevision ] );
		$schmeaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			$mockRevLookup
		);

		$schmeaUpdater->updateSchemaNameBadge(
			new SchemaId( $id ),
			$language,
			$englishLabel,
			$englishDescription,
			$englishAliases,
			$this->parentRevision->getId()
		);
	}

	/**
	 * @dataProvider provideNameBadgesWithComments
	 */
	public function testUpdateSchemaNameBadge_comment(
		?NameBadge $old,
		NameBadge $new,
		$expectedAutocommentKey,
		$expectedAutosummary
	) {
		$id = 'E1';
		$language = 'en';
		$oldArray = [
			'id' => $id,
			'serializationVersion' => '3.0',
			'labels' => [],
			'descriptions' => [],
			'aliases' => [],
			'schemaText' => 'schema text',
			'type' => 'ShExC',
		];

		if ( $old !== null ) {
			$oldArray['labels'][$language] = $old->label;
			$oldArray['descriptions'][$language] = $old->description;
			$oldArray['aliases'][$language] = $old->aliases;
		}

		$autocomment = $expectedAutocommentKey . ':' . $language;
		$expectedComment = CommentStoreComment::newUnsavedComment(
			'/* ' . $autocomment . ' */' . $expectedAutosummary,
			[
				'key' => $expectedAutocommentKey,
				'language' => $language,
				'label' => $new->label,
				'description' => $new->description,
				'aliases' => $new->aliases,
			]
		);

		$oldContent = new EntitySchemaContent( json_encode( $oldArray ) );
		$pageUpdaterFactory = $this->getPageUpdaterFactoryExpectingComment(
			$expectedComment,
			$oldContent
		);
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->parentRevision ] );
		$writer = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			$mockRevLookup
		);

		$writer->updateSchemaNameBadge(
			new SchemaId( $id ),
			$language,
			$new->label,
			$new->description,
			$new->aliases,
			$this->parentRevision->getId()
		);
	}

	public function provideNameBadgesWithComments() {
		$oldBadge = new NameBadge( 'old label', 'old description', [ 'old alias' ] );

		yield 'everything changed' => [
			$oldBadge,
			new NameBadge( 'new label', 'new description', [ 'new alias' ] ),
			MediaWikiRevisionSchemaUpdater::AUTOCOMMENT_UPDATED_NAMEBADGE,
			'',
		];

		yield 'label changed' => [
			$oldBadge,
			new NameBadge( 'new label', $oldBadge->description, $oldBadge->aliases ),
			MediaWikiRevisionSchemaUpdater::AUTOCOMMENT_UPDATED_LABEL,
			'new label',
		];

		yield 'description changed' => [
			$oldBadge,
			new NameBadge( $oldBadge->label, 'new description', $oldBadge->aliases ),
			MediaWikiRevisionSchemaUpdater::AUTOCOMMENT_UPDATED_DESCRIPTION,
			'new description',
		];

		yield 'aliases changed' => [
			$oldBadge,
			new NameBadge( $oldBadge->label, $oldBadge->description, [ 'new alias', 'other' ] ),
			MediaWikiRevisionSchemaUpdater::AUTOCOMMENT_UPDATED_ALIASES,
			'new alias, other',
		];

		yield 'label removed' => [
			$oldBadge,
			new NameBadge( '', $oldBadge->description, $oldBadge->aliases ),
			MediaWikiRevisionSchemaUpdater::AUTOCOMMENT_UPDATED_LABEL,
			'',
		];

		yield 'description removed' => [
			$oldBadge,
			new NameBadge( $oldBadge->label, '', $oldBadge->aliases ),
			MediaWikiRevisionSchemaUpdater::AUTOCOMMENT_UPDATED_DESCRIPTION,
			'',
		];

		yield 'aliases removed' => [
			$oldBadge,
			new NameBadge( $oldBadge->label, $oldBadge->description, [] ),
			MediaWikiRevisionSchemaUpdater::AUTOCOMMENT_UPDATED_ALIASES,
			'',
		];

		yield 'label added in new language' => [
			null,
			new NameBadge( 'new label', '', [] ),
			MediaWikiRevisionSchemaUpdater::AUTOCOMMENT_UPDATED_LABEL,
			'new label',
		];

		yield 'description added in new language' => [
			null,
			new NameBadge( '', 'new description', [] ),
			MediaWikiRevisionSchemaUpdater::AUTOCOMMENT_UPDATED_DESCRIPTION,
			'new description',
		];

		yield 'aliases added in new language' => [
			null,
			new NameBadge( '', '', [ 'new alias' ] ),
			MediaWikiRevisionSchemaUpdater::AUTOCOMMENT_UPDATED_ALIASES,
			'new alias',
		];

		yield 'label changed, alias removed' => [
			$oldBadge,
			new NameBadge( 'new label', $oldBadge->description, [] ),
			MediaWikiRevisionSchemaUpdater::AUTOCOMMENT_UPDATED_NAMEBADGE,
			''
		];
	}

	public function testUpdateSchemaNameBadge_throwsForEditConflict() {
		$this->parentRevision = $this->createMockRevisionRecord( new EntitySchemaContent(
			json_encode(
				[
					'serializationVersion' => '3.0',
					'labels' => [ 'en' => 'conflicting label' ],
				]
			)
		), 2 );
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'grabParentRevision' )->willReturn( $this->parentRevision );
		$pageUpdaterFactory = $this->getPageUpdaterFactory( $pageUpdater );

		$this->baseRevision = $this->createMockRevisionRecord( new EntitySchemaContent(
			json_encode(
				[
					'serializationVersion' => '3.0',
					'labels' => [ 'en' => 'original label' ],
				]
			)
		) );
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->baseRevision, $this->parentRevision ] );

		$schmeaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater(),
			$mockRevLookup
		);

		$this->expectException( EditConflict::class );
		$schmeaUpdater->updateSchemaNameBadge(
			new SchemaId( 'E1' ),
			'en',
			'test label',
			'test description',
			[ 'test alias' ],
			$this->baseRevision->getId()
		);
	}

	public function testUpdateSchemaNameBadgeSuccessNonConflictingEdit() {
		$id = 'E1';
		$language = 'en';
		$labels = [ $language => 'englishLabel' ];
		$descriptions = [ $language => 'englishDescription' ];
		$aliases = [ $language => [ 'englishAlias' ] ];
		$existingContent = new EntitySchemaContent( json_encode( [
			'id' => $id,
			'serializationVersion' => '3.0',
			'labels' => [ 'en' => 'Cat' ],
			'descriptions' => [ 'en' => 'This is what a cat look like' ],
			'aliases' => [ 'en' => [ 'Tiger', 'Lion' ] ],
			'schemaText' => '# some schema about goats',
			'type' => 'ShExC',
		] ) );
		$expectedContent = new EntitySchemaContent( json_encode( [
			'id' => $id,
			'serializationVersion' => '3.0',
			'labels' => $labels,
			'descriptions' => $descriptions,
			'aliases' => $aliases,
			'schemaText' => '# some schema about goats',
			'type' => 'ShExC',
		] ) );

		$this->baseRevision = $this->createMockRevisionRecord( new EntitySchemaContent(
			json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => [ 'en' => 'Cat', 'de' => 'Katze' ],
				'descriptions' => [ 'en' => 'This is what a cat look like' ],
				'aliases' => [ 'en' => [ 'Tiger', 'Lion' ] ],
				'schemaText' => '# some schema about goats',
				'type' => 'ShExC',
			] )
		) );

		$pageUpdaterFactory = $this
			->getPageUpdaterFactoryProvidingAndExpectingContent( $expectedContent, $existingContent );
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->baseRevision, $this->parentRevision ] );
		$updater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			$mockRevLookup
		);

		$updater->updateSchemaNameBadge(
			new SchemaId( $id ),
			$language,
			$labels['en'],
			$descriptions['en'],
			$aliases['en'],
			$this->baseRevision->getId()
		);
	}

	public function testUpdateNameBadge_mergesChangesInSchemaText() {
		$id = 'E1';
		$oldLabels = [ 'en' => 'old label' ];
		$newLabels = [ 'en' => 'new label' ];
		$descriptions = [ 'en' => 'description' ];
		$aliases = [ 'en' => [ 'alias' ] ];
		$oldSchemaText = 'old schema text';
		$newSchemaText = 'new schema text';

		$this->baseRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $oldLabels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $oldSchemaText,
				'type' => 'ShExC',
			] ) )
		);
		$this->parentRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $oldLabels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $newSchemaText,
				'type' => 'ShExC',
			] ) ),
			2
		);
		$pageUpdaterFactory = $this->getPageUpdaterFactoryProvidingAndExpectingContent(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $newLabels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $newSchemaText,
				'type' => 'ShExC',
			] ) )
		);
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->baseRevision, $this->parentRevision ] );

		$schemaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			$mockRevLookup
		);
		$schemaUpdater->updateSchemaNameBadge(
			new SchemaId( $id ),
			'en',
			$newLabels['en'],
			$descriptions['en'],
			$aliases['en'],
			$this->baseRevision->getId()
		);
	}

	public function testUpdateNameBadge_mergesChangesInOtherLanguage() {
		$id = 'E1';
		$baseLabels = [ 'de' => 'alte Bezeichnung', 'en' => 'old label' ];
		$parentLabels = [ 'de' => 'neue Bezeichnung', 'en' => 'old label' ];
		$userLabels = [ 'de' => 'alte Bezeichnung', 'en' => 'new label' ];
		$finalLabels = [ 'de' => 'neue Bezeichnung', 'en' => 'new label' ];
		$descriptions = [ 'en' => 'description' ];
		$aliases = [ 'en' => [ 'alias' ] ];
		$schemaText = 'schema text';

		$this->baseRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $baseLabels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $schemaText,
				'type' => 'ShExC',
			] ) )
		);
		$this->parentRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $parentLabels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $schemaText,
				'type' => 'ShExC',
			] ) ),
			2
		);
		$pageUpdaterFactory = $this->getPageUpdaterFactoryProvidingAndExpectingContent(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $finalLabels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $schemaText,
				'type' => 'ShExC',
			] ) )
		);
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->baseRevision, $this->parentRevision ] );

		$schemaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			$mockRevLookup
		);
		$schemaUpdater->updateSchemaNameBadge(
			new SchemaId( $id ),
			'en',
			$userLabels['en'],
			$descriptions['en'],
			$aliases['en'],
			$this->baseRevision->getId()
		);
	}

	public function testUpdateNameBadge_mergesChangesInSameLanguage() {
		$id = 'E1';
		$oldLabels = [ 'en' => 'old label' ];
		$newLabels = [ 'en' => 'new label' ];
		$oldDescriptions = [ 'en' => 'old description' ];
		$newDescriptions = [ 'en' => 'new description' ];
		$aliases = [ 'en' => [ 'alias' ] ];
		$schemaText = 'schema text';

		$this->baseRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $oldLabels,
				'descriptions' => $oldDescriptions,
				'aliases' => $aliases,
				'schemaText' => $schemaText,
				'type' => 'ShExC',
			] ) )
		);
		$this->parentRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $oldLabels,
				'descriptions' => $newDescriptions,
				'aliases' => $aliases,
				'schemaText' => $schemaText,
				'type' => 'ShExC',
			] ) ),
			2
		);
		$pageUpdaterFactory = $this->getPageUpdaterFactoryProvidingAndExpectingContent(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $newLabels,
				'descriptions' => $newDescriptions,
				'aliases' => $aliases,
				'schemaText' => $schemaText,
				'type' => 'ShExC',
			] ) )
		);
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->baseRevision,$this->parentRevision ] );

		$schemaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			$mockRevLookup
		);
		$schemaUpdater->updateSchemaNameBadge(
			new SchemaId( $id ),
			'en',
			$newLabels['en'],
			$oldDescriptions['en'],
			$aliases['en'],
			$this->baseRevision->getId()
		);
	}

	public function testUpdateSchemaNameBadge_saveFails() {
		$schmeaUpdater = $this->newMediaWikiRevisionSchemaUpdaterFailingToSave();

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'The revision could not be saved' );
		$schmeaUpdater->updateSchemaNameBadge(
			new SchemaId( 'E1' ),
			'en',
			'test label',
			'test description',
			[ 'test alias' ],
			1
		);
	}

	public function testUpdateSchemaNameBadge_onlySerializationVersionChanges() {
		$this->parentRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'serializationVersion' => '2.0',
				'labels' => [ 'en' => 'label' ],
			] ) )
		);
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'grabParentRevision' )
			->willReturn( $this->parentRevision );
		$pageUpdater->expects( $this->never() )->method( 'setContent' );
		$pageUpdater->expects( $this->never() )->method( 'saveRevision' );
		$pageUpdaterFactory = $this->getPageUpdaterFactory( $pageUpdater );
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->parentRevision ] );
		$schemaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater(),
			$mockRevLookup
		);

		$schemaUpdater->updateSchemaNameBadge(
			new SchemaId( 'E1' ),
			'en',
			'label',
			'',
			[],
			$this->parentRevision->getId()
		);
	}

	private function createMockRevisionRecord(
		EntitySchemaContent $content = null,
		$id = 1
	): RevisionRecord {
		$revisionRecord = $this->createMock( RevisionRecord::class );
		$revisionRecord->method( 'getContent' )->willReturn( $content );
		$revisionRecord->method( 'getId' )->willReturn( $id );
		return $revisionRecord;
	}

}
