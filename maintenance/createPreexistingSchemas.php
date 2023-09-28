<?php

namespace EntitySchema\Maintenance;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' )
	: __DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';
require_once $basePath . '/extensions/EntitySchema/src/Domain/Storage/IdGenerator.php';
require_once 'FixedIdGenerator.php';

use EntitySchema\DataAccess\MediaWikiPageUpdaterFactory;
use EntitySchema\DataAccess\MediaWikiRevisionSchemaInserter;
use EntitySchema\DataAccess\WatchlistUpdater;
use Maintenance;
use MediaWiki\MediaWikiServices;
use RequestContext;
use RuntimeException;
use User;

/**
 * Maintenance script for creating preexisting EntitySchemas.
 *
 * @license GPL-2.0-or-later
 */
class CreatePreexistingSchemas extends Maintenance {

	private const LABEL = 'label';
	private const DESC = 'desc';

	public function __construct() {
		parent::__construct();

		$this->addDescription(
			'Create initial EntitySchemas. Prossibly not that useful beyond wikidata.'
		);

		$this->requireExtension( 'EntitySchema' );
	}

	public function execute() {
		// "Maintenance script" is in MediaWiki's $wgReservedUsernames
		$user = User::newSystemUser( 'Maintenance script', [ 'steal' => true ] );

		$entities = $this->getSchemasToCreate();

		$this->output( "Starting import...\n\n" );

		foreach ( $entities as $idString => $dataMap ) {
			$this->createSchema( $idString, $dataMap, $user );
		}

		$this->output( "Import completed.\n" );
	}

	private function getSchemasToCreate(): array {
		return [
			'E1' => [
				self::LABEL => 'ShExR',
				self::DESC => 'Schema of ShEx',
			],

			'E2' => [
				self::LABEL => 'Wikimedia',
				self::DESC => 'Schema of Wikimedia projects in Wikidata',
			],

			'E3' => [
				self::LABEL => 'Wikidata Item',
				self::DESC => 'Schema of a Wikidata item',
			],

			'E4' => [
				self::LABEL => 'Labels/Descriptions',
				self::DESC => 'Schema of labels and descriptions',
			],

			'E5' => [
				self::LABEL => 'Statement',
				self::DESC => 'Schema of a Statement',
			],

			'E6' => [
				self::LABEL => 'Language mappings',
				self::DESC => 'Schema for language mappings in Wikidata',
			],

			'E7' => [
				self::LABEL => 'Citation',
				self::DESC => 'Schema of a Citation',
			],

			'E8' => [
				self::LABEL => 'External RDF',
				self::DESC => 'Schema of a Citation',
			],

			'E9' => [
				self::LABEL => 'Wikidata-Wikibase',
				self::DESC => 'Schema linking wikibase and wikidata',
			],

			'E40' => [
				self::LABEL => 'Routes',
				self::DESC => 'European routes',
			],

			'E42' => [
				self::LABEL => 'author',
				self::DESC => 'A Schema for authors',
			],

			'E123' => [
				self::LABEL => 'Sandbox Schema',
				self::DESC => 'An EntitySchema to try things out, not intended for productive use',
			],

			'E570' => [
				self::LABEL => 'recently deceased humans',
				self::DESC => 'Schema for humans who died recently',
			],

			'E734' => [
				self::LABEL => 'family name',
				self::DESC => 'basic scheme for family name items',
			],

			'E735' => [
				self::LABEL => 'given name',
				self::DESC => 'basic schema for given name items',
			],

			'E999' => [
				self::LABEL => 'B0rked',
				self::DESC => 'Broken schema',

			],

			'E1234' => [
				self::LABEL => 'Sandbox Schema',
				self::DESC => 'An EntitySchema to try things out, not intended for productive use',
			],

			'E3300' => [
				self::LABEL => 'human',
				self::DESC => 'basic schema for instances of Q5',
			],

			'E11424' => [
				self::LABEL => 'Films',
				self::DESC => 'basic schema for films',
			],

			'E12345' => [
				self::LABEL => 'Sandbox Schema',
				self::DESC => 'An EntitySchema to try things out, not intended for productive use',
			],

		];
	}

	private function createSchema( $idString, array $dataMap, User $user ) {
		$this->output(
			'Importing Schema with label ' . $dataMap[self::LABEL] . " as EntitySchema $idString... \n"
		);

		$pageUpdaterFactory = new MediaWikiPageUpdaterFactory( $user );

		$fixedIdGenerator = new FixedIdGenerator( (int)trim( $idString, 'E' ) );

		$services = MediaWikiServices::getInstance();
		$schemaInserter = new MediaWikiRevisionSchemaInserter(
			$pageUpdaterFactory,
			new WatchlistUpdater( $user, NS_ENTITYSCHEMA_JSON ),
			$fixedIdGenerator,
			$services->getLanguageFactory(),
			RequestContext::getMain(),
			$services->getHookContainer(),
			$services->getTitleFactory()
		);

		try {
			$schemaInserter->insertSchema(
				'en',
				$dataMap[self::LABEL] ?? '',
				$dataMap[self::DESC] ?? '',
				[],
				''
			);
		} catch ( RuntimeException $e ) {
			$this->output(
				'Failed to save ' . $dataMap[self::LABEL] . " with ID $idString. Moving on... \n"
			);
		}
	}

}

$maintClass = CreatePreexistingSchemas::class;
require_once RUN_MAINTENANCE_IF_MAIN;
