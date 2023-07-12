<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use EntitySchema\Services\SchemaConverter\SchemaConverter;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\WikiPageFactory;

/**
 * @license GPL-2.0-or-later
 */
class LabelLookup {

	private WikiPageFactory $wikiPageFactory;

	public function __construct( WikiPageFactory $wikiPageFactory ) {
		$this->wikiPageFactory = $wikiPageFactory;
	}

	public function getLabelForTitle( PageIdentity $title, string $langCode ): ?EntitySchemaTerm {
		$wikiPage = $this->wikiPageFactory->newFromTitle( $title );
		$content = $wikiPage->getContent();
		'@phan-var \EntitySchema\MediaWiki\Content\EntitySchemaContent|null $content';
		if ( !$content ) {
			return null;
		}

		$schema = $content->getText();

		$converter = new SchemaConverter();
		$schemaData = $converter->getFullViewSchemaData( $schema, [ $langCode ] );

		// TODO: Language fallback should be implemented here. See T330491

		if ( $schemaData->nameBadges[ $langCode ]->label !== '' ) {
			return new EntitySchemaTerm( $langCode, $schemaData->nameBadges[ $langCode ]->label );
		}

		return null;
	}
}
