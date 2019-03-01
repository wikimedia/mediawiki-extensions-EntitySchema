<?php

namespace Wikibase\Schema\MediaWiki\Content;

use Action;
use Article;
use IContextSource;
use JsonContentHandler;
use Page;
use RequestContext;
use SlotDiffRenderer;
use Wikibase\Schema\MediaWiki\Actions\RestoreViewAction;
use Wikibase\Schema\MediaWiki\Actions\RestoreSubmitAction;
use Wikibase\Schema\MediaWiki\Actions\UndoSubmitAction;
use Wikibase\Schema\MediaWiki\Actions\UndoViewAction;
use Wikibase\Schema\MediaWiki\SchemaEditAction;
use Wikibase\Schema\MediaWiki\SchemaSubmitAction;
use WikiPage;

/**
 * Content handler for the Wikibase Schema content
 */
class WikibaseSchemaContentHandler extends JsonContentHandler {

	public function __construct( $modelId = WikibaseSchemaContent::CONTENT_MODEL_ID ) {
		parent::__construct( $modelId );
	}

	protected function getContentClass() {
		return WikibaseSchemaContent::class;
	}

	protected function getSlotDiffRendererInternal( IContextSource $context ): SlotDiffRenderer {
		return new WikibaseSchemaSlotDiffRenderer( $context );
	}

	public function getActionOverrides() {
		return [
			'edit' => function( Page $page, IContextSource $context = null ) {
				if ( $context === null ) {
					$context = RequestContext::getMain();
				}

				/** @var Article|WikiPage $page */
				// @phan-suppress-next-line PhanUndeclaredMethod
				if ( $page->getRevision() === null ) {
					return Action::factory( 'view', $page, $context );
				}

				$req = $context->getRequest();

				if (
					$req->getCheck( 'undo' )
					|| $req->getCheck( 'undoafter' )
				) {
					return new UndoViewAction(
						$page,
						new WikibaseSchemaSlotDiffRenderer( $context ),
						$context
					);
				}

				if ( $req->getCheck( 'restore' ) ) {
					return new RestoreViewAction(
						$page,
						new WikibaseSchemaSlotDiffRenderer( $context ),
						$context
					);
				}

				// TODo: check redirect?
				// !$page->isRedirect()

				return new SchemaEditAction( $page, $context );
			},
			'submit' => function( Page $page, IContextSource $context = null ) {
				if ( $context === null ) {
					$context = RequestContext::getMain();
				}

				$req = $context->getRequest();

				if (
					$req->getCheck( 'undo' )
					|| $req->getCheck( 'undoafter' )
				) {
					return new UndoSubmitAction( $page, $context );
				}

				if ( $req->getCheck( 'restore' ) ) {
					return new RestoreSubmitAction( $page, $context );
				}

				return SchemaSubmitAction::class;
			},
		];
	}

	public function supportsDirectApiEditing() {
		return false;
	}

}
