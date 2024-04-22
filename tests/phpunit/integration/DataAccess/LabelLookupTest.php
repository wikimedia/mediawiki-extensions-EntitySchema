<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\DataAccess;

use EntitySchema\MediaWiki\EntitySchemaServices;
use EntitySchema\Tests\Integration\EntitySchemaIntegrationTestCaseTrait;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @license GPL-2.0-or-later
 * @covers \EntitySchema\DataAccess\LabelLookup
 * @group Database
 */
class LabelLookupTest extends MediaWikiIntegrationTestCase {
	use EntitySchemaIntegrationTestCaseTrait;

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
		$this->assertSame( 'en', $actualLabelTerm->getActualLanguageCode() );
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
		$this->assertSame( 'en', $actualLabelTerm->getActualLanguageCode() );
		$this->assertSame( 'de', $actualLabelTerm->getLanguageCode() );
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

}
