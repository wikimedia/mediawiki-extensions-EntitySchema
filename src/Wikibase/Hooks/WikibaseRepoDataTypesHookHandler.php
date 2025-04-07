<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\Hooks;

use EntitySchema\DataAccess\SchemaDataResolvingLabelLookup;
use EntitySchema\Wikibase\DataValues\EntitySchemaValue;
use EntitySchema\Wikibase\DataValues\EntitySchemaValueParser;
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
use Wikibase\Repo\Validators\TypeValidator;
use Wikimedia\Assert\Assert;

/**
 * @license GPL-2.0-or-later
 */
class WikibaseRepoDataTypesHookHandler {

	private bool $entitySchemaIsRepo;
	private LinkRenderer $linkRenderer;
	private ?EntitySchemaExistsValidator $entitySchemaExistsValidator;
	private ?LanguageNameLookupFactory $languageNameLookupFactory;
	private ?DatabaseEntitySource $localEntitySource;
	private TitleFactory $titleFactory;
	private ?SchemaDataResolvingLabelLookup $labelLookup;
	private Config $config;

	public function __construct(
		LinkRenderer $linkRenderer,
		Config $config,
		TitleFactory $titleFactory,
		bool $entitySchemaIsRepo,
		?LanguageNameLookupFactory $languageNameLookupFactory,
		?DatabaseEntitySource $localEntitySource,
		?EntitySchemaExistsValidator $entitySchemaExistsValidator,
		?SchemaDataResolvingLabelLookup $labelLookup
	) {
		$this->linkRenderer = $linkRenderer;
		$this->entitySchemaIsRepo = $entitySchemaIsRepo;
		$this->entitySchemaExistsValidator = $entitySchemaExistsValidator;
		$this->languageNameLookupFactory = $languageNameLookupFactory;
		$this->localEntitySource = $localEntitySource;
		$this->titleFactory = $titleFactory;
		$this->labelLookup = $labelLookup;
		$this->config = $config;
		if ( $entitySchemaIsRepo ) {
			Assert::parameterType(
				LanguageNameLookupFactory::class,
				$languageNameLookupFactory,
				'$languageNameLookupFactory'
			);
			Assert::parameterType( DatabaseEntitySource::class, $localEntitySource, '$localEntitySource' );
			Assert::parameterType(
				EntitySchemaExistsValidator::class,
				$entitySchemaExistsValidator,
				'$entitySchemaExistsValidator'
			);
			Assert::parameterType( SchemaDataResolvingLabelLookup::class, $labelLookup, '$labelLookup' );
		}
	}

	public function onWikibaseRepoDataTypes( array &$dataTypeDefinitions ): void {
		if ( !$this->entitySchemaIsRepo ) {
			return;
		}
		$dataTypeDefinitions['PT:entity-schema'] = [
			'value-type' => 'wikibase-entityid',
			'expert-module' => 'ext.EntitySchema.experts.EntitySchema',
			'formatter-factory-callback' => function ( $format, FormatterOptions $options ) {
				return new EntitySchemaFormatter(
					$format,
					$options,
					$this->linkRenderer,
					// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
					$this->labelLookup,
					$this->titleFactory,
					// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
					$this->languageNameLookupFactory
				);
			},
			'validator-factory-callback' => function (): array {
				return [
					new TypeValidator( EntitySchemaValue::class ),
					$this->entitySchemaExistsValidator,
				];
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
			'parser-factory-callback' => static fn () => new EntitySchemaValueParser(),
			'deserializer-builder' => EntitySchemaValue::class,
			'search-index-data-formatter-callback' => static function ( EntitySchemaValue $value ): string {
				return $value->getSchemaId();
			},
		];

		if ( $this->config->get( 'EntitySchemaTmpFixRdfUri' ) ) {
			$dataTypeDefinitions['PT:entity-schema']['rdf-uri'] = 'http://wikiba.se/ontology#WikibaseEntitySchema';
		}
	}
}
