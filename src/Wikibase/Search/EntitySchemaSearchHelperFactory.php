<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\Search;

use EntitySchema\DataAccess\DescriptionLookup;
use EntitySchema\DataAccess\LabelLookup;
use MediaWiki\Context\IContextSource;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\TitleFactory;

/**
 * @license GPL-2.0-or-later
 */
class EntitySchemaSearchHelperFactory {

	private TitleFactory $titleFactory;
	private WikiPageFactory $wikiPageFactory;
	private string $wikibaseConceptBaseUri;
	private DescriptionLookup $descriptionLookup;
	private LabelLookup $labelLookup;

	public function __construct(
		TitleFactory $titleFactory,
		WikiPageFactory $wikiPageFactory,
		string $wikibaseConceptBaseUri,
		DescriptionLookup $descriptionLookup,
		LabelLookup $labelLookup
	) {
		$this->titleFactory = $titleFactory;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->wikibaseConceptBaseUri = $wikibaseConceptBaseUri;
		$this->descriptionLookup = $descriptionLookup;
		$this->labelLookup = $labelLookup;
	}

	public function newForContext( IContextSource $context ): EntitySchemaSearchHelper {
		return new EntitySchemaSearchHelper(
			$this->titleFactory,
			$this->wikiPageFactory,
			$this->wikibaseConceptBaseUri,
			$this->descriptionLookup,
			$this->labelLookup,
			$context->getLanguage()->getCode()
		);
	}

}
