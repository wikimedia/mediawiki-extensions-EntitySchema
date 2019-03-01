<?php

namespace Wikibase\Schema\MediaWiki\Content;

use Html;
use JsonContent;
use MediaWiki\MediaWikiServices;
use ParserOptions;
use ParserOutput;
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
				$this->renderNameBadges( $schemaData->nameBadges ) .
				$this->renderSchemaSection( $title, $schemaData->schemaText )
			);
		} else {
			$output->setText( '' );
		}
	}

	private function renderNameBadges( array $nameBadges ) {
		$html = '';
		foreach ( $nameBadges as $nameBadge ) {
			if ( $html ) {
				$html .= "\n";
			}
			$html .= $this->renderNameBadge( $nameBadge );
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

	private function renderSchemaSection( Title $title, $schemaContent ) {
		return Html::rawElement( 'div', [
			'id' => 'wbschema-schema-view-section',
			'class' => 'wbschema-section',
			],
			$this->renderSchema( $schemaContent ) .
			$this->renderSchemaEditLink( $title )
		);
	}

	private function renderSchema( $schemaContent ) {
		return Html::element(
				'pre',
				[
					'id' => 'wbschema-schema-text',
					'class' => 'wbschema-schema-text',
				],
				$schemaContent
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
