<?php

namespace EntitySchema\Tests\MediaWiki;

use Diff\DiffOp\Diff\Diff;
use Diff\DiffOp\DiffOpChange;
use DomainException;
use MediaWikiTestCase;
use EntitySchema\MediaWiki\Content\WikibaseSchemaContent;
use EntitySchema\MediaWiki\UndoHandler;

/**
 * @license GPL-2.0-or-later
 *
 * @covers \EntitySchema\MediaWiki\UndoHandler
 */
class UndoHandlerTest extends MediaWikiTestCase {

	public function testAssertSameId() {
		$id = 'E123';

		$content1 = new WikibaseSchemaContent(
			json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
			] )
		);
		$content2 = new WikibaseSchemaContent(
			json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
			] )
		);
		$contentBase = new WikibaseSchemaContent(
			json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
			] )
		);

		$undoHandler = new UndoHandler();
		$actualSchemaId = $undoHandler->validateContentIds( $content1, $content2, $contentBase );

		$this->assertSame( $id, $actualSchemaId->getId() );
	}

	public function inconsistentIdProvider() {
		yield 'invalidWithoutThirdId' => [
			'E12', 'E123', null
		];

		yield 'thirdIdDifferent' => [
			'E123', 'E123', 'E12'
		];
	}

	/**
	 * @dataProvider inconsistentIdProvider
	 */
	public function testAssertSameIdFail( $firstID, $secondID, $thirdID ) {
		$content1 = new WikibaseSchemaContent(
			json_encode( [
				'id' => $firstID,
				'serializationVersion' => '3.0',
			] )
		);
		$content2 = new WikibaseSchemaContent(
			json_encode( [
				'id' => $secondID,
				'serializationVersion' => '3.0',
			] )
		);
		$contentBase = null;
		if ( $thirdID !== null ) {
			$contentBase = new WikibaseSchemaContent(
				json_encode( [
					'id' => $thirdID,
					'serializationVersion' => '3.0',
				] )
			);
		}

		$undoHandler = new UndoHandler();
		$this->expectException( DomainException::class );
		$undoHandler->validateContentIds( $content1, $content2, $contentBase );
	}

	public function testGetDiffFromContents() {
		$goodContent = new WikibaseSchemaContent(
			json_encode( [
				'labels' => [
					'en' => 'abc',
				],
				'serializationVersion' => '3.0',
			] )
		);
		$contentToBeUndone = new WikibaseSchemaContent(
			json_encode( [
				'labels' => [
					'en' => 'def',
				],
				'serializationVersion' => '3.0',
			] )
		);

		$undoHandler = new UndoHandler();

		$actualDiffStatus = $undoHandler->getDiffFromContents( $contentToBeUndone, $goodContent );

		$this->assertTrue( $actualDiffStatus->isGood() );
		$expectedDiff = new Diff(
			[
				'labels' => new Diff(
					[
						'en' => new DiffOpChange( 'def', 'abc' ),
					],
					true
				),
			],
			true
		);
		$actualDiff = $actualDiffStatus->getValue();
		$this->assertSame( $expectedDiff->toArray(), $actualDiff->toArray() );
	}

	public function testTryPatching() {

		$baseContent = new WikibaseSchemaContent(
			json_encode( [
				'labels' => [
					'en' => 'def',
				],
				'serializationVersion' => '3.0',
			] )
		);
		$diff = new Diff(
			[
				'labels' => new Diff(
					[
						'en' => new DiffOpChange( 'def', 'abc' ),
					],
					true
				),
			],
			true
		);
		$undoHandler = new UndoHandler();

		$actualPatchStatus = $undoHandler->tryPatching( $diff, $baseContent );

		$this->assertTrue( $actualPatchStatus->isGood() );
		$expectedSchema = [
			'labels' => [
				'en' => 'abc',
			],
			'descriptions' => [],
			'aliases' => [],
			'schemaText' => ''
		];
		$actualSchema = $actualPatchStatus->getValue()->data;
		$this->assertSame( $expectedSchema, $actualSchema );
	}

	public function testTryPatchingFail() {

		$baseContent = new WikibaseSchemaContent(
			json_encode( [
				'labels' => [
					'en' => 'ghi',
				],
				'serializationVersion' => '3.0',
			] )
		);
		$diff = new Diff(
			[
				'labels' => new Diff(
					[
						'en' => new DiffOpChange( 'def', 'abc' ),
					],
					true
				),
			],
			true
		);
		$undoHandler = new UndoHandler();

		$actualPatchStatus = $undoHandler->tryPatching( $diff, $baseContent );

		$this->assertFalse( $actualPatchStatus->isOK() );
		$actualMessage = $actualPatchStatus->getMessage();
		$this->assertSame( 'entityschema-undo-cannot-apply-patch', $actualMessage->getKey() );
	}

}
