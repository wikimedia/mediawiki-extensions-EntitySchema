<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use MediaWiki\Page\PageIdentity;
use Wikibase\DataModel\Term\TermFallback;

/**
 * Lookup for EntitySchema labels, with language fallbacks applied.
 * Resolves the EntitySchema data from a PageTitle
 *
 * @license GPL-2.0-or-later
 */
class SchemaDataResolvingLabelLookup {

	private FullViewSchemaDataLookup $fullViewSchemaDataLookup;
	private LabelLookup $labelLookup;

	public function __construct(
		FullViewSchemaDataLookup $fullViewSchemaDataLookup,
		LabelLookup $labelLookup
	) {
		$this->fullViewSchemaDataLookup = $fullViewSchemaDataLookup;
		$this->labelLookup = $labelLookup;
	}

	/**
	 * Look up the label of the EntitySchema with the given title, if any.
	 * Language fallbacks are applied based on the given language code.
	 *
	 * @param PageIdentity $title
	 * @param string $langCode
	 * @return TermFallback|null The label, or null if no label or EntitySchema was found.
	 */
	public function getLabelForTitle( PageIdentity $title, string $langCode ): ?TermFallback {
		$schemaData = $this->fullViewSchemaDataLookup->getFullViewSchemaDataForTitle( $title );
		if ( $schemaData === null ) {
			return null;
		}
		return $this->labelLookup->getLabelForSchemaData( $schemaData, $langCode );
	}
}
