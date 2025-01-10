<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Content;

use Action;
use Article;
use CirrusSearch\CirrusSearch;
use EntitySchema\DataAccess\EntitySchemaEncoder;
use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\MediaWiki\Actions\EntitySchemaEditAction;
use EntitySchema\MediaWiki\Actions\EntitySchemaSubmitAction;
use EntitySchema\MediaWiki\Actions\RestoreSubmitAction;
use EntitySchema\MediaWiki\Actions\RestoreViewAction;
use EntitySchema\MediaWiki\Actions\UndoSubmitAction;
use EntitySchema\MediaWiki\Actions\UndoViewAction;
use EntitySchema\MediaWiki\UndoHandler;
use EntitySchema\Presentation\InputValidator;
use EntitySchema\Services\Converter\EntitySchemaConverter;
use LogicException;
use MediaWiki\Config\Config;
use MediaWiki\Content\Content;
use MediaWiki\Content\JsonContentHandler;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Language\Language;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\TempUser\TempUserConfig;
use ReadOnlyMode;
use SearchEngine;
use SearchIndexField;
use Wikibase\Lib\LanguageNameLookupFactory;
use Wikibase\Lib\SettingsArray;
use Wikibase\Search\Elastic\Fields\DescriptionsProviderFieldDefinitions;
use Wikibase\Search\Elastic\Fields\LabelsProviderFieldDefinitions;
use WikiPage;

/**
 * Content handler for the EntitySchema content
 *
 * @license GPL-2.0-or-later
 */
class EntitySchemaContentHandler extends JsonContentHandler {

	/**
	 * @var LabelsProviderFieldDefinitions|null The search field definitions for labels,
	 * or null if WikibaseCirrusSearch is not loaded and no search fields are available.
	 */
	private ?LabelsProviderFieldDefinitions $labelsFieldDefinitions;

	/**
	 * @var DescriptionsProviderFieldDefinitions|null The search field definitions for descriptions,
	 * or null if WikibaseCirrusSearch is not loaded and no search fields are available.
	 */
	private ?DescriptionsProviderFieldDefinitions $descriptionsFieldDefinitions;

	private LanguageNameLookupFactory $languageNameLookupFactory;

	private LabelLookup $labelLookup;

	public function __construct(
		string $modelId,
		LabelLookup $labelLookup,
		LanguageNameLookupFactory $languageNameLookupFactory,
		?LabelsProviderFieldDefinitions $labelsFieldDefinitions,
		?DescriptionsProviderFieldDefinitions $descriptionsFieldDefinitions
	) {
		// $modelId is typically EntitySchemaContent::CONTENT_MODEL_ID
		parent::__construct( $modelId );
		$this->labelLookup = $labelLookup;
		$this->languageNameLookupFactory = $languageNameLookupFactory;
		$this->labelsFieldDefinitions = $labelsFieldDefinitions;
		$this->descriptionsFieldDefinitions = $descriptionsFieldDefinitions;
	}

	protected function getContentClass(): string {
		return EntitySchemaContent::class;
	}

	protected function getSlotDiffRendererWithOptions(
		IContextSource $context,
		$options = []
	): EntitySchemaSlotDiffRenderer {
		return new EntitySchemaSlotDiffRenderer(
			$context,
			$this->createTextSlotDiffRenderer( $options )
		);
	}

	/**
	 * @see ContentHandler::getPageViewLanguage
	 *
	 * This implementation returns the user language, because Schemas get rendered in
	 * the user's language. The PageContentLanguage hook is bypassed.
	 *
	 * @param Title $title (unused) the page to determine the language for.
	 * @param Content|null $content (unused) the page's content
	 *
	 * @return Language The page's language
	 */
	public function getPageViewLanguage( Title $title, ?Content $content = null ): Language {
		$context = RequestContext::getMain();
		return $context->getLanguage();
	}

	public function canBeUsedOn( Title $title ): bool {
		return $title->inNamespace( NS_ENTITYSCHEMA_JSON ) && parent::canBeUsedOn( $title );
	}

	public function getActionOverrides(): array {
		return [
			'edit' => [
				'factory' => function (
					Article $article,
					IContextSource $context,
					RevisionStore $revisionStore,
					Config $mainConfig,
					LanguageNameUtils $languageNameUtils,
					UserOptionsLookup $userOptionsLookup,
					SettingsArray $repoSettings,
					TempUserConfig $tempUserConfig
				) {
					return $this->getActionOverridesEdit(
						$article,
						$context,
						$revisionStore,
						$mainConfig,
						$languageNameUtils,
						$userOptionsLookup,
						$repoSettings,
						$tempUserConfig
					);
				},
				'services' => [
					'RevisionStore',
					'MainConfig',
					'LanguageNameUtils',
					'UserOptionsLookup',
					'WikibaseRepo.Settings',
					'TempUserConfig',
				],
			],
			'submit' => [
				'factory' => function (
					Article $article,
					IContextSource $context,
					ReadOnlyMode $readOnlyMode,
					RevisionStore $revisionStore,
					PermissionManager $permissionManager,
					Config $mainConfig,
					LanguageNameUtils $languageNameUtils,
					UserOptionsLookup $userOptionsLookup,
					SettingsArray $repoSettings,
					TempUserConfig $tempUserConfig
				) {
					return $this->getActionOverridesSubmit(
						$article,
						$context,
						$readOnlyMode,
						$revisionStore,
						$permissionManager,
						$mainConfig,
						$languageNameUtils,
						$userOptionsLookup,
						$repoSettings,
						$tempUserConfig
					);
				},
				'services' => [
					'ReadOnlyMode',
					'RevisionStore',
					'PermissionManager',
					'MainConfig',
					'LanguageNameUtils',
					'UserOptionsLookup',
					'WikibaseRepo.Settings',
					'TempUserConfig',
				],
			],
		];
	}

	private function getActionOverridesEdit(
		Article $article,
		IContextSource $context,
		RevisionStore $revisionStore,
		Config $mainConfig,
		LanguageNameUtils $languageNameUtils,
		UserOptionsLookup $userOptionsLookup,
		SettingsArray $repoSettings,
		TempUserConfig $tempUserConfig
	): Action {
		global $wgEditSubmitButtonLabelPublish;

		if ( $article->getPage()->getRevisionRecord() === null ) {
			return Action::factory( 'view', $article, $context );
		}

		$req = $context->getRequest();

		if (
			$req->getCheck( 'undo' )
			|| $req->getCheck( 'undoafter' )
		) {
			return new UndoViewAction(
				$article,
				$context,
				$this->getSlotDiffRendererWithOptions( $context ),
				$revisionStore
			);
		}

		if ( $req->getCheck( 'restore' ) ) {
			return new RestoreViewAction(
				$article,
				$context,
				$this->getSlotDiffRendererWithOptions( $context )
			);
		}

		// TODo: check redirect?
		// !$article->isRedirect()
		return new EntitySchemaEditAction(
			$article,
			$context,
			new InputValidator( $context, $mainConfig, $languageNameUtils ),
			$wgEditSubmitButtonLabelPublish,
			$userOptionsLookup,
			$repoSettings->getSetting( 'dataRightsUrl' ),
			$repoSettings->getSetting( 'dataRightsText' ),
			$tempUserConfig
		);
	}

	private function getActionOverridesSubmit(
		Article $article,
		IContextSource $context,
		ReadOnlyMode $readOnlyMode,
		RevisionStore $revisionStore,
		PermissionManager $permissionManager,
		Config $mainConfig,
		LanguageNameUtils $languageNameUtils,
		UserOptionsLookup $userOptionsLookup,
		SettingsArray $repoSettings,
		TempUserConfig $tempUserConfig
	): Action {
		global $wgEditSubmitButtonLabelPublish;
		$req = $context->getRequest();

		if (
			$req->getCheck( 'undo' )
			|| $req->getCheck( 'undoafter' )
		) {
			return new UndoSubmitAction(
				$article,
				$context,
				$readOnlyMode,
				$permissionManager,
				$revisionStore
			);
		}

		if ( $req->getCheck( 'restore' ) ) {
			return new RestoreSubmitAction( $article, $context );
		}

		return new EntitySchemaSubmitAction(
			$article,
			$context,
			new InputValidator( $context, $mainConfig, $languageNameUtils ),
			$wgEditSubmitButtonLabelPublish,
			$userOptionsLookup,
			$repoSettings->getSetting( 'dataRightsUrl' ),
			$repoSettings->getSetting( 'dataRightsText' ),
			$tempUserConfig
		);
	}

	public function supportsDirectApiEditing(): bool {
		return false;
	}

	/**
	 * Get the Content object that needs to be saved in order to undo all revisions
	 * between $undo and $undoafter. Revisions must belong to the same page,
	 * must exist and must not be deleted.
	 *
	 * @since 1.32 accepts Content objects for all parameters instead of Revision objects.
	 *  Passing Revision objects is deprecated.
	 * @since 1.37 only accepts Content objects
	 *
	 * @param Content $baseContent The current text
	 * @param Content $undoFromContent The content of the revision to undo
	 * @param Content $undoToContent Must be from an earlier revision than $undo
	 * @param bool $undoIsLatest Set true if $undo is from the current revision (since 1.32)
	 *
	 * @return Content|false
	 */
	public function getUndoContent(
		Content $baseContent,
		Content $undoFromContent,
		Content $undoToContent,
		$undoIsLatest = false
	) {
		if ( $undoIsLatest ) {
			return $undoToContent;
		}

		// Make sure correct subclass
		if ( !$baseContent instanceof EntitySchemaContent ||
			!$undoFromContent instanceof EntitySchemaContent ||
			!$undoToContent instanceof EntitySchemaContent
		) {
			return false;
		}

		$undoHandler = new UndoHandler();
		try {
			$schemaId = $undoHandler->validateContentIds( $undoToContent, $undoFromContent, $baseContent );
		} catch ( LogicException $e ) {
			return false;
		}

		$diffStatus = $undoHandler->getDiffFromContents( $undoFromContent, $undoToContent );
		if ( !$diffStatus->isOK() ) {
			return false;
		}

		$patchStatus = $undoHandler->tryPatching( $diffStatus->getValue(), $baseContent );
		if ( !$patchStatus->isOK() ) {
			return false;
		}
		$patchedSchema = $patchStatus->getValue()->data;

		return new EntitySchemaContent( EntitySchemaEncoder::getPersistentRepresentation(
			$schemaId,
			$patchedSchema['labels'],
			$patchedSchema['descriptions'],
			$patchedSchema['aliases'],
			$patchedSchema['schemaText']
		) );
	}

	/**
	 * Returns true to indicate that the parser cache can be used for Schemas.
	 *
	 * @note The html representation of Schemas depends on the user language, so
	 * EntitySchemaContent::getParserOutput needs to make sure
	 * ParserOutput::recordOption( 'userlang' ) is called to split the cache by user language.
	 *
	 * @see ContentHandler::isParserCacheSupported
	 *
	 * @return bool Always true in this default implementation.
	 */
	public function isParserCacheSupported(): bool {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	protected function fillParserOutput(
		Content $content,
		ContentParseParams $cpoParams,
		ParserOutput &$parserOutput
	): void {
		'@phan-var EntitySchemaContent $content';
		$parserOptions = $cpoParams->getParserOptions();
		$generateHtml = $cpoParams->getGenerateHtml();
		if ( $generateHtml && $content->isValid() ) {
			$languageCode = $parserOptions->getUserLang();
			$renderer = new EntitySchemaSlotViewRenderer(
				$languageCode,
				$this->labelLookup,
				$this->languageNameLookupFactory
			);
			$renderer->fillParserOutput(
				( new EntitySchemaConverter() )
					->getFullViewSchemaData( $content->getText() ),
				$cpoParams->getPage(),
				$parserOutput
			);
		} else {
			$parserOutput->setText( '' );
		}
	}

	/**
	 * @param SearchEngine $engine
	 * @return SearchIndexField[] List of fields this content handler can provide.
	 */
	public function getFieldsForSearchIndex( SearchEngine $engine ): array {
		if ( $this->labelsFieldDefinitions === null || $this->descriptionsFieldDefinitions === null ) {
			if ( $engine instanceof CirrusSearch ) {
				wfLogWarning(
					'Trying to use CirrusSearch but WikibaseCirrusSearch is not loaded. ' .
					'EntitySchema search is not available; consider loading WikibaseCirrusSearch.'
				);
			}
			return [];
		} else {
			$fields = [];
			foreach ( $this->labelsFieldDefinitions->getFields() as $name => $field ) {
				$mappingField = $field->getMappingField( $engine, $name );
				if ( $mappingField !== null ) {
					$fields[$name] = $mappingField;
				}
			}
			foreach ( $this->descriptionsFieldDefinitions->getFields() as $name => $field ) {
				$mappingField = $field->getMappingField( $engine, $name );
				if ( $mappingField !== null ) {
					$fields[$name] = $mappingField;
				}
			}
			return $fields;
		}
	}

	public function getDataForSearchIndex(
		WikiPage $page,
		ParserOutput $output,
		SearchEngine $engine,
		?RevisionRecord $revision = null
	): array {
		$fieldsData = parent::getDataForSearchIndex( $page, $output, $engine, $revision );
		if ( $this->labelsFieldDefinitions === null || $this->descriptionsFieldDefinitions === null ) {
			return $fieldsData;
		}
		$content = $revision !== null ? $revision->getContent( SlotRecord::MAIN ) : $page->getContent();
		if ( $content instanceof EntitySchemaContent ) {
			$adapter = ( new EntitySchemaConverter() )
				->getSearchEntitySchemaAdapter( $content->getText() );
			foreach ( $this->labelsFieldDefinitions->getFields() as $name => $field ) {
				if ( $field !== null ) {
					$fieldsData[$name] = $field->getLabelsIndexedData( $adapter );
				}
			}
			foreach ( $this->descriptionsFieldDefinitions->getFields() as $name => $field ) {
				if ( $field !== null ) {
					$fieldsData[$name] = $field->getDescriptionsIndexedData( $adapter );
				}
			}
		}
		return $fieldsData;
	}

}
