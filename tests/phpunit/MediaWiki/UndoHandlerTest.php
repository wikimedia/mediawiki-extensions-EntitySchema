<?php

namespace phpunit\MediaWiki;

use Diff\DiffOp\Diff\Diff;
use Diff\DiffOp\DiffOpChange;
use MediaWikiTestCase;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use Wikibase\Schema\MediaWiki\UndoHandler;

/**
 * @license GPL-2.0-or-later
 *
 * @covers \Wikibase\Schema\MediaWiki\UndoHandler
 */
class UndoHandlerTest extends MediaWikiTestCase {

	public function testGetDiffFromContents() {
		$goodContent = new WikibaseSchemaContent(
			json_encode( [
				'labels' => [
					'en' => 'abc',
				],
				'serializationVersion' => '2.0',
			] )
		);
		$contentToBeUndone = new WikibaseSchemaContent(
			json_encode( [
				'labels' => [
					'en' => 'def',
				],
				'serializationVersion' => '2.0',
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
				'serializationVersion' => '2.0',
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
		];
		$actualSchema = $actualPatchStatus->getValue();
		$this->assertSame( $expectedSchema, $actualSchema );
	}

	public function testTryPatchingFail() {

		$baseContent = new WikibaseSchemaContent(
			json_encode( [
				'labels' => [
					'en' => 'ghi',
				],
				'serializationVersion' => '2.0',
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
		$this->assertSame( 'wikibaseschema-undo-cannot-apply-patch', $actualMessage->getKey() );
	}

}
