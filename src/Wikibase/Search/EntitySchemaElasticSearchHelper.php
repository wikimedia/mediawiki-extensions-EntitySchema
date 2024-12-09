<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\Search;

use CirrusSearch\Search\SearchContext;
use Elastica\Query\AbstractQuery;
use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use InvalidArgumentException;
use MediaWiki\Title\TitleFactory;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Repo\Api\EntitySearchException;
use Wikibase\Repo\Api\EntitySearchHelper;
use Wikibase\Search\Elastic\EntitySearchElastic;
use Wikibase\Search\Elastic\Query\LabelsCompletionQuery;
use Wikibase\Search\Elastic\WikibasePrefixSearcher;

/**
 * @license GPL-2.0-or-later
 */
class EntitySchemaElasticSearchHelper implements EntitySearchHelper {

	private TitleFactory $titleFactory;
	private LanguageFallbackChainFactory $languageFallbackChainFactory;
	private string $wikibaseConceptBaseUri;
	private string $userLanguageCode;

	public function __construct(
		TitleFactory $titleFactory,
		LanguageFallbackChainFactory $languageFallbackChainFactory,
		string $wikibaseConceptBaseUri,
		string $userLanguageCode
	) {
		$this->titleFactory = $titleFactory;
		$this->languageFallbackChainFactory = $languageFallbackChainFactory;
		$this->wikibaseConceptBaseUri = $wikibaseConceptBaseUri;
		$this->userLanguageCode = $userLanguageCode;
	}

	/**
	 * Produce ES query that matches the arguments.
	 *
	 * @param string $text
	 * @param string $languageCode
	 * @param bool $strictLanguage
	 * @param SearchContext $context
	 *
	 * @return AbstractQuery
	 */
	protected function getElasticSearchQuery(
		$text,
		$languageCode,
		$strictLanguage,
		SearchContext $context
	) {
		$context->setOriginalSearchTerm( $text );
		$profile = LabelsCompletionQuery::loadProfile(
			$context->getConfig()->getProfileService(),
			$this->languageFallbackChainFactory,
			EntitySearchElastic::WIKIBASE_PREFIX_QUERY_BUILDER,
			$context->getProfileContext(),
			$context->getProfileContextParams(),
			$languageCode
		);
		$query = LabelsCompletionQuery::build(
			$text,
			$profile,
			EntitySchemaContent::CONTENT_MODEL_ID,
			$languageCode,
			$strictLanguage,
			static function ( string $text ): ?string {
				try {
					$id = new EntitySchemaId( $text );
				} catch ( InvalidArgumentException $e ) {
					return null;
				}
				return $id->getId();
			}
		);
		return $query;
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
		if ( $entityType !== EntitySchemaSearchHelperFactory::ENTITY_TYPE ) {
			return [];
		}
		$profileContext ??= EntitySearchElastic::CONTEXT_WIKIBASE_PREFIX;
		$searcher = new WikibasePrefixSearcher( 0, $limit );
		$searcher->getSearchContext()->setProfileContext(
			$profileContext,
			[ 'language' => $languageCode ] );
		$query = $this->getElasticSearchQuery( $text, $languageCode, $strictLanguage,
				$searcher->getSearchContext() );

		$searcher->setResultsType( new ESElasticTermResult(
			$this->titleFactory,
			$this->wikibaseConceptBaseUri,
			$query instanceof LabelsCompletionQuery ? $query->getSearchLanguageCodes() : [],
			$this->languageFallbackChainFactory->newFromLanguageCode( $this->userLanguageCode )
		) );

		$result = $searcher->performSearch( $query );

		if ( $result->isOK() ) {
			return $result->getValue();
		} else {
			throw new EntitySearchException( $result );
		}
	}

}
