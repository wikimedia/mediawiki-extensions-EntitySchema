<?php

namespace Wikibase\Schema\MediaWiki\Content;

use Html;
use JsonContent;
use MediaWiki\MediaWikiServices;
use ParserOptions;
use ParserOutput;
use SpecialPage;
use Title;
use Wikibase\Schema\Services\SchemaDispatcher\NameBadge;
use Wikibase\Schema\Services\SchemaDispatcher\SchemaDispatcher;

/**
 * Represents the content of a Wikibase Schema page
 */
class WikibaseSchemaContent extends JsonContent {

	const CONTENT_MODEL_ID = 'WikibaseSchema';

	public function __construct( $text, $modelId = self::CONTENT_MODEL_ID ) {
		parent::__construct( $text, $modelId );
	}

	protected function fillParserOutput(
		Title $title,
		$revId,
		ParserOptions $options,
		$generateHtml,
		/** @noinspection ReferencingObjectsInspection */
		ParserOutput &$output
	) {

		if ( $generateHtml && $this->isValid() ) {
			$output->addModuleStyles( 'ext.WikibaseSchema.view' );
			$languageCode = $options->getUserLang(); // TODO which language?
			$schemaData = ( new SchemaDispatcher() )
				->getFullViewSchemaData( $this->getText(), $languageCode );
			$output->setText(
				$this->renderNameBadges( $title, $schemaData->nameBadges ) .
				$this->renderSchemaSection( $title, $schemaData->schemaText )
			);
		} else {
			$output->setText( '' );
		}
	}

	private function renderNameBadges( Title $title, array $nameBadges ) {
		$html = '';
		foreach ( $nameBadges as $langCode => $nameBadge ) {
			if ( $html ) {
				$html .= "\n";
			}
			$html .= $this->renderNameBadge( $nameBadge ) .
				$this->renderNameBadgeEditLink( $title, $langCode );
		}
		return $html;
	}

	private function renderNameBadge( NameBadge $nameBadge ) {
		return Html::element(
				'h1',
				[
					'id' => 'wbschema-title-label',
				],
				$nameBadge->label
			) .
			Html::element(
				'abstract',
				[
					'id' => 'wbschema-heading-description',
				],
				$nameBadge->description
			) .
			Html::element(
				'p',
				[
					'id' => 'wbschema-heading-aliases',
				],
				implode( ' | ', $nameBadge->aliases )
			);
	}

	private function renderNameBadgeEditLink( Title $title, $langCode ) {
		$specialPageTitleValue = SpecialPage::getTitleValueFor(
			'SetSchemaLabelDescriptionAliases',
			$title->getText() . '/' . $langCode
		);

		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		return Html::rawElement(
			'div',
			[
				'class' => 'wbschema-edit-button',
			],
			$linkRenderer->makeLink(
				$specialPageTitleValue,
				wfMessage( 'wikibaseschema-edit' )->inContentLanguage(),
				[ 'class' => 'edit-icon' ]
			)
		);
	}

	private function renderSchemaSection( Title $title, $schemaText ) {
		return Html::rawElement( 'div', [
			'id' => 'wbschema-schema-view-section',
			'class' => 'wbschema-section',
			],
			$this->renderSchemaText( $schemaText ) .
			$this->renderSchemaEditLink( $title )
		);
	}

	private function renderSchemaText( $schemaText ) {
		return Html::element(
				'pre',
				[
					'id' => 'wbschema-schema-text',
					'class' => 'wbschema-schema-text',
				],
				$schemaText
			);
	}

	private function renderSchemaEditLink( Title $title ) {
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		return Html::rawElement(
			'div',
			[
				'id' => 'wbschema-edit-schema-text',
				'class' => 'wbschema-edit-button',
			],
			$linkRenderer->makeLink(
				$title,
				wfMessage( 'wikibaseschema-edit' )->inContentLanguage(),
				[ 'class' => 'edit-icon' ],
				[ 'action' => 'edit' ]
			)
		);
	}

}
