<?php

namespace Wikibase\Schema\Tests\DataAccess;

use MediaWiki\Storage\RevisionRecord;
use \RuntimeException;
use MediaWiki\Storage\PageUpdater;
use stdClass;
use Wikibase\Schema\DataAccess\MediaWikiPageUpdaterFactory;
use Wikibase\Schema\DataAccess\MediaWikiRevisionSchemaWriter;
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
		$schemaContent = '#some fake schema {}';
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
					'schema' => $schemaContent,
					'type' => 'ShExC',
				]
			)
		);

		$pageUpdaterFactory = $this
			->getPageUpdaterFactoryProvidingAndExpectingContent( $expectedContent );

		$idGenerator = $this->createMock( IdGenerator::class );
		$idGenerator->method( 'getNewId' )->willReturn( '123' );

		$writer = new MediaWikiRevisionSchemaWriter( $pageUpdaterFactory, $idGenerator );

		$writer->insertSchema( $language,
			$label,
			$description,
			$aliases,
			$schemaContent
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

	public function testUpdateSchema_throwsForNonExistantPage() {
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdaterFactory = $this->getPageUpdaterFactory( $pageUpdater );
		$idGenerator = $this->createMock( IdGenerator::class );
		$writer = new MediaWikiRevisionSchemaWriter( $pageUpdaterFactory, $idGenerator );

		$this->expectException( RuntimeException::class );
		$writer->updateSchema(
			new SchemaId( 'O123456999999999' ),
			'',
			'',
			'',
			[],
			''
		);
	}

	public function provideBadParameters() {
		return [
			'language is not string' => [ new stdClass() ,'', '', [], '' ],
			'label is not string' => [ '' , new StdClass(), '', [], '' ],
			'description is not string' => [ '' , '', new StdClass(), [], '' ],
			'aliases is non-string array' => [ '' ,'', '', [ new stdClass() ], '' ],
			'aliases is mixed array' => [ '' ,'', '', [ new stdClass(), 'foo' ], '' ],
			'aliases is associative array' => [ '' ,'', '', [ 'en' => 'foo' ], '' ],
			'schema content is not string' => [ '' , '', '', [], new StdClass(), ],
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
		$testSchemaContent
	) {
		$pageUpdaterFactory = $this->getPageUpdaterFactory();
		$idGenerator = $this->createMock( IdGenerator::class );
		$writer = new MediaWikiRevisionSchemaWriter( $pageUpdaterFactory, $idGenerator );
		$this->expectException( RuntimeException::class );
		$writer->updateSchema(
			new SchemaId( 'O1' ),
			$testLanguage,
			$testLabel,
			$testDescription,
			$testAliases,
			$testSchemaContent
		);
	}

	public function testUpdateSchema_WritesExpectedContentForOverwritingMonoLingualSchema() {
		$id = 'O1';
		$language = 'en';
		$label = 'englishLabel';
		$description = 'englishDescription';
		$aliases = [ 'englishAlias' ];
		$schemaContent = '#some schema about goats';
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
				'schema' => $schemaContent,
				'type' => 'ShExC',
			]
		) );
		$pageUpdaterFactory = $this
			->getPageUpdaterFactoryProvidingAndExpectingContent( $expectedContent, $existingContent );
		$idGenerator = $this->createMock( IdGenerator::class );
		$writer = new MediaWikiRevisionSchemaWriter( $pageUpdaterFactory, $idGenerator );
		$writer->updateSchema(
			new SchemaId( 'O1' ),
			'en',
			'englishLabel',
			'englishDescription',
			$aliases,
			$schemaContent
		);
	}

}
