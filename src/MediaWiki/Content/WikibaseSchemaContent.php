<?php

namespace Wikibase\Schema\MediaWiki\Content;

use Html;
use JsonContent;
use LanguageCode;
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
				->getFullViewSchemaData( $this->getText(), [ $languageCode ] );
			$output->setText(
				$this->renderNameBadges( $title, $schemaData->nameBadges, $languageCode ) .
				$this->renderSchemaSection( $title, $schemaData->schemaText )
			);
		} else {
			$output->setText( '' );
		}
	}

	private function renderNameBadges( Title $title, array $nameBadges, $languageCode ) {
		$html = Html::openElement( 'table', [ 'class' => 'wikitable' ] );
		$html .= $this->renderNameBadgeHeader( $languageCode );
		$html .= Html::openElement( 'tbody' );
		foreach ( $nameBadges as $langCode => $nameBadge ) {
			$html .= "\n";
			$html .= $this->renderNameBadge( $nameBadge, $langCode, $title->getText() );
		}
		$html .= Html::closeElement( 'tbody' );
		$html .= Html::closeElement( 'table' );
		return $html;
	}

	private function renderNameBadgeHeader( $languageCode ) {
		$tableHeaders = '';
		// message keys:
		// wikibaseschema-namebadge-header-language-code
		// wikibaseschema-namebadge-header-label
		// wikibaseschema-namebadge-header-description
		// wikibaseschema-namebadge-header-aliases
		// wikibaseschema-namebadge-header-edit
		foreach ( [ 'language-code', 'label', 'description', 'aliases', 'edit' ] as $key ) {
			$tableHeaders .= Html::element(
				'th',
				[],
				wfMessage( 'wikibaseschema-namebadge-header-' . $key )
					->inLanguage( $languageCode )
					->parse()
			);
		}

		return Html::rawElement( 'thead', [], Html::rawElement(
			'tr',
			[],
			$tableHeaders
		) );
	}

	private function renderNameBadge( NameBadge $nameBadge, $languageCode, $schemaId ) {
		$language = Html::element(
			'td',
			[],
			$languageCode
		);
		$bcp47 = LanguageCode::bcp47( $languageCode ); // 'simple' => 'en-simple' etc.
		$label = Html::element(
			'td',
			[
				'class' => 'wbschema-label',
				'lang' => $bcp47,
			],
			$nameBadge->label
		);
		$description = Html::element(
			'td',
			[
				'class' => 'wbschema-description',
				'lang' => $bcp47,
			],
			$nameBadge->description
		);
		$aliases = Html::element(
			'td',
			[
				'class' => 'wbschema-aliases',
				'lang' => $bcp47,
			],
			implode( ' | ', $nameBadge->aliases )
		);
		$editLink = $this->renderNameBadgeEditLink( $schemaId, $languageCode );
		return Html::rawElement(
			'tr',
			[],
			$language . $label . $description . $aliases . $editLink
		);
	}

	private function renderNameBadgeEditLink( $schemaId, $langCode ) {
		$specialPageTitleValue = SpecialPage::getTitleValueFor(
			'SetSchemaLabelDescriptionAliases',
			$schemaId . '/' . $langCode
		);

		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		return Html::rawElement(
			'td',
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
