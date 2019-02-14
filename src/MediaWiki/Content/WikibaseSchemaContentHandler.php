<?php

namespace Wikibase\Schema\MediaWiki\Content;

use Action;
use Article;
use IContextSource;
use JsonContentHandler;
use Page;
use SlotDiffRenderer;
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
			'edit' => function ( Page $page, IContextSource $context = null ) {
				/** @var Article|WikiPage $page */
				if ( $page->getRevision() === null ) {
					return Action::factory( 'view', $page, $context );
				}

				// TODo: check redirect?
				// !$page->isRedirect()

				return new SchemaEditAction( $page, $context );
			},
			'submit' => SchemaSubmitAction::class,
		];
	}

	public function supportsDirectApiEditing() {
		return false;
	}

}
