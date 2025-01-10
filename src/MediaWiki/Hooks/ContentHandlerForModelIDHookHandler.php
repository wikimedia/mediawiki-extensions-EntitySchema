<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Hooks;

use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\MediaWiki\Content\EntitySchemaContentHandler;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Content\Hook\ContentHandlerForModelIDHook;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Registration\ExtensionRegistry;
use Wikibase\Lib\LanguageNameLookupFactory;
use Wikibase\Search\Elastic\Fields\DescriptionsProviderFieldDefinitions;
use Wikibase\Search\Elastic\Fields\LabelsProviderFieldDefinitions;
use Wikimedia\Assert\Assert;

/**
 * @license GPL-2.0-or-later
 */
class ContentHandlerForModelIDHookHandler implements ContentHandlerForModelIDHook {

	private ConfigFactory $configFactory;
	private LanguageNameUtils $languageNameUtils;
	private bool $entitySchemaIsRepo;
	private ?LanguageNameLookupFactory $languageNameLookupFactory;
	private ?LabelLookup $labelLookup;

	public function __construct(
		ConfigFactory $configFactory,
		LanguageNameUtils $languageNameUtils,
		bool $entitySchemaIsRepo,
		?LabelLookup $labelLookup,
		?LanguageNameLookupFactory $languageNameLookupFactory
	) {
		$this->configFactory = $configFactory;
		$this->languageNameUtils = $languageNameUtils;
		$this->entitySchemaIsRepo = $entitySchemaIsRepo;
		$this->labelLookup = $labelLookup;
		$this->languageNameLookupFactory = $languageNameLookupFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function onContentHandlerForModelID( $modelName, &$handler ): void {
		if ( !$this->entitySchemaIsRepo ) {
			return;
		}
		if ( $modelName !== 'EntitySchema' ) {
			return;
		}
		Assert::invariant(
			$this->labelLookup !== null &&
			$this->languageNameLookupFactory !== null,
			'Optional services LabelLookup and LanguageNameLookupFactory should be set in repo mode'
		);
		$labelsFieldDefinitions = null;
		$descriptionsFieldDefinitions = null;
		$extensionRegistry = ExtensionRegistry::getInstance(); // TODO inject (T257586)
		if ( $extensionRegistry->isLoaded( 'WikibaseCirrusSearch' ) ) {
			$languages = array_keys( $this->languageNameUtils->getLanguageNames(
				'en', LanguageNameUtils::DEFINED ) );
			$labelsFieldDefinitions = new LabelsProviderFieldDefinitions(
				$languages, $this->configFactory );
			$descriptionsFieldDefinitions = new DescriptionsProviderFieldDefinitions(
				$languages, $this->configFactory );
		}
		$handler = new EntitySchemaContentHandler(
			$modelName,
			$this->labelLookup,
			$this->languageNameLookupFactory,
			$labelsFieldDefinitions,
			$descriptionsFieldDefinitions
		);
	}

}
