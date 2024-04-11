<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\Hooks;

use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\Wikibase\Formatters\EntitySchemaFormatter;
use EntitySchema\Wikibase\Rdf\EntitySchemaRdfBuilder;
use EntitySchema\Wikibase\Validators\EntitySchemaExistsValidator;
use MediaWiki\Config\Config;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Title\TitleFactory;
use ValueFormatters\FormatterOptions;
use Wikibase\DataAccess\DatabaseEntitySource;
use Wikibase\Lib\LanguageNameLookupFactory;
use Wikibase\Repo\Rdf\RdfVocabulary;
use Wikibase\Repo\Rdf\ValueSnakRdfBuilder;
use Wikibase\Repo\Validators\DataValueValidator;
use Wikibase\Repo\Validators\RegexValidator;
use Wikibase\Repo\WikibaseRepo;

/**
 * @license GPL-2.0-or-later
 */
class WikibaseDataTypesHandler {

	private LinkRenderer $linkRenderer;
	public Config $settings;
	private EntitySchemaExistsValidator $entitySchemaExistsValidator;
	private LanguageNameLookupFactory $languageNameLookupFactory;
	private DatabaseEntitySource $localEntitySource;
	private TitleFactory $titleFactory;
	private LabelLookup $labelLookup;

	public function __construct(
		LinkRenderer $linkRenderer,
		Config $settings,
		TitleFactory $titleFactory,
		LanguageNameLookupFactory $languageNameLookupFactory,
		DatabaseEntitySource $localEntitySource,
		EntitySchemaExistsValidator $entitySchemaExistsValidator,
		LabelLookup $labelLookup
	) {
		$this->linkRenderer = $linkRenderer;
		$this->settings = $settings;
		$this->entitySchemaExistsValidator = $entitySchemaExistsValidator;
		$this->languageNameLookupFactory = $languageNameLookupFactory;
		$this->localEntitySource = $localEntitySource;
		$this->titleFactory = $titleFactory;
		$this->labelLookup = $labelLookup;
	}

	public function onWikibaseRepoDataTypes( array &$dataTypeDefinitions ): void {
		if ( !$this->settings->get( 'EntitySchemaEnableDatatype' ) ) {
			return;
		}
		$dataTypeDefinitions['PT:entity-schema'] = [
			'value-type' => 'string',
			'formatter-factory-callback' => function ( $format, FormatterOptions $options ) {
				return new EntitySchemaFormatter(
					$format,
					$options,
					$this->linkRenderer,
					$this->labelLookup,
					$this->titleFactory,
					$this->languageNameLookupFactory
				);
			},
			'validator-factory-callback' => function (): array {
				$validators = WikibaseRepo::getDefaultValidatorBuilders()->buildStringValidators( 11 );
				$validators[] = new DataValueValidator( new RegexValidator(
					EntitySchemaId::PATTERN,
					false,
					'illegal-entity-schema-title'
				) );
				$validators[] = $this->entitySchemaExistsValidator;
				return $validators;
			},
			'rdf-builder-factory-callback' => function (
				$flags,
				RdfVocabulary $vocab
			): ValueSnakRdfBuilder {
				return new EntitySchemaRdfBuilder(
					$vocab,
					$this->localEntitySource->getConceptBaseUri()
				);
			},
		];
	}
}
