<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use EntitySchema\Services\Converter\FullViewEntitySchemaData;
use Wikibase\DataModel\Term\TermFallback;
use Wikibase\Lib\LanguageFallbackChainFactory;

/**
 * Lookup for EntitySchema labels, with language fallbacks applied.
 *
 * @license GPL-2.0-or-later
 */
class LabelLookup {

	private LanguageFallbackChainFactory $languageFallbackChainFactory;

	public function __construct(
		LanguageFallbackChainFactory $languageFallbackChainFactory
	) {
		$this->languageFallbackChainFactory = $languageFallbackChainFactory;
	}

	/**
	 * Look up the label of the EntitySchema with the supplied schema data, if any.
	 * Language fallbacks are applied based on the given language code.
	 *
	 * @param FullViewEntitySchemaData $schemaData
	 * @param string $langCode
	 * @return TermFallback|null The label, or null if no label or EntitySchema was found.
	 */
	public function getLabelForSchemaData(
		FullViewEntitySchemaData $schemaData,
		string $langCode
	): ?TermFallback {
		$chain = $this->languageFallbackChainFactory->newFromLanguageCode( $langCode );
		$preferredLabel = $chain->extractPreferredValue( array_map(
			static fn ( $nameBadge ) => $nameBadge->label,
			$schemaData->nameBadges
		) );
		if ( $preferredLabel !== null ) {
			return new TermFallback(
				$langCode,
				$preferredLabel['value'],
				$preferredLabel['language'],
				$preferredLabel['source']
			);
		} else {
			return null;
		}
	}
}
