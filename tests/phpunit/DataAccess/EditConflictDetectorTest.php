<?php

namespace Wikibase\Schema\Tests\DataAccess;

use MediaWiki\Revision\RevisionStore;
use MediaWiki\Storage\RevisionRecord;
use Wikibase\Schema\DataAccess\EditConflictDetector;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;

/**
 * @license GPL-2.0-or-later
 * @covers \Wikibase\Schema\DataAccess\EditConflictDetector
 */
class EditConflictDetectorTest extends \MediaWikiTestCase {

	public function provideNameBadgeConflictData() {
		yield 'differentLanguageChanged' => [
			[
				'labels' => [ 'en' => 'Cat' ],
				'descriptions' => [ 'en' => 'This is what a cat look like' ],
				'aliases' => [ 'en' => [ 'Tiger', 'Lion' ] ],
				'schemaText' => '# some schema about goats',
			],
			[
				'labels' => [ 'en' => 'Cat' ],
				'descriptions' => [ 'en' => 'This is what a cat look like' ],
				'aliases' => [ 'en' => [ 'Tiger', 'Lion' ] ],
				'schemaText' => '# some schema about goats',
			],
			false,
		];

		yield 'schemaTextChanged' => [
			[
				'labels' => [ 'en' => 'Cat' ],
				'schemaText' => 'original text of base revision',
			],
			[
				'labels' => [ 'en' => 'Cat' ],
				'schemaText' => 'changed text of parent revision',
			],
			false,
		];

		yield 'sameLanguageLabelChanged' => [
			[
				'labels' => [ 'en' => 'Cat' ],
			],
			[
				'labels' => [ 'en' => 'Goat' ],
			],
			true,
		];

		yield 'same Language Description Changed' => [
			[
				'descriptions' => [ 'en' => 'Cat' ],
			],
			[
				'descriptions' => [ 'en' => 'Goat' ],
			],
			true,
		];

		yield 'same Language Aliases Changed' => [
			[
				'aliases' => [ 'en' => [ 'Cat', 'Tiger' ] ],
			],
			[
				'aliases' => [ 'en' => [ 'Cat', 'Tiger', 'Lion' ] ],
			],
			true,
		];
	}

	/**
	 * @dataProvider provideNameBadgeConflictData
	 *
	 * @param array $baseRevData
	 * @param array $parentRevData
	 * @param bool $isConflict
	 */
	public function testIsNameBadgeEditConflict( $baseRevData, $parentRevData, $isConflict ) {
		$defaultData = [
			'serializationVersion' => '3.0',
			'type' => 'ShExC',
		];

		$baseRevisionRecord = $this->createMockRevisionRecord( new WikibaseSchemaContent(
			json_encode( array_merge( $defaultData, $baseRevData ) ) ) );
		$mockRevStore = $this->getMockBuilder( RevisionStore::class )
			->disableOriginalConstructor()
			->getMock();
		$mockRevStore->method( 'getRevisionById' )->willReturn(
			$baseRevisionRecord
		);

		$parentRevisionRecord = $this->createMockRevisionRecord( new WikibaseSchemaContent(
			json_encode( array_merge( $defaultData, $parentRevData ) ) ) );

		$detector = new EditConflictDetector( $mockRevStore );

		$actualResult = $detector->isNameBadgeEditConflict( $parentRevisionRecord, 2, 'en' );

		$this->assertSame( $isConflict, $actualResult );
	}

	public function provideSchemaTextConflictData() {
		yield 'NameBadge changed' => [
			[
				'labels' => [ 'en' => 'Cat' ],
				'schemaText' => 'originalText',
			],
			[
				'labels' => [ 'en' => 'Goat' ],
				'schemaText' => 'originalText',
			],
			false,
		];

		yield 'schemaText changed completely' => [
			[
				'schemaText' => 'original SchemaText',
			],
			[
				'schemaText' => 'the text has changed',
			],
			true,
		];
	}

	/**
	 * @dataProvider provideSchemaTextConflictData
	 *
	 * @param array $baseRevData
	 * @param array $parentRevData
	 * @param bool $isConflict
	 */
	public function testIsSchemaTextEditConflict( $baseRevData, $parentRevData, $isConflict ) {
		$defaultData = [
			'serializationVersion' => '3.0',
			'type' => 'ShExC',
		];

		$baseRevisionRecord = $this->createMockRevisionRecord( new WikibaseSchemaContent(
			json_encode( array_merge( $defaultData, $baseRevData ) ) ) );
		$mockRevStore = $this->getMockBuilder( RevisionStore::class )
			->disableOriginalConstructor()
			->getMock();
		$mockRevStore->method( 'getRevisionById' )->willReturn(
			$baseRevisionRecord
		);

		$parentRevisionRecord = $this->createMockRevisionRecord( new WikibaseSchemaContent(
			json_encode( array_merge( $defaultData, $parentRevData ) ) ) );

		$detector = new EditConflictDetector( $mockRevStore );

		$actualResult = $detector->isSchemaTextEditConflict( $parentRevisionRecord, 2 );

		$this->assertSame( $isConflict, $actualResult );
	}

	private function createMockRevisionRecord(
		WikibaseSchemaContent $content = null,
		$revId = 1
	): RevisionRecord {
		$revisionRecord = $this->createMock( RevisionRecord::class );
		$revisionRecord->method( 'getContent' )->willReturn( $content );
		$revisionRecord->method( 'getId' )->willReturn( $revId );
		return $revisionRecord;
	}

}
