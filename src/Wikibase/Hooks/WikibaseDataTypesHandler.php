<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\Hooks;

use Config;
use EntitySchema\Domain\Model\SchemaId;
use EntitySchema\Wikibase\Formatters\EntitySchemaFormatter;
use EntitySchema\Wikibase\Rdf\EntitySchemaRdfBuilder;
use EntitySchema\Wikibase\Validators\EntitySchemaExistsValidator;
use MediaWiki\Linker\LinkRenderer;
use Wikibase\DataAccess\DatabaseEntitySource;
use Wikibase\Repo\Rdf\RdfVocabulary;
use Wikibase\Repo\Rdf\ValueSnakRdfBuilder;
use Wikibase\Repo\ValidatorBuilders;
use Wikibase\Repo\Validators\DataValueValidator;
use Wikibase\Repo\Validators\RegexValidator;

/**
 * @license GPL-2.0-or-later
 */
class WikibaseDataTypesHandler {

	private LinkRenderer $linkRenderer;
	public Config $settings;
	private EntitySchemaExistsValidator $entitySchemaExistsValidator;
	private ValidatorBuilders $validatorBuilders;
	private DatabaseEntitySource $localEntitySource;

	public function __construct(
		LinkRenderer $linkRenderer,
		Config $settings,
		ValidatorBuilders $validatorBuilders,
		DatabaseEntitySource $localEntitySource,
		EntitySchemaExistsValidator $entitySchemaExistsValidator
	) {
		$this->linkRenderer = $linkRenderer;
		$this->settings = $settings;
		$this->entitySchemaExistsValidator = $entitySchemaExistsValidator;
		$this->validatorBuilders = $validatorBuilders;
		$this->localEntitySource = $localEntitySource;
	}

	public function onWikibaseRepoDataTypes( array &$dataTypeDefinitions ): void {
		if ( !$this->settings->get( 'EntitySchemaEnableDatatype' ) ) {
			return;
		}
		$dataTypeDefinitions['PT:entity-schema'] = [
			'value-type' => 'string',
			'formatter-factory-callback' => function ( $format ) {
				return new EntitySchemaFormatter(
					$format,
					$this->linkRenderer
				);
			},
			'validator-factory-callback' => function (): array {
				$validators = $this->validatorBuilders->buildStringValidators( 11 );
				$validators[] = new DataValueValidator( new RegexValidator(
					SchemaId::PATTERN,
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
