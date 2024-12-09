<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\Search;

use EntitySchema\Domain\Model\EntitySchemaId;
use InvalidArgumentException;
use TitleFactory;
use Wikibase\DataModel\Term\Term;
use Wikibase\Lib\Interactors\TermSearchResult;
use Wikibase\Lib\TermLanguageFallbackChain;
use Wikibase\Repo\Api\ConceptUriSearchHelper;
use Wikibase\Search\Elastic\ElasticTermResult;

/**
 * This result type implements the result for searching
 * an EntitySchema by its label or alias.
 *
 * @license GPL-2.0-or-later
 */
class ESElasticTermResult extends ElasticTermResult {

	private TitleFactory $titleFactory;
	private string $wikibaseConceptBaseUri;

	public function __construct(
		TitleFactory $titleFactory,
		string $wikibaseConceptBaseUri,
		array $searchLanguageCodes,
		TermLanguageFallbackChain $displayFallbackChain
	) {
		parent::__construct( $searchLanguageCodes, $displayFallbackChain );
		$this->titleFactory = $titleFactory;
		$this->wikibaseConceptBaseUri = $wikibaseConceptBaseUri;
	}

	protected function getTermSearchResult(
		array $sourceData,
		Term $matchedTerm,
		string $matchedTermType,
		?Term $displayLabel,
		?Term $displayDescription
	): ?TermSearchResult {
		try {
			$id = new EntitySchemaId( $sourceData['title'] );
			$entityId = $id->getId();
		} catch ( InvalidArgumentException $e ) {
			// Can not parse entity ID - skip it
			return null;
		}
		$title = $this->titleFactory->newFromText( $entityId, NS_ENTITYSCHEMA_JSON );
		if ( !$title ) {
			return null;
		}

		return new TermSearchResult(
			$matchedTerm,
			$matchedTermType,
			null,
			$displayLabel,
			$displayDescription,
			[
				'id' => $entityId,
				'title' => $title->getFullText(),
				'pageid' => $title->getId(),
				'url' => $title->getFullURL(),
				ConceptUriSearchHelper::CONCEPTURI_META_DATA_KEY => $this->wikibaseConceptBaseUri . $entityId,
			]
		);
	}

}
