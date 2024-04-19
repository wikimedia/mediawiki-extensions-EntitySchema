<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use MediaWiki\Page\PageIdentity;
use Wikibase\DataModel\Term\TermFallback;
use Wikibase\Lib\LanguageFallbackChainFactory;

/**
 * Lookup for EntitySchema descriptions, with language fallbacks applied.
 *
 * @license GPL-2.0-or-later
 */
class DescriptionLookup {

	private FullViewSchemaDataLookup $fullViewSchemaDataLookup;

	private LanguageFallbackChainFactory $languageFallbackChainFactory;

	public function __construct(
		FullViewSchemaDataLookup $fullViewSchemaDataLookup,
		LanguageFallbackChainFactory $languageFallbackChainFactory
	) {
		$this->fullViewSchemaDataLookup = $fullViewSchemaDataLookup;
		$this->languageFallbackChainFactory = $languageFallbackChainFactory;
	}

	/**
	 * Look up the description of the EntitySchema with the given title, if any.
	 * Language fallbacks are applied based on the given language code.
	 *
	 * @param PageIdentity $title
	 * @param string $langCode
	 * @return TermFallback|null The description, or null if no description or EntitySchema was found.
	 */
	public function getDescriptionForTitle( PageIdentity $title, string $langCode ): ?TermFallback {
		$schemaData = $this->fullViewSchemaDataLookup->getFullViewSchemaDataForTitle( $title );
		if ( $schemaData === null ) {
			return null;
		}

		$chain = $this->languageFallbackChainFactory->newFromLanguageCode( $langCode );
		$preferredDescription = $chain->extractPreferredValue( array_map(
			fn ( $nameBadge ) => $nameBadge->description,
			$schemaData->nameBadges
		) );
		if ( $preferredDescription !== null ) {
			return new TermFallback(
				$langCode,
				$preferredDescription['value'],
				$preferredDescription['language'],
				$preferredDescription['source']
			);
		} else {
			return null;
		}
	}
}
