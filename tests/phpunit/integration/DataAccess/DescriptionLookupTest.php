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
 * @covers \EntitySchema\DataAccess\DescriptionLookup
 * @group Database
 */
class DescriptionLookupTest extends MediaWikiIntegrationTestCase {

	public function testGetDescription_DescriptionExistsInLanguage() {
		$id = 'E456';
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $id );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$englishDescription = 'en description';
		$this->saveSchemaPageContent( $page, [
			'descriptions' => [ 'en' => $englishDescription ],
		] );
		$descriptionLookup = EntitySchemaServices::getDescriptionLookup( $this->getServiceContainer() );

		$actualDescriptionTerm = $descriptionLookup->getDescriptionForTitle( $title, 'en' );

		$this->assertSame( $englishDescription, $actualDescriptionTerm->getText() );
		$this->assertSame( 'en', $actualDescriptionTerm->getActualLanguageCode() );
		$this->assertSame( 'en', $actualDescriptionTerm->getLanguageCode() );
	}

	public function testGetDescription_DescriptionExistsInFallbackLanguage() {
		$id = 'E456';
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $id );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$englishDescription = 'en description';
		$this->saveSchemaPageContent( $page, [
			'descriptions' => [ 'en' => $englishDescription ],
		] );
		$descriptionLookup = EntitySchemaServices::getDescriptionLookup( $this->getServiceContainer() );

		$actualDescriptionTerm = $descriptionLookup->getDescriptionForTitle( $title, 'de' );

		$this->assertSame( $englishDescription, $actualDescriptionTerm->getText() );
		$this->assertSame( 'en', $actualDescriptionTerm->getActualLanguageCode() );
		$this->assertSame( 'de', $actualDescriptionTerm->getLanguageCode() );
	}

	public function testGetDescription_NoDescriptionInLanguage() {
		$id = 'E4567';
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $id );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$germanDescription = 'de description';
		$this->saveSchemaPageContent( $page, [
			'descriptions' => [ 'de' => $germanDescription ],
		] );
		$descriptionLookup = EntitySchemaServices::getDescriptionLookup( $this->getServiceContainer() );

		$actualDescriptionTerm = $descriptionLookup->getDescriptionForTitle( $title, 'en' );

		$this->assertNull( $actualDescriptionTerm );
	}

	public function testGetDescription_SchemaDoesNotExist() {
		$id = 'E45678';
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $id );
		$descriptionLookup = EntitySchemaServices::getDescriptionLookup( $this->getServiceContainer() );

		$actualDescriptionTerm = $descriptionLookup->getDescriptionForTitle( $title, 'en' );

		$this->assertNull( $actualDescriptionTerm );
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
