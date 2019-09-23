<?php

namespace EntitySchema\Tests\Integration\MediaWiki\Specials;

use ContentHandler;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\SlotRecord;
use PermissionsError;
use ReadOnlyError;
use ReadOnlyMode;
use FauxRequest;
use SpecialPageTestBase;
use TitleValue;
use UserBlockedError;

use EntitySchema\MediaWiki\Specials\NewEntitySchema;

/**
 * @covers \EntitySchema\MediaWiki\Specials\NewEntitySchema
 *
 * @group Database
 *
 * @license GPL-2.0-or-later
 */
class NewEntitySchemaTest extends SpecialPageTestBase {

	private $block;

	/**
	 * @expectedException ReadOnlyError
	 */
	public function testReadOnly() {
		$readOnlyMode = $this->getMockBuilder( ReadOnlyMode::class )
			->disableOriginalConstructor()
			->getMock();
		$readOnlyMode->method( 'isReadOnly' )->willReturn( true );
		$this->setService( 'ReadOnlyMode', $readOnlyMode );
		$this->executeSpecialPage( null );
	}

	/**
	 * @expectedException PermissionsError
	 */
	public function testNoRights() {
		$this->mergeMwGlobalArrayValue( 'wgGroupPermissions',
			[ '*' => [ 'createpage' => false ] ] );
		$this->executeSpecialPage( null );
	}

	/**
	 * @group slow
	 */
	public function testNewSchemaIsCreatedWithMinData() {
		$testLabel = uniqid( 'testLabel_' . __FUNCTION__ . '_' );
		$this->executeSpecialPage(
			null,
			new FauxRequest(
				[
					'label' => $testLabel,
				],
				true
			)
		);
		$textOfNewestPage = $this->getLastCreatedPageText();
		$this->assertContains( $testLabel, $textOfNewestPage );
	}

	public function testNewSchemaIsNotCreatedWhenBlocked() {
		$testuser = self::getTestUser()->getUser();
		$this->block = new DatabaseBlock(
			[
				'address' => $testuser,
				'reason' => 'testing in ' . __CLASS__,
				'by' => $testuser->getId(),
			]
		);
		$this->block->insert();

		$testLabel = uniqid( 'testLabel_' . __FUNCTION__ . '_' );

		try {
			$this->executeSpecialPage(
				null,
				new FauxRequest(
					[
						'label' => $testLabel,
					],
					true
				),
				'en',
				$testuser
			);

			$this->fail( 'a blocked user must cause an exception!' );
		} catch ( UserBlockedError $e ) {
			// we expect that
		}

		$textOfNewestPage = $this->getLastCreatedPageText();
		$this->assertNotContains(
			$testLabel,
			$textOfNewestPage,
			'Blocked User was able to create new Schema!'
		);
	}

	public function testNewSchemaIsNotCreatedWithInvalidData() {
		list( $html, ) = $this->executeSpecialPage(
			null,
			new FauxRequest(
				[

				], true
			)
		);

		$this->assertContains(
			'There are problems with some of your input.',
			$html,
			'error status message is missing'
		);
		$this->assertContains(
			'This value is required.',
			$html,
			'message about required value is missing'
		);
	}

	protected function tearDown() {
		if ( isset( $this->block ) ) {
			$this->block->delete();
		}

		parent::tearDown();
	}

	/**
	 * Gets the last created page.
	 * @return LinkTarget
	 */
	private function getLastCreatedTitle() {
		$row = $this->db->selectRow(
			[ 'page' ],
			[ 'page_namespace',  'page_title' ],
			[],
			__METHOD__,
			[ 'ORDER BY' => [ 'page_id DESC' ] ]
		);
		return new TitleValue( (int)$row->page_namespace, $row->page_title );
	}

	/**
	 * Gets the text of the last created page.
	 * @return string
	 * @throws \MWException
	 */
	protected function getLastCreatedPageText() {
		return ContentHandler::getContentText(
			MediaWikiServices::getInstance()
				->getRevisionLookup()
				->getRevisionByTitle( $this->getLastCreatedTitle() )
				->getContent( SlotRecord::MAIN )
		);
	}

	protected function newSpecialPage() {
		return new NewEntitySchema();
	}

}
