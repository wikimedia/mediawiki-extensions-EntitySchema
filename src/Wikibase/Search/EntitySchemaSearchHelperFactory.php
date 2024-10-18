<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\Search;

use EntitySchema\DataAccess\DescriptionLookup;
use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\Wikibase\DataValues\EntitySchemaValue;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Context\IContextSource;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\TitleFactory;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Repo\Api\CombinedEntitySearchHelper;
use Wikibase\Repo\Api\EntitySearchHelper;
use Wikibase\Search\Elastic\WikibaseSearchConfig;

/**
 * @license GPL-2.0-or-later
 */
class EntitySchemaSearchHelperFactory {

	/** @var string Not a real entity type, but registered under this name in wbsearchentities. */
	public const ENTITY_TYPE = EntitySchemaValue::TYPE;

	private ConfigFactory $configFactory;
	private TitleFactory $titleFactory;
	private WikiPageFactory $wikiPageFactory;
	private LanguageFallbackChainFactory $languageFallbackChainFactory;
	private string $wikibaseConceptBaseUri;
	private DescriptionLookup $descriptionLookup;
	private LabelLookup $labelLookup;

	public function __construct(
		ConfigFactory $configFactory,
		TitleFactory $titleFactory,
		WikiPageFactory $wikiPageFactory,
		LanguageFallbackChainFactory $languageFallbackChainFactory,
		string $wikibaseConceptBaseUri,
		DescriptionLookup $descriptionLookup,
		LabelLookup $labelLookup
	) {
		$this->configFactory = $configFactory;
		$this->titleFactory = $titleFactory;
		$this->languageFallbackChainFactory = $languageFallbackChainFactory;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->wikibaseConceptBaseUri = $wikibaseConceptBaseUri;
		$this->descriptionLookup = $descriptionLookup;
		$this->labelLookup = $labelLookup;
	}

	public function newForContext( IContextSource $context ): EntitySearchHelper {
		$idHelper = new EntitySchemaIdSearchHelper(
			$this->titleFactory,
			$this->wikiPageFactory,
			$this->wikibaseConceptBaseUri,
			$this->descriptionLookup,
			$this->labelLookup,
			$context->getLanguage()->getCode()
		);

		if ( $this->isWBCSEnabled() ) {
			$elasticHelper = new EntitySchemaElasticSearchHelper(
				$this->titleFactory,
				$this->languageFallbackChainFactory,
				$this->wikibaseConceptBaseUri,
				$context->getLanguage()->getCode()
			);
			return new CombinedEntitySearchHelper( [ $idHelper, $elasticHelper ] );
		} else {
			return $idHelper;
		}
	}

	private function getWBCSConfig(): ?WikibaseSearchConfig {
		if ( !in_array( 'WikibaseCirrusSearch', $this->configFactory->getConfigNames(), true ) ) {
			return null;
		}
		// @phan-suppress-next-line PhanTypeMismatchReturn
		return $this->configFactory->makeConfig( 'WikibaseCirrusSearch' );
	}

	private function isWBCSEnabled(): bool {
		$config = $this->getWBCSConfig();
		if ( $config === null ) {
			return false;
		}
		return $config->enabled();
	}

}
