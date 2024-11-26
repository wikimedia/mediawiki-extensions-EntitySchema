<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Hooks;

use ConfigFactory;
use EntitySchema\MediaWiki\Content\EntitySchemaContentHandler;
use MediaWiki\Content\Hook\ContentHandlerForModelIDHook;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Registration\ExtensionRegistry;
use Wikibase\Search\Elastic\Fields\DescriptionsProviderFieldDefinitions;
use Wikibase\Search\Elastic\Fields\LabelsProviderFieldDefinitions;

/**
 * @license GPL-2.0-or-later
 */
class ContentHandlerForModelIDHookHandler implements ContentHandlerForModelIDHook {

	private ConfigFactory $configFactory;
	private LanguageNameUtils $languageNameUtils;
	private bool $entitySchemaIsRepo;

	public function __construct(
		ConfigFactory $configFactory,
		LanguageNameUtils $languageNameUtils,
		bool $entitySchemaIsRepo
	) {
		$this->configFactory = $configFactory;
		$this->languageNameUtils = $languageNameUtils;
		$this->entitySchemaIsRepo = $entitySchemaIsRepo;
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
		$handler = new EntitySchemaContentHandler( $modelName, $labelsFieldDefinitions, $descriptionsFieldDefinitions );
	}

}
