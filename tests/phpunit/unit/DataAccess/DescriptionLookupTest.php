<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\DataAccess;

use EntitySchema\DataAccess\DescriptionLookup;
use EntitySchema\DataAccess\FullViewSchemaDataLookup;
use EntitySchema\Services\Converter\FullViewEntitySchemaData;
use EntitySchema\Services\Converter\NameBadge;
use MediaWiki\Page\PageIdentity;
use MediaWikiUnitTestCase;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Lib\TermLanguageFallbackChain;

/**
 * @covers \EntitySchema\DataAccess\DescriptionLookup
 * @license GPL-2.0-or-later
 */
class DescriptionLookupTest extends MediaWikiUnitTestCase {

	public function testTitleDoesNotExist(): void {
		$stubDataLookup = $this->createConfiguredMock(
			FullViewSchemaDataLookup::class,
			[ 'getFullViewSchemaDataForTitle' => null ]
		);
		$stubLanguageFallbackChainFactory = $this->createStub( LanguageFallbackChainFactory::class );
		$descriptionLookup = new DescriptionLookup( $stubDataLookup, $stubLanguageFallbackChainFactory );

		$actualResult = $descriptionLookup->getDescriptionForTitle( $this->createMock( PageIdentity::class ), 'en' );

		$this->assertNull( $actualResult );
	}

	public function testNoDescriptionInLanguage(): void {
		$stubDataLookup = $this->createConfiguredMock(
			FullViewSchemaDataLookup::class,
			[ 'getFullViewSchemaDataForTitle' => new FullViewEntitySchemaData( [
				'de' => new NameBadge( '', 'Schema für Menschen', [] ),
			], '' ) ]
		);
		$stubLanguageFallbackChain = $this->createConfiguredMock(
			TermLanguageFallbackChain::class,
			[ 'extractPreferredValue' => null ],
		);
		$stubLanguageFallbackChainFactory = $this->createConfiguredMock(
			LanguageFallbackChainFactory::class,
			[ 'newFromLanguageCode' => $stubLanguageFallbackChain ]
		);

		$descriptionLookup = new DescriptionLookup( $stubDataLookup, $stubLanguageFallbackChainFactory );

		$actualResult = $descriptionLookup->getDescriptionForTitle( $this->createMock( PageIdentity::class ), 'en' );

		$this->assertNull( $actualResult );
	}

	public function testDescriptionInLanguageAvailable(): void {
		$stubDataLookup = $this->createConfiguredMock(
			FullViewSchemaDataLookup::class,
			[ 'getFullViewSchemaDataForTitle' => new FullViewEntitySchemaData( [
				'de' => new NameBadge( '', 'Schema für Menschen', [] ),
			], '' ) ]
		);
		$stubLanguageFallbackChain = $this->createConfiguredMock(
			TermLanguageFallbackChain::class,
			[ 'extractPreferredValue' => [
				'value' => 'Schema für Menschen',
				'language' => 'de',
				'source' => 'de',
			] ],
		);
		$stubLanguageFallbackChainFactory = $this->createConfiguredMock(
			LanguageFallbackChainFactory::class,
			[ 'newFromLanguageCode' => $stubLanguageFallbackChain ]
		);

		$descriptionLookup = new DescriptionLookup( $stubDataLookup, $stubLanguageFallbackChainFactory );

		$actualResult = $descriptionLookup->getDescriptionForTitle( $this->createMock( PageIdentity::class ), 'de' );

		$this->assertSame( 'de', $actualResult->getLanguageCode() );
		$this->assertSame( 'de', $actualResult->getActualLanguageCode() );
		$this->assertSame( 'Schema für Menschen', $actualResult->getText() );
	}

	public function testDescriptionInFallbackLanguageAvailable(): void {
		$stubDataLookup = $this->createConfiguredMock(
			FullViewSchemaDataLookup::class,
			[ 'getFullViewSchemaDataForTitle' => new FullViewEntitySchemaData( [
				'en' => new NameBadge( '', 'schema for humans', [] ),
			], '' ) ]
		);
		$stubLanguageFallbackChain = $this->createConfiguredMock(
			TermLanguageFallbackChain::class,
			[ 'extractPreferredValue' => [
				'value' => 'schema for humans',
				'language' => 'en',
				'source' => 'en',
			] ],
		);
		$stubLanguageFallbackChainFactory = $this->createConfiguredMock(
			LanguageFallbackChainFactory::class,
			[ 'newFromLanguageCode' => $stubLanguageFallbackChain ]
		);

		$descriptionLookup = new DescriptionLookup( $stubDataLookup, $stubLanguageFallbackChainFactory );

		$actualResult = $descriptionLookup->getDescriptionForTitle( $this->createMock( PageIdentity::class ), 'de-at' );

		$this->assertSame( 'de-at', $actualResult->getLanguageCode() );
		$this->assertSame( 'en', $actualResult->getActualLanguageCode() );
		$this->assertSame( 'schema for humans', $actualResult->getText() );
	}
}
