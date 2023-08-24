<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\DataAccess;

use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\MediaWiki\EntitySchemaServices;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use WikiPage;

/**
 * @license GPL-2.0-or-later
 * @coversNothing
 * @group Database
 */
class LabelLookupTest extends MediaWikiIntegrationTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->tablesUsed[] = 'page';
		$this->tablesUsed[] = 'revision';
	}

	public function testGetLabel_LabelExistsInLanguage() {
		$id = 'E456';
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $id );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$englishLabel = 'en label';
		$this->saveSchemaPageContent( $page, [
			'labels' => [ 'en' => $englishLabel ],
		] );
		$labelLookup = EntitySchemaServices::getLabelLookup( $this->getServiceContainer() );

		$actualLabelTerm = $labelLookup->getLabelForTitle( $title, 'en' );

		$this->assertSame( $englishLabel, $actualLabelTerm->getText() );
		$this->assertSame( 'en', $actualLabelTerm->getLanguageCode() );
	}

	public function testGetLabel_LabelExistsInFallbackLanguage() {
		$id = 'E456';
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $id );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$englishLabel = 'en label';
		$this->saveSchemaPageContent( $page, [
			'labels' => [ 'en' => $englishLabel ],
		] );
		$labelLookup = EntitySchemaServices::getLabelLookup( $this->getServiceContainer() );

		$actualLabelTerm = $labelLookup->getLabelForTitle( $title, 'de' );

		$this->assertSame( $englishLabel, $actualLabelTerm->getText() );
		$this->assertSame( 'en', $actualLabelTerm->getLanguageCode() );
	}

	public function testGetLabel_NoLabelInLanguage() {
		$id = 'E4567';
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $id );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$germanLabel = 'de label';
		$this->saveSchemaPageContent( $page, [
			'labels' => [ 'de' => $germanLabel ],
		] );
		$labelLookup = EntitySchemaServices::getLabelLookup( $this->getServiceContainer() );

		$actualLabelTerm = $labelLookup->getLabelForTitle( $title, 'en' );

		$this->assertNull( $actualLabelTerm );
	}

	public function testGetLabel_SchemaDoesNotExist() {
		$id = 'E45678';
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $id );
		$labelLookup = EntitySchemaServices::getLabelLookup( $this->getServiceContainer() );

		$actualLabelTerm = $labelLookup->getLabelForTitle( $title, 'en' );

		$this->assertNull( $actualLabelTerm );
	}

	private function saveSchemaPageContent( WikiPage $page, array $content ): RevisionRecord {
		$content['serializationVersion'] = '3.0';
		$updater = $page->newPageUpdater( $this->getTestUser()->getUser() );
		$updater->setContent( SlotRecord::MAIN, new EntitySchemaContent( json_encode( $content ) ) );
		$firstRevRecord = $updater->saveRevision(
			CommentStoreComment::newUnsavedComment(
				'test summary'
			)
		);

		return $firstRevRecord;
	}

}
