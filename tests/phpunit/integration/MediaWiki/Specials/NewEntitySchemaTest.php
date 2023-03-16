<?php

namespace EntitySchema\Tests\Integration\MediaWiki\Specials;

use EntitySchema\MediaWiki\Specials\NewEntitySchema;
use FauxRequest;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Revision\SlotRecord;
use PermissionsError;
use ReadOnlyError;
use ReadOnlyMode;
use SpecialPageTestBase;
use TextContent;
use TitleValue;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * @covers \EntitySchema\MediaWiki\Specials\NewEntitySchema
 *
 * @group Database
 *
 * @license GPL-2.0-or-later
 */
class NewEntitySchemaTest extends SpecialPageTestBase {

	/** @var DatabaseBlock */
	private $block;

	public function testReadOnly() {
		$readOnlyMode = $this->getMockBuilder( ReadOnlyMode::class )
			->disableOriginalConstructor()
			->getMock();
		$readOnlyMode->method( 'isReadOnly' )->willReturn( true );
		$this->setService( 'ReadOnlyMode', $readOnlyMode );
		$this->expectException( ReadOnlyError::class );
		$this->executeSpecialPage( null );
	}

	public function testNoRights() {
		$this->mergeMwGlobalArrayValue( 'wgGroupPermissions',
			[ '*' => [ 'createpage' => false ] ] );
		$this->expectException( PermissionsError::class );
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
					NewEntitySchema::FIELD_LABEL => $testLabel,
					NewEntitySchema::FIELD_LANGUAGE => 'en',
				],
				true
			)
		);
		$textOfNewestPage = $this->getLastCreatedPageText();
		$this->assertStringContainsString( $testLabel, $textOfNewestPage );
	}

	public function testNewSchemaIsNotCreatedWhenBlocked() {
		$testuser = self::getTestUser()->getUser();
		$this->block = new DatabaseBlock(
			[
				'address' => $testuser,
				'reason' => 'testing in ' . __CLASS__,
				'by' => $testuser,
			]
		);
		$this->block->insert();

		$testLabel = uniqid( 'testLabel_' . __FUNCTION__ . '_' );

		try {
			$this->executeSpecialPage(
				null,
				new FauxRequest(
					[
						NewEntitySchema::FIELD_LABEL => $testLabel,
					],
					true
				),
				'en',
				$testuser
			);

			$this->fail( 'a blocked user must cause an exception!' );
		} catch ( PermissionsError $e ) {
			// we expect that
		}

		$textOfNewestPage = $this->getLastCreatedPageText();
		$this->assertStringNotContainsString(
			$testLabel,
			$textOfNewestPage,
			'Blocked User was able to create new Schema!'
		);
	}

	public function testNewSchemaIsNotCreatedWithInvalidData() {
		$this->setUserLang( 'qqx' );
		list( $html, ) = $this->executeSpecialPage(
			null,
			new FauxRequest(
				[

				], true
			)
		);

		$this->assertStringContainsString(
			'(entityschema-error-',
			$html,
			'error status message is missing'
		);
		$this->assertStringContainsString(
			'(htmlform-required)',
			$html,
			'message about required value is missing'
		);
	}

	protected function tearDown(): void {
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
		$row = $this->db->newSelectQueryBuilder()
			->select( [ 'page_namespace',  'page_title' ] )
			->from( 'page' )
			->orderBy( [ 'page_id' ], SelectQueryBuilder::SORT_DESC )
			->caller( __METHOD__ )
			->fetchRow();
		return new TitleValue( (int)$row->page_namespace, $row->page_title );
	}

	/**
	 * Gets the text of the last created page.
	 * @return string|null
	 */
	protected function getLastCreatedPageText() {
		$content = $this->getServiceContainer()
			->getRevisionLookup()
			->getRevisionByTitle( $this->getLastCreatedTitle() )
			->getContent( SlotRecord::MAIN );
		return ( $content instanceof TextContent ) ? $content->getText() : null;
	}

	protected function newSpecialPage() {
		return new NewEntitySchema();
	}

}
