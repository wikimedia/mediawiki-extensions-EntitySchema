<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Hooks;

use MediaWiki\Html\Html;
use MediaWiki\Page\Hook\BeforeDisplayNoArticleTextHook;

/**
 * Handler for the BeforeDisplayNoArticleText hook called by Article.
 * We implement this solely to replace the standard message that
 * is shown when a Schema does not exist.
 * @license GPL-2.0-or-later
 */
class BeforeDisplayNoArticleTextHookHandler implements BeforeDisplayNoArticleTextHook {

	private bool $entitySchemaIsRepo;

	public function __construct( bool $entitySchemaIsRepo ) {
		$this->entitySchemaIsRepo = $entitySchemaIsRepo;
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforeDisplayNoArticleText( $article ) {
		if ( !$this->entitySchemaIsRepo ) {
			return true;
		}

		if ( $article->getTitle()->getNamespace() !== NS_ENTITYSCHEMA_JSON ) {
			return true;
		}

		$context = $article->getContext();
		$dir = $context->getLanguage()->getDir();
		$lang = $context->getLanguage()->getHtmlCode();

		$outputPage = $context->getOutput();

		$outputPage->wrapWikiMsg(
			Html::element( 'div', [
				'class' => "noarticletext mw-content-$dir",
				'dir' => $dir,
				'lang' => $lang,
			], '$1' ),
			'entityschema-noschema'
		);

		return false;
	}
}
