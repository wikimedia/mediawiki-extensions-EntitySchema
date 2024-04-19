<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\Services\Converter\EntitySchemaConverter;
use EntitySchema\Services\Converter\FullViewEntitySchemaData;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\WikiPageFactory;
use TitleFactory;

/**
 * Lookup for loading the full-view data of an EntitySchema.
 * Mainly for usage in other, more specific lookups.
 *
 * @license GPL-2.0-or-later
 */
class FullViewSchemaDataLookup {

	private TitleFactory $titleFactory;
	private WikiPageFactory $wikiPageFactory;

	public function __construct(
		TitleFactory $titleFactory,
		WikiPageFactory $wikiPageFactory
	) {
		$this->titleFactory = $titleFactory;
		$this->wikiPageFactory = $wikiPageFactory;
	}

	/**
	 * Load the full-view EntitySchema data for the given ID.
	 * Returns null if the EntitySchema does not exist.
	 */
	public function getFullViewSchemaData( EntitySchemaId $id ): ?FullViewEntitySchemaData {
		$title = $this->titleFactory->makeTitleSafe( NS_ENTITYSCHEMA_JSON, $id->getId() );
		if ( $title === null ) {
			return null;
		}

		return $this->getFullViewSchemaDataForTitle( $title );
	}

	/**
	 * Load the full-view EntitySchema data for the given page identity,
	 * which must be in the EntitySchema namespace.
	 * (Note that $title can also be a WikiPage, which implements PageIdentity.)
	 * Returns null if the EntitySchema does not exist.
	 */
	public function getFullViewSchemaDataForTitle( PageIdentity $title ): ?FullViewEntitySchemaData {
		$wikiPage = $this->wikiPageFactory->newFromTitle( $title );
		$content = $wikiPage->getContent();
		if ( !( $content instanceof EntitySchemaContent ) ) {
			return null;
		}

		$schema = $content->getText();

		$converter = new EntitySchemaConverter();
		return $converter->getFullViewSchemaData( $schema, [] );
	}

}
