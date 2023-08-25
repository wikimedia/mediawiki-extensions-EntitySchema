<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Content;

use Action;
use Article;
use Content;
use EntitySchema\DataAccess\EntitySchemaEncoder;
use EntitySchema\MediaWiki\Actions\EntitySchemaEditAction;
use EntitySchema\MediaWiki\Actions\EntitySchemaSubmitAction;
use EntitySchema\MediaWiki\Actions\RestoreSubmitAction;
use EntitySchema\MediaWiki\Actions\RestoreViewAction;
use EntitySchema\MediaWiki\Actions\UndoSubmitAction;
use EntitySchema\MediaWiki\Actions\UndoViewAction;
use EntitySchema\MediaWiki\UndoHandler;
use EntitySchema\Presentation\InputValidator;
use EntitySchema\Services\Converter\EntitySchemaConverter;
use IContextSource;
use JsonContentHandler;
use Language;
use LogicException;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ParserOutput;
use RequestContext;
use Wikibase\Repo\WikibaseRepo;

/**
 * Content handler for the EntitySchema content
 *
 * @license GPL-2.0-or-later
 */
class EntitySchemaContentHandler extends JsonContentHandler {

	private IContentHandlerFactory $contentHandlerFactory;

	public function __construct(
		string $modelId,
		IContentHandlerFactory $contentHandlerFactory
	) {
		// $modelId is typically EntitySchemaContent::CONTENT_MODEL_ID
		parent::__construct( $modelId );
		$this->contentHandlerFactory = $contentHandlerFactory;
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
	public function getPageViewLanguage( Title $title, Content $content = null ): Language {
		$context = RequestContext::getMain();
		return $context->getLanguage();
	}

	public function canBeUsedOn( Title $title ): bool {
		return $title->inNamespace( NS_ENTITYSCHEMA_JSON ) && parent::canBeUsedOn( $title );
	}

	public function getActionOverrides(): array {
		return [
			'edit' => function ( Article $article, IContextSource $context ) {
				return $this->getActionOverridesEdit( $article, $context );
			},
			'submit' => function ( Article $article, IContextSource $context ) {
				return $this->getActionOverridesSubmit( $article, $context );
			},
		];
	}

	/**
	 * @param Article $article
	 * @param IContextSource $context
	 * @return Action|callable
	 */
	private function getActionOverridesEdit(
		Article $article,
		IContextSource $context
	) {
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
				$this->getSlotDiffRendererWithOptions( $context )
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
		$repoSettings = WikibaseRepo::getSettings();
		return new EntitySchemaEditAction(
			$article,
			$context,
			new InputValidator(
				$context,
				MediaWikiServices::getInstance()->getMainConfig(),
				MediaWikiServices::getInstance()->getLanguageNameUtils()
			),
			$wgEditSubmitButtonLabelPublish,
			MediaWikiServices::getInstance()->getUserOptionsLookup(),
			$repoSettings->getSetting( 'dataRightsUrl' ),
			$repoSettings->getSetting( 'dataRightsText' )
		);
	}

	/**
	 * @param Article $article
	 * @param IContextSource $context
	 * @return RestoreSubmitAction|UndoSubmitAction|string
	 */
	private function getActionOverridesSubmit(
		Article $article,
		IContextSource $context
	) {
		$req = $context->getRequest();

		if (
			$req->getCheck( 'undo' )
			|| $req->getCheck( 'undoafter' )
		) {
			return new UndoSubmitAction( $article, $context );
		}

		if ( $req->getCheck( 'restore' ) ) {
			return new RestoreSubmitAction( $article, $context );
		}

		return EntitySchemaSubmitAction::class;
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
			$renderer = new EntitySchemaSlotViewRenderer( $languageCode );
			$renderer->fillParserOutput(
				( new EntitySchemaConverter() )
					->getFullViewSchemaData( $content->getText(), [ $languageCode ] ),
				$cpoParams->getPage(),
				$parserOutput
			);
		} else {
			$parserOutput->setText( '' );
		}
	}

}
