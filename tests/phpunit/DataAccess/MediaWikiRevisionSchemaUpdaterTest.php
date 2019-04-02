<?php

namespace Wikibase\Schema\Tests\DataAccess;

use CommentStoreComment;
use DomainException;
use InvalidArgumentException;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Storage\PageUpdater;
use MediaWiki\Storage\RevisionRecord;
use Message;
use MessageLocalizer;
use RuntimeException;
use stdClass;
use Wikibase\Schema\DataAccess\EditConflict;
use Wikibase\Schema\DataAccess\EditConflictDetector;
use Wikibase\Schema\DataAccess\MediaWikiPageUpdaterFactory;
use Wikibase\Schema\DataAccess\MediaWikiRevisionSchemaUpdater;
use Wikibase\Schema\DataAccess\WatchlistUpdater;
use Wikibase\Schema\Domain\Model\SchemaId;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use Wikibase\Schema\Services\SchemaConverter\NameBadge;

/**
 * @covers \Wikibase\Schema\DataAccess\MediaWikiRevisionSchemaUpdater
 * @license GPL-2.0-or-later
 */
class MediaWikiRevisionSchemaUpdaterTest extends \PHPUnit_Framework_TestCase {
	use \PHPUnit4And6Compat;

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
			$revisionRecord = $this->createMockRevisionRecord( $existingContent );
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

	private function newMediaWikiRevisionSchemaUpdaterFailingToSave(): MediaWikiRevisionSchemaUpdater {
		$existingContent = new WikibaseSchemaContent( '{}' );
		$revisionRecord = $this->createMockRevisionRecord( $existingContent );

		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'wasSuccessful' )->willReturn( false );
		$pageUpdater->method( 'grabParentRevision' )->willReturn( $revisionRecord );

		return new MediaWikiRevisionSchemaUpdater(
			$this->getPageUpdaterFactory( $pageUpdater ),
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater(),
			new EditConflictDetector( MediaWikiServices::getInstance()->getRevisionStore() )
		);
	}

	public function testOverwriteWholeSchema_throwsForNonExistantPage() {
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdaterFactory = $this->getPageUpdaterFactory( $pageUpdater );
		$schmeaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater()
		);

		$this->expectException( RuntimeException::class );
		$schmeaUpdater->overwriteWholeSchema(
			new SchemaId( 'O123456999999999' ),
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

		$schmeaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater()
		);
		$this->setExpectedException( InvalidArgumentException::class, $exceptionMessage );
		$schmeaUpdater->overwriteWholeSchema(
			new SchemaId( 'O1' ),
			[ $testLanguage => $testLabel ],
			[ $testLanguage => $testDescription ],
			[ $testLanguage => $testAliases ],
			$testSchemaText,
			1,
			CommentStoreComment::newUnsavedComment( '' )
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
		$schmeaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' )
		);
		$schmeaUpdater->overwriteWholeSchema(
			new SchemaId( 'O1' ),
			[ 'en' => 'englishLabel' ],
			[ 'en' => 'englishDescription' ],
			[ 'en' => $aliases ],
			$schemaText,
			1,
			CommentStoreComment::newUnsavedComment( '' )
		);
	}

	public function testOverwriteWholeSchema_saveFails() {
		$schmeaUpdater = $this->newMediaWikiRevisionSchemaUpdaterFailingToSave();

		$this->setExpectedException(
			RuntimeException::class,
			'The revision could not be saved'
		);
		$schmeaUpdater->overwriteWholeSchema(
			new SchemaId( 'O1' ),
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
		$pageUpdaterFactory = $this->getPageUpdaterFactory();
		$schmeaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater()
		);

		$this->expectException( InvalidArgumentException::class );
		$schmeaUpdater->updateSchemaText(
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
		$schmeaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater(),
			new EditConflictDetector( MediaWikiServices::getInstance()->getRevisionStore() )
		);

		$this->expectException( DomainException::class );
		$schmeaUpdater->updateSchemaText( new SchemaId( 'O1' ), '', 1 );
	}

	public function testUpdateSchemaText_throwsForEditConflict() {
		$parentRevisionRecord = $this->createMockRevisionRecord( new WikibaseSchemaContent(
			'{
		"serializationVersion": "3.0",
		"schemaText": "conflicting text"
		}' ), 2 );
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'grabParentRevision' )->willReturn( $parentRevisionRecord );
		$pageUpdaterFactory = $this->getPageUpdaterFactory( $pageUpdater );
		$baseRevisionRecord = $this->createMockRevisionRecord( new WikibaseSchemaContent(
			'{
		"serializationVersion": "3.0",
		"schemaText": "original text"
		}' ) );
		$mockRevStore = $this->getMockBuilder( RevisionStore::class )
			->disableOriginalConstructor()
			->getMock();
		$mockRevStore->method( 'getRevisionById' )->willReturn(
			$baseRevisionRecord
		);
		$schmeaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater(),
			new EditConflictDetector( $mockRevStore )
		);

		$this->expectException( EditConflict::class );
		$schmeaUpdater->updateSchemaText( new SchemaId( 'O1' ), '', 1 );
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
		$schmeaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			new EditConflictDetector( MediaWikiServices::getInstance()->getRevisionStore() )
		);

		$schmeaUpdater->updateSchemaText(
			new SchemaId( $id ),
			$newSchemaText,
			1
		);
	}

	public function testUpdateSchemaText_saveFails() {
		$schmeaUpdater = $this->newMediaWikiRevisionSchemaUpdaterFailingToSave();

		$this->setExpectedException(
			RuntimeException::class,
			'The revision could not be saved'
		);
		$schmeaUpdater->updateSchemaText(
			new SchemaId( 'O1' ),
			'qwerty',
			1
		);
	}

	public function testUpdateSchemaText_comment() {
		$expectedComment = CommentStoreComment::newUnsavedComment(
			'/* ' . MediaWikiRevisionSchemaUpdater::AUTOCOMMENT_UPDATED_SCHEMATEXT . ' */user given',
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
		$schmeaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			new EditConflictDetector( MediaWikiServices::getInstance()->getRevisionStore() )
		);

		$schmeaUpdater->updateSchemaText(
			$id,
			'new schema text',
			1,
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
		$schmeaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			new EditConflictDetector( MediaWikiServices::getInstance()->getRevisionStore() )
		);

		$schmeaUpdater->updateSchemaNameBadge(
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
		$schmeaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			new EditConflictDetector( MediaWikiServices::getInstance()->getRevisionStore() )
		);

		$schmeaUpdater->updateSchemaNameBadge(
			new SchemaId( $id ),
			$language,
			$englishLabel,
			$englishDescription,
			$englishAliases,
			1
		);
	}

	/**
	 * @dataProvider provideNameBadgesWithComments
	 */
	public function testUpdateSchemaNameBadge_comment(
		NameBadge $old = null, // FIXME PHP7.1 nullable typehint
		NameBadge $new,
		$expectedAutocommentKey,
		$expectedAutosummary
	) {
		$id = 'O1';
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

		$oldContent = new WikibaseSchemaContent( json_encode( $oldArray ) );
		$pageUpdaterFactory = $this->getPageUpdaterFactoryExpectingComment(
			$expectedComment,
			$oldContent
		);
		$writer = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			new EditConflictDetector( MediaWikiServices::getInstance()->getRevisionStore() )
		);

		$writer->updateSchemaNameBadge(
			new SchemaId( $id ),
			$language,
			$new->label,
			$new->description,
			$new->aliases,
			1
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
		$parentRevisionRecord = $this->createMockRevisionRecord( new WikibaseSchemaContent(
			json_encode(
				[
					'serializationVersion' => '3.0',
					'labels' => [ 'en' => 'conflicting label' ],
				]
			) ), 2 );
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'grabParentRevision' )->willReturn( $parentRevisionRecord );
		$pageUpdaterFactory = $this->getPageUpdaterFactory( $pageUpdater );

		$baseRevisionRecord = $this->createMockRevisionRecord( new WikibaseSchemaContent(
			json_encode(
				[
					'serializationVersion' => '3.0',
					'labels' => [ 'en' => 'original label' ],
				]
			) ) );
		$mockRevStore = $this->getMockBuilder( RevisionStore::class )
			->disableOriginalConstructor()
			->getMock();
		$mockRevStore->method( 'getRevisionById' )->willReturn(
			$baseRevisionRecord
		);

		$schmeaUpdater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater(),
			new EditConflictDetector( $mockRevStore )
		);

		$this->expectException( EditConflict::class );
		$schmeaUpdater->updateSchemaNameBadge(
			new SchemaId( 'O1' ),
			'en',
			'test label',
			'test description',
			[ 'test alias' ],
			1
		);
	}

	public function testUpdateSchemaNameBadgeSuccessNonConflictingEdit() {
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

		$baseRevisionRecord = $this->createMockRevisionRecord( new WikibaseSchemaContent(
			json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => [ 'en' => 'Cat', 'de' => 'Katze' ],
				'descriptions' => [ 'en' => 'This is what a cat look like' ],
				'aliases' => [ 'en' => [ 'Tiger', 'Lion' ] ],
				'schemaText' => '# some schema about goats',
				'type' => 'ShExC',
			] ) ) );
		$mockRevStore = $this->getMockBuilder( RevisionStore::class )
			->disableOriginalConstructor()
			->getMock();
		$mockRevStore->method( 'getRevisionById' )->willReturn(
			$baseRevisionRecord
		);

		$pageUpdaterFactory = $this
			->getPageUpdaterFactoryProvidingAndExpectingContent( $expectedContent, $existingContent );
		$updater = new MediaWikiRevisionSchemaUpdater(
			$pageUpdaterFactory,
			$this->getMessageLocalizer(),
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			new EditConflictDetector( $mockRevStore )
		);

		$updater->updateSchemaNameBadge(
			new SchemaId( $id ),
			$language,
			$labels['en'],
			$descriptions['en'],
			$aliases['en'],
			2
		);
	}

	public function testUpdateSchemaNameBadge_saveFails() {
		$schmeaUpdater = $this->newMediaWikiRevisionSchemaUpdaterFailingToSave();

		$this->setExpectedException(
			RuntimeException::class,
			'The revision could not be saved'
		);
		$schmeaUpdater->updateSchemaNameBadge(
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
