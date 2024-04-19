<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\Search;

use EntitySchema\DataAccess\DescriptionLookup;
use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\Wikibase\DataValues\EntitySchemaValue;
use InvalidArgumentException;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\TitleFactory;
use Wikibase\DataModel\Term\Term;
use Wikibase\Lib\Interactors\TermSearchResult;
use Wikibase\Repo\Api\ConceptUriSearchHelper;
use Wikibase\Repo\Api\EntitySearchHelper;

/**
 * @license GPL-2.0-or-later
 */
class EntitySchemaSearchHelper implements EntitySearchHelper {

	/** @var string Not a real entity type, but registered under this name in wbsearchentities. */
	public const ENTITY_TYPE = EntitySchemaValue::TYPE;

	private TitleFactory $titleFactory;
	private WikiPageFactory $wikiPageFactory;
	private string $wikibaseConceptBaseUri;
	private DescriptionLookup $descriptionLookup;
	private LabelLookup $labelLookup;
	private string $userLanguageCode;

	public function __construct(
		TitleFactory $titleFactory,
		WikiPageFactory $wikiPageFactory,
		string $wikibaseConceptBaseUri,
		DescriptionLookup $descriptionLookup,
		LabelLookup $labelLookup,
		string $userLanguageCode
	) {
		$this->titleFactory = $titleFactory;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->wikibaseConceptBaseUri = $wikibaseConceptBaseUri;
		$this->descriptionLookup = $descriptionLookup;
		$this->labelLookup = $labelLookup;
		$this->userLanguageCode = $userLanguageCode;
	}

	/** @inheritDoc */
	public function getRankedSearchResults(
		$text,
		$languageCode,
		$entityType,
		$limit,
		$strictLanguage,
		?string $profileContext
	) {
		if ( $entityType !== self::ENTITY_TYPE ) {
			return [];
		}

		try {
			$id = new EntitySchemaId( $text );
		} catch ( InvalidArgumentException $e ) {
			return [];
		}

		$title = $this->titleFactory->newFromText( $id->getId(), NS_ENTITYSCHEMA_JSON );
		if ( !$title ) {
			return [];
		}
		$wikiPage = $this->wikiPageFactory->newFromTitle( $title );
		if ( !$wikiPage->exists() ) {
			return [];
		}

		// pass the full $wikiPage into the lookup so it doesn’t have to be looked up again
		$label = $this->labelLookup->getLabelForTitle( $wikiPage, $this->userLanguageCode );
		$description = $this->descriptionLookup->getDescriptionForTitle( $wikiPage, $this->userLanguageCode );

		// the qid “language code” (in the ISO 639-3 reserved range) and entityId “matched term type”
		// are also used by EntityIdSearchHelper in Wikibase
		return [ new TermSearchResult(
			new Term( 'qid', $id->getId() ),
			'entityId',
			null,
			$label,
			$description,
			[
				'id' => $id->getId(),
				'title' => $title->getFullText(),
				'pageid' => $title->getId(),
				'url' => $title->getFullURL(),
				ConceptUriSearchHelper::CONCEPTURI_META_DATA_KEY =>
					$this->wikibaseConceptBaseUri . $id->getId(),
			]
		) ];
	}

}
