<?php

namespace Wikibase\Schema\Tests\DataAccess;

use DomainException;
use InvalidArgumentException;
use MediaWiki\Storage\RevisionRecord;
use Message;
use MessageLocalizer;
use \RuntimeException;
use MediaWiki\Storage\PageUpdater;
use stdClass;
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
					'serializationVersion' => '2.0',
					'labels' => [
						$language => $label
					],
					'descriptions' => [
						$language => $description
					],
					'aliases' => [
						$language => $aliases
					],
					'schema' => $schemaText,
					'type' => 'ShExC',
				]
			)
		);

		$pageUpdaterFactory = $this
			->getPageUpdaterFactoryProvidingAndExpectingContent( $expectedContent );

		$idGenerator = $this->createMock( IdGenerator::class );
		$idGenerator->method( 'getNewId' )->willReturn( '123' );

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

	private function getPageUpdaterFactoryProvidingAndExpectingContent(
		WikibaseSchemaContent $expectedContent,
		WikibaseSchemaContent $existingContent = null
	): MediaWikiPageUpdaterFactory {
		$pageUpdater = $this->createMock( PageUpdater::class );
		if ( $existingContent !== null ) {
			$revisionRecord = $this->createMock( RevisionRecord::class );
			$revisionRecord->method( 'getContent' )->willReturn( $existingContent );
			$pageUpdater->method( 'grabParentRevision' )->willReturn( $revisionRecord );
			$pageUpdater->method( 'wasSuccessful' )->willReturn( true );
		}
		$pageUpdater->expects( $this->once() )
			->method( 'setContent' )
			->with(
				'main',
				$this->equalTo( $expectedContent )
			);

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

	public function testUpdateSchema_throwsForNonExistantPage() {
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
			''
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
			'schema content is not string' => [ 'he', '', '', [], new StdClass(), $typeExceptionMsg ],
			'aliases must be unique' => [ 'hi', '', '', [ 'foo', 'foo' ], '', 'aliases must be unique' ],
		];
	}

	/**
	 * @dataProvider provideBadParameters
	 */
	public function testUpdateSchema_throwsForInvalidParams(
		$testLanguage,
		$testLabel,
		$testDescription,
		$testAliases,
		$testSchemaText,
		$exceptionMessage
	) {
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'grabParentRevision' )->willReturn(
			$this->createMock( RevisionRecord::class )
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
			$testSchemaText
		);
	}

	public function testUpdateSchema_WritesExpectedContentForOverwritingMonoLingualSchema() {
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
				'serializationVersion' => '2.0',
				'labels' => [
					$language => $label
				],
				'descriptions' => [
					$language => $description
				],
				'aliases' => [
					$language => $aliases
				],
				'schema' => $schemaText,
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
			$schemaText
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
			null
		);
	}

	public function testUpdateSchemaText_throwsForUnknownSerializationVersion() {
		$revisionRecord = $this->createMock( RevisionRecord::class );
		$revisionRecord->method( 'getContent' )->willReturn(
			new WikibaseSchemaContent( json_encode( [
				'serializationVersion' => '3.0',
				'schema' => [
					'replacing this' => 'with the new text',
					'would be a' => 'grave mistake',
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
		$writer->updateSchemaText( new SchemaId( 'O1' ), '' );
	}

	public function testUpdateSchema_WritesExpectedContentForOverwritingSchemaText() {
		$id = 'O1';
		$language = 'en';
		$labels = [ $language => 'englishLabel' ];
		$descriptions = [ $language => 'englishDescription' ];
		$aliases = [ $language => [ 'englishAlias' ] ];
		$existingContent = new WikibaseSchemaContent( json_encode( [
			'id' => $id,
			'serializationVersion' => '2.0',
			'labels' => $labels,
			'descriptions' => $descriptions,
			'aliases' => $aliases,
			'schema' => '# some schema about goats',
			'type' => 'ShExC',
		] ) );
		$newSchemaText = '# some schema about cats';
		$expectedContent = new WikibaseSchemaContent( json_encode( [
			'id' => $id,
			'serializationVersion' => '2.0',
			'labels' => $labels,
			'descriptions' => $descriptions,
			'aliases' => $aliases,
			'schema' => $newSchemaText,
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
			$newSchemaText
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
			'serializationVersion' => '2.0',
			'labels' => [ 'en' => 'Cat' ],
			'descriptions' => [ 'en' => 'This is what a cat look like' ],
			'aliases' => [ 'en' => [ 'Tiger', 'Lion' ] ],
			'schema' => '# some schema about goats',
			'type' => 'ShExC',
		] ) );
		$expectedContent = new WikibaseSchemaContent( json_encode( [
			'id' => $id,
			'serializationVersion' => '2.0',
			'labels' => $labels,
			'descriptions' => $descriptions,
			'aliases' => $aliases,
			'schema' => '# some schema about goats',
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
			$aliases['en']
		);
	}

}
