<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\DataAccess;

use DomainException;
use EntitySchema\DataAccess\MediaWikiPageUpdaterFactory;
use EntitySchema\DataAccess\MediaWikiRevisionEntitySchemaUpdater;
use EntitySchema\DataAccess\WatchlistUpdater;
use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\MediaWiki\EntitySchemaServices;
use EntitySchema\MediaWiki\HookRunner;
use EntitySchema\Services\Converter\NameBadge;
use InvalidArgumentException;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\Content;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Page\WikiPage;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use MediaWiki\Storage\PageUpdater;
use MediaWiki\Storage\PageUpdateStatus;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @covers \EntitySchema\DataAccess\MediaWikiRevisionEntitySchemaUpdater
 * @covers \EntitySchema\DataAccess\EntitySchemaUpdateGuard
 *
 * @group Database
 *
 * @license GPL-2.0-or-later
 */
class MediaWikiRevisionEntitySchemaUpdaterTest extends MediaWikiIntegrationTestCase {

	use TempUserTestTrait;

	private ?RevisionRecord $baseRevision;
	private ?RevisionRecord $parentRevision;

	protected function tearDown(): void {
		$this->baseRevision = null;
		$this->parentRevision = null;
	}

	/**
	 * @param EntitySchemaContent|null $expectedContent The content to expect in a setContent() call
	 * (null means not to expect particular content).
	 * @param EntitySchemaContent|null $existingContent Used to override $this->parentRevision
	 * if not null. grabParentRevision() is only mocked if a page revision is available.
	 *
	 * @return MediaWikiPageUpdaterFactory
	 */
	private function getPageUpdaterFactoryProvidingAndExpectingContent(
		?EntitySchemaContent $expectedContent = null,
		?EntitySchemaContent $existingContent = null
	): MediaWikiPageUpdaterFactory {
		$pageUpdater = $this->createMock( PageUpdater::class );
		if ( $existingContent !== null ) {
			$this->parentRevision = $this->createMockRevisionRecord( $existingContent );
		}
		if ( $this->parentRevision !== null ) {
			$pageUpdater->method( 'grabParentRevision' )->willReturn( $this->parentRevision );
		}
		$pageUpdater->method( 'getStatus' )->willReturn( PageUpdateStatus::newGood() );
		$setContentInvocation = $pageUpdater->expects( $this->once() )
			->method( 'setContent' );
		if ( $expectedContent !== null ) {
			$setContentInvocation->with( SlotRecord::MAIN, $expectedContent );
		}

		return $this->getPageUpdaterFactory( $pageUpdater );
	}

	private function getPageUpdaterFactoryExpectingComment(
		CommentStoreComment $expectedComment,
		?EntitySchemaContent $existingContent = null
	): MediaWikiPageUpdaterFactory {
		$pageUpdater = $this->createMock( PageUpdater::class );
		if ( $existingContent !== null ) {
			$this->parentRevision = $this->createMockRevisionRecord( $existingContent );
			$pageUpdater->method( 'grabParentRevision' )->willReturn( $this->parentRevision );
		}
		$pageUpdater->method( 'getStatus' )->willReturn( PageUpdateStatus::newGood() );
		$pageUpdater->expects( $this->once() )
			->method( 'saveRevision' )
			->with( $expectedComment );

		return $this->getPageUpdaterFactory( $pageUpdater );
	}

	private function getPageUpdaterFactory( ?PageUpdater $pageUpdater = null ): MediaWikiPageUpdaterFactory {
		$wikiPage = $this->createConfiguredMock( WikiPage::class, [
			'newPageUpdater' => $pageUpdater ?? $this->createMock( PageUpdater::class ),
		] );
		$wikiPageFactory = $this->createConfiguredMock( WikiPageFactory::class, [
			'newFromTitle' => $wikiPage,
		] );
		$this->setService( 'WikiPageFactory', $wikiPageFactory );
		return EntitySchemaServices::getMediaWikiPageUpdaterFactory( $this->getServiceContainer() );
	}

	private function createMockRevisionLookup( array $revisionRecords = [] ): RevisionLookup {
		$revisionRecordMap = [];
		foreach ( $revisionRecords as $revisionRecord ) {
			$revisionRecordMap[$revisionRecord->getId()] = $revisionRecord;
		}
		$mockRevLookup = $this->getMockForAbstractClass( RevisionLookup::class );
		$mockRevLookup->method( 'getRevisionById' )
			->willReturnCallback( static function ( $id, $flags = 0 ) use ( $revisionRecordMap ) {
				return $revisionRecordMap[$id] ?? null;
			} );
		return $mockRevLookup;
	}

	private function newUpdaterFailingToSave(): MediaWikiRevisionEntitySchemaUpdater {
		$existingContent = new EntitySchemaContent( '{}' );
		$this->parentRevision = $this->createMockRevisionRecord( $existingContent );

		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'getStatus' )->willReturn( PageUpdateStatus::newFatal( __CLASS__ ) );
		$pageUpdater->method( 'grabParentRevision' )->willReturn( $this->parentRevision );

		$mockRevLookup = $this->createMockRevisionLookup( [ $this->parentRevision ] );

		$services = $this->getServiceContainer();
		return new MediaWikiRevisionEntitySchemaUpdater(
			$this->getPageUpdaterFactory( $pageUpdater ),
			$this->getMockWatchlistUpdater(),
			new RequestContext(),
			$mockRevLookup,
			$services->getLanguageFactory(),
			EntitySchemaServices::getHookRunner( $services )
		);
	}

	private function newUpdaterWithEditFilter( string $schemaId ): MediaWikiRevisionEntitySchemaUpdater {
		$existingContent = new EntitySchemaContent( '{}' );
		$this->parentRevision = $this->createMockRevisionRecord( $existingContent );

		$originalRequest = new FauxRequest();
		$originalUser = $this->getTestUser()->getUser();
		$originalContext = new RequestContext();
		$originalContext->setRequest( $originalRequest );
		$originalContext->setUser( $originalUser );
		$this->setTemporaryHook(
			'EditFilterMergedContent',
			function (
				IContextSource $context,
				Content $content,
				Status $status,
				string $summary,
				User $user,
				bool $minoredit
			) use ( $originalRequest, $originalUser, $schemaId ) {
				$this->assertSame( $originalRequest, $context->getRequest() );
				$this->assertSame( $originalUser, $user );
				$expectedPageIdentity = new PageIdentityValue( 1, NS_ENTITYSCHEMA_JSON, $schemaId, false );
				$this->assertTrue( $context->getTitle()->isSamePageAs( $expectedPageIdentity ) );
				$this->assertInstanceOf( EntitySchemaContent::class, $content );
				$this->assertStringStartsWith( '/* entityschema-summary-', $summary );
				$this->assertFalse( $minoredit );

				$status->fatal( __CLASS__ );
				return false;
			}
		);

		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'grabParentRevision' )
			->willReturn( $this->parentRevision );
		$pageUpdater->expects( $this->never() )
			->method( 'saveRevision' );

		$mockRevLookup = $this->createMockRevisionLookup( [ $this->parentRevision ] );

		$services = $this->getServiceContainer();
		return new MediaWikiRevisionEntitySchemaUpdater(
			$this->getPageUpdaterFactory( $pageUpdater ),
			$this->getMockWatchlistUpdater(),
			$originalContext,
			$mockRevLookup,
			$services->getLanguageFactory(),
			EntitySchemaServices::getHookRunner( $services )
		);
	}

	public function testOverwriteWholeSchema_failsForNonExistentPage() {
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdaterFactory = $this->getPageUpdaterFactory( $pageUpdater );

		$mockRevLookup = $this->createMockRevisionLookup();

		$services = $this->getServiceContainer();
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater(),
			new RequestContext(),
			$mockRevLookup,
			$services->getLanguageFactory(),
			EntitySchemaServices::getHookRunner( $services )
		);

		$status = $schemaUpdater->overwriteWholeSchema(
			new EntitySchemaId( 'E1234569999' ),
			[],
			[],
			[],
			'',
			1,
			CommentStoreComment::newUnsavedComment( '' )
		);

		$this->assertStatusError( 'entityschema-error-schemaupdate-failed', $status );
	}

	public static function provideBadParameters(): iterable {
		$langExceptionMsg = 'language codes must be valid!';
		$typeExceptionMsg = 'language, label and description must be strings '
			. 'and aliases must be an array of strings';
		return [
			'language is not supported' => [ 'not a real langcode', '', '', [], '', $langExceptionMsg ],
			'label is not string' => [ 'de', (object)[], '', [], '', $typeExceptionMsg ],
			'description is not string' => [ 'en', '', (object)[], [], '', $typeExceptionMsg ],
			'aliases is non-string array' => [ 'fr', '', '', [ (object)[] ], '', $typeExceptionMsg ],
			'aliases is mixed array' => [ 'ar', '', '', [ (object)[], 'foo' ], '', $typeExceptionMsg ],
			'aliases is associative array' => [ 'hu', '', '', [ 'en' => 'foo' ], '', $typeExceptionMsg ],
		];
	}

	/**
	 * @dataProvider provideBadParameters
	 */
	public function testOverwriteWholeSchema_throwsForInvalidParams(
		string $testLanguage,
		$testLabel,
		$testDescription,
		$testAliases,
		string $testSchemaText,
		string $exceptionMessage
	) {
		$this->parentRevision = $this->createMockRevisionRecord();
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'grabParentRevision' )->willReturn( $this->parentRevision );
		$pageUpdaterFactory = $this->getPageUpdaterFactory( $pageUpdater );
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->parentRevision ] );

		$services = $this->getServiceContainer();
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater(),
			new RequestContext(),
			$mockRevLookup,
			$services->getLanguageFactory(),
			EntitySchemaServices::getHookRunner( $services )
		);
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( $exceptionMessage );
		$this->assertStatusGood( $schemaUpdater->overwriteWholeSchema(
			new EntitySchemaId( 'E1' ),
			[ $testLanguage => $testLabel ],
			[ $testLanguage => $testDescription ],
			[ $testLanguage => $testAliases ],
			$testSchemaText,
			$this->parentRevision->getId(),
			CommentStoreComment::newUnsavedComment( '' )
		) );
	}

	public function testOverwriteWholeSchema_WritesExpectedContentForOverwritingMonoLingualSchema() {
		$id = 'E1';
		$language = 'en';
		$label = 'englishLabel';
		$description = 'englishDescription';
		$aliases = [ 'englishAlias' ];
		$schemaText = '#some schema about goats';
		$existingContent = new EntitySchemaContent( '' );
		$expectedContent = new EntitySchemaContent( json_encode(
			[
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => [
					$language => $label,
				],
				'descriptions' => [
					$language => $description,
				],
				'aliases' => [
					$language => $aliases,
				],
				'schemaText' => $schemaText,
				'type' => 'ShExC',
			]
		) );
		$pageUpdaterFactory = $this
			->getPageUpdaterFactoryProvidingAndExpectingContent( $expectedContent, $existingContent );
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->parentRevision ] );
		$services = $this->getServiceContainer();
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			new RequestContext(),
			$mockRevLookup,
			$services->getLanguageFactory(),
			EntitySchemaServices::getHookRunner( $services )
		);
		$this->assertStatusGood( $schemaUpdater->overwriteWholeSchema(
			new EntitySchemaId( 'E1' ),
			[ 'en' => 'englishLabel' ],
			[ 'en' => 'englishDescription' ],
			[ 'en' => $aliases ],
			$schemaText,
			$this->parentRevision->getId(),
			CommentStoreComment::newUnsavedComment( '' )
		) );
	}

	public function testOverwriteWholeSchema_saveFails() {
		$schemaUpdater = $this->newUpdaterFailingToSave();

		$status = $schemaUpdater->overwriteWholeSchema(
			new EntitySchemaId( 'E1' ),
			[],
			[],
			[],
			'lalalala',
			1,
			CommentStoreComment::newUnsavedComment( '' )
		);

		$this->assertStatusError( __CLASS__, $status );
	}

	public function testOverwriteWholeSchema_editFilterFails() {
		$schemaUpdater = $this->newUpdaterWithEditFilter( 'E1' );

		$status = $schemaUpdater->overwriteWholeSchema(
			new EntitySchemaId( 'E1' ),
			[],
			[],
			[],
			'lalalala',
			1,
			CommentStoreComment::newUnsavedComment(
				'/* ' . MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_RESTORE . ' */'
			)
		);

		$this->assertStatusError( __CLASS__, $status );
	}

	public function testOverwriteWholeSchema_createTempUser(): void {
		$this->enableAutoCreateTempUser();
		$services = $this->getServiceContainer();
		$expectedContent = null;
		$existingContent = new EntitySchemaContent( '' );
		$pageUpdaterFactory = $this->getPageUpdaterFactoryProvidingAndExpectingContent(
			$expectedContent, $existingContent );
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$pageUpdaterFactory,
			EntitySchemaServices::getWatchlistUpdater( $services ),
			new RequestContext(),
			$this->createMockRevisionLookup( [ $this->parentRevision ] ),
			$services->getLanguageFactory(),
			$this->createConfiguredMock( HookRunner::class, [ 'onEditFilterMergedContent' => true ] )
		);

		$status = $schemaUpdater->overwriteWholeSchema(
			new EntitySchemaId( 'E1' ),
			[],
			[],
			[],
			'',
			$this->parentRevision->getId(),
			CommentStoreComment::newUnsavedComment( '' )
		);
		$this->assertStatusGood( $status );
		$savedTempUser = $status->getSavedTempUser();
		$newContext = $status->getContext();

		$this->assertTrue( $services->getUserIdentityUtils()->isTemp( $savedTempUser ) );
		$this->assertSame( $savedTempUser, $newContext->getUser() );
	}

	/**
	 * @param string|null $methodToExpect
	 *
	 * @return WatchlistUpdater
	 */
	private function getMockWatchlistUpdater( ?string $methodToExpect = null ): WatchlistUpdater {
		$mockWatchlistUpdater = $this->getMockBuilder( WatchlistUpdater::class )
			->disableOriginalConstructor()
			->getMock();
		if ( $methodToExpect === null ) {
			$mockWatchlistUpdater->expects( $this->never() )->method( $this->anything() );
		} else {
			$mockWatchlistUpdater->expects( $this->once() )->method( $methodToExpect );
		}
		return $mockWatchlistUpdater;
	}

	public function testUpdateSchemaText_throwsForInvalidParams() {
		$this->parentRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( '{}' )
		);
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'grabParentRevision' )->willReturn( $this->parentRevision );
		$pageUpdaterFactory = $this->getPageUpdaterFactory( $pageUpdater );
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->parentRevision ] );
		$services = $this->getServiceContainer();
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater(),
			new RequestContext(),
			$mockRevLookup,
			$services->getLanguageFactory(),
			EntitySchemaServices::getHookRunner( $services )
		);

		$this->expectException( InvalidArgumentException::class );
		$schemaUpdater->updateSchemaText(
			new EntitySchemaId( 'E1' ),
			str_repeat( '#', $this->getServiceContainer()->getMainConfig()
				->get( 'EntitySchemaSchemaTextMaxSizeBytes' ) + 100 ),
			1
		);
	}

	public function testUpdateSchemaText_throwsForUnknownSerializationVersion() {
		$this->parentRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'serializationVersion' => '4.0',
				'schema' => [
					'replacing this' => 'with the new text',
					'would be a' => 'grave mistake',
				],
				'schemaText' => [
					'same goes' => 'for this',
				],
			] ) )
		);
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'grabParentRevision' )->willReturn( $this->parentRevision );
		$pageUpdaterFactory = $this->getPageUpdaterFactory( $pageUpdater );
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->parentRevision ] );
		$services = $this->getServiceContainer();
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater(),
			new RequestContext(),
			$mockRevLookup,
			$services->getLanguageFactory(),
			EntitySchemaServices::getHookRunner( $services )
		);

		$this->expectException( DomainException::class );
		$schemaUpdater->updateSchemaText(
			new EntitySchemaId( 'E1' ),
			'',
			$this->parentRevision->getId()
		);
	}

	public function testUpdateSchemaText_failsForEditConflict() {
		$this->parentRevision = $this->createMockRevisionRecord( new EntitySchemaContent(
			'{
		"serializationVersion": "3.0",
		"schemaText": "conflicting text"
		}' ), 2 );
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'grabParentRevision' )->willReturn( $this->parentRevision );
		$pageUpdaterFactory = $this->getPageUpdaterFactory( $pageUpdater );
		$this->baseRevision = $this->createMockRevisionRecord( new EntitySchemaContent(
			'{
		"serializationVersion": "3.0",
		"schemaText": "original text"
		}' ) );
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->baseRevision, $this->parentRevision ] );
		$services = $this->getServiceContainer();
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater(),
			new RequestContext(),
			$mockRevLookup,
			$services->getLanguageFactory(),
			EntitySchemaServices::getHookRunner( $services )
		);

		$status = $schemaUpdater->updateSchemaText(
			new EntitySchemaId( 'E1' ),
			'',
			$this->baseRevision->getId()
		);

		$this->assertStatusError( 'edit-conflict', $status );
	}

	public function testUpdateSchemaText_WritesExpectedContentForOverwritingSchemaText() {
		$id = 'E1';
		$language = 'en';
		$labels = [ $language => 'englishLabel' ];
		$descriptions = [ $language => 'englishDescription' ];
		$aliases = [ $language => [ 'englishAlias' ] ];
		$existingContent = new EntitySchemaContent( json_encode( [
			'id' => $id,
			'serializationVersion' => '3.0',
			'labels' => $labels,
			'descriptions' => $descriptions,
			'aliases' => $aliases,
			'schemaText' => '# some schema about goats',
			'type' => 'ShExC',
		] ) );
		$newSchemaText = '# some schema about cats';
		$expectedContent = new EntitySchemaContent( json_encode( [
			'id' => $id,
			'serializationVersion' => '3.0',
			'labels' => $labels,
			'descriptions' => $descriptions,
			'aliases' => $aliases,
			'schemaText' => $newSchemaText,
			'type' => 'ShExC',
		] ) );

		$pageUpdaterFactory = $this
			->getPageUpdaterFactoryProvidingAndExpectingContent( $expectedContent, $existingContent );
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->parentRevision ] );
		$services = $this->getServiceContainer();
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			new RequestContext(),
			$mockRevLookup,
			$services->getLanguageFactory(),
			EntitySchemaServices::getHookRunner( $services )
		);

		$this->assertStatusGood( $schemaUpdater->updateSchemaText(
			new EntitySchemaId( $id ),
			$newSchemaText,
			$this->parentRevision->getId()
		) );
	}

	public function testUpdateSchemaText_mergesChangesInNameBadge() {
		$id = 'E1';
		$oldLabels = [ 'en' => 'old label' ];
		$newLabels = [ 'en' => 'new label' ];
		$descriptions = [ 'en' => 'description' ];
		$aliases = [ 'en' => [ 'alias' ] ];
		$oldSchemaText = 'old schema text';
		$newSchemaText = 'new schema text';

		$this->baseRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $oldLabels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $oldSchemaText,
				'type' => 'ShExC',
			] ) )
		);
		$this->parentRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $newLabels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $oldSchemaText,
				'type' => 'ShExC',
			] ) ),
			2
		);
		$pageUpdaterFactory = $this->getPageUpdaterFactoryProvidingAndExpectingContent(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $newLabels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $newSchemaText,
				'type' => 'ShExC',
			] ) )
		);
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->baseRevision, $this->parentRevision ] );

		$services = $this->getServiceContainer();
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			new RequestContext(),
			$mockRevLookup,
			$services->getLanguageFactory(),
			EntitySchemaServices::getHookRunner( $services )
		);
		$this->assertStatusGood( $schemaUpdater->updateSchemaText(
			new EntitySchemaId( $id ),
			$newSchemaText,
			$this->baseRevision->getId()
		) );
	}

	public function testUpdateSchemaText_mergesChangesInSchemaText() {
		$id = 'E1';
		$labels = [ 'en' => 'label' ];
		$descriptions = [ 'en' => 'description' ];
		$aliases = [ 'en' => [ 'alias' ] ];
		$baseSchemaText = <<< 'SHEXC'
<:foo> {
  :bar .;
}

<:abc> {
  :xyz .;
}
SHEXC;
		$parentSchemaText = <<< 'SHEXC'
<:foo> {
  :bar IRI;
}

<:abc> {
  :xyz .;
}
SHEXC;
		$userSchemaText = <<< 'SHEXC'
<:foo> {
  :bar .;
}

<:abc> {
  :xyz IRI;
}
SHEXC;
		$finalSchemaText = <<< 'SHEXC'
<:foo> {
  :bar IRI;
}

<:abc> {
  :xyz IRI;
}
SHEXC;

		$this->baseRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $labels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $baseSchemaText,
				'type' => 'ShExC',
			] ) )
		);
		$this->parentRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $labels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $parentSchemaText,
				'type' => 'ShExC',
			] ) ),
			2
		);
		$pageUpdaterFactory = $this->getPageUpdaterFactoryProvidingAndExpectingContent(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $labels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $finalSchemaText,
				'type' => 'ShExC',
			] ) )
		);
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->baseRevision, $this->parentRevision ] );

		$services = $this->getServiceContainer();
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			new RequestContext(),
			$mockRevLookup,
			$services->getLanguageFactory(),
			EntitySchemaServices::getHookRunner( $services )
		);
		$this->assertStatusGood( $schemaUpdater->updateSchemaText(
			new EntitySchemaId( $id ),
			$userSchemaText,
			$this->baseRevision->getId()
		) );
	}

	public function testUpdateSchemaText_saveFails() {
		$schemaUpdater = $this->newUpdaterFailingToSave();

		$status = $schemaUpdater->updateSchemaText(
			new EntitySchemaId( 'E1' ),
			'qwerty',
			1
		);

		$this->assertStatusError( __CLASS__, $status );
	}

	public function testUpdateSchemaText_editFilterFails() {
		$schemaUpdater = $this->newUpdaterWithEditFilter( 'E1' );

		$status = $schemaUpdater->updateSchemaText(
			new EntitySchemaId( 'E1' ),
			'qwerty',
			1
		);

		$this->assertStatusError( __CLASS__, $status );
	}

	public function testUpdateSchemaText_comment() {
		$expectedComment = CommentStoreComment::newUnsavedComment(
			'/* ' . MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_UPDATED_SCHEMATEXT . ' */user given',
			[
				'key' => 'entityschema-summary-update-schema-text',
				'schemaText_truncated' => 'new schema text',
				'userSummary' => 'user given',
			]
		);

		$id = new EntitySchemaId( 'E1' );
		$existingContent = new EntitySchemaContent( json_encode( [
			'id' => $id,
			'serializationVersion' => '3.0',
			'labels' => [],
			'descriptions' => [],
			'aliases' => [],
			'schemaText' => '# some schema about goats',
			'type' => 'ShExC',
		] ) );

		$pageUpdaterFactory = $this->getPageUpdaterFactoryExpectingComment(
			$expectedComment,
			$existingContent
		);
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->parentRevision ] );
		$services = $this->getServiceContainer();
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			new RequestContext(),
			$mockRevLookup,
			$services->getLanguageFactory(),
			EntitySchemaServices::getHookRunner( $services )
		);

		$this->assertStatusGood( $schemaUpdater->updateSchemaText(
			$id,
			'new schema text',
			$this->parentRevision->getId(),
			'user given'
		) );
	}

	public function testUpdateSchemaText_onlySerializationVersionChanges() {
		$this->parentRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'serializationVersion' => '2.0',
				'schema' => 'schema text',
			] ) )
		);
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'grabParentRevision' )
			->willReturn( $this->parentRevision );
		$pageUpdater->expects( $this->never() )->method( 'setContent' );
		$pageUpdater->expects( $this->never() )->method( 'saveRevision' );
		$pageUpdaterFactory = $this->getPageUpdaterFactory( $pageUpdater );
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->parentRevision ] );
		$services = $this->getServiceContainer();
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater(),
			new RequestContext(),
			$mockRevLookup,
			$services->getLanguageFactory(),
			EntitySchemaServices::getHookRunner( $services )
		);

		$this->assertStatusGood( $schemaUpdater->updateSchemaText(
			new EntitySchemaId( 'E1' ),
			'schema text',
			$this->parentRevision->getId()
		) );
	}

	public function testUpdateSchemaText_createTempUser(): void {
		$this->enableAutoCreateTempUser();
		$services = $this->getServiceContainer();
		$expectedContent = null;
		$existingContent = new EntitySchemaContent( '{}' );
		$pageUpdaterFactory = $this->getPageUpdaterFactoryProvidingAndExpectingContent(
			$expectedContent, $existingContent );
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$pageUpdaterFactory,
			EntitySchemaServices::getWatchlistUpdater( $services ),
			new RequestContext(),
			$this->createMockRevisionLookup( [ $this->parentRevision ] ),
			$services->getLanguageFactory(),
			$this->createConfiguredMock( HookRunner::class, [ 'onEditFilterMergedContent' => true ] )
		);

		$status = $schemaUpdater->updateSchemaText(
			new EntitySchemaId( 'E1' ),
			'schema text',
			$this->parentRevision->getId()
		);
		$this->assertStatusGood( $status );
		$savedTempUser = $status->getSavedTempUser();
		$newContext = $status->getContext();

		$this->assertTrue( $services->getUserIdentityUtils()->isTemp( $savedTempUser ) );
		$this->assertSame( $savedTempUser, $newContext->getUser() );
	}

	public function testUpdateSchemaNameBadgeSuccess() {
		$id = 'E1';
		$language = 'en';
		$labels = [ $language => 'englishLabel' ];
		$descriptions = [ $language => 'englishDescription' ];
		$aliases = [ $language => [ 'englishAlias' ] ];
		$existingContent = new EntitySchemaContent( json_encode( [
			'id' => $id,
			'serializationVersion' => '3.0',
			'labels' => [ 'en' => 'Cat' ],
			'descriptions' => [ 'en' => 'This is what a cat look like' ],
			'aliases' => [ 'en' => [ 'Tiger', 'Lion' ] ],
			'schemaText' => '# some schema about goats',
			'type' => 'ShExC',
		] ) );
		$expectedContent = new EntitySchemaContent( json_encode( [
			'id' => $id,
			'serializationVersion' => '3.0',
			'labels' => $labels,
			'descriptions' => $descriptions,
			'aliases' => $aliases,
			'schemaText' => '# some schema about goats',
			'type' => 'ShExC',
		] ) );

		$pageUpdaterFactory = $this
			->getPageUpdaterFactoryProvidingAndExpectingContent( $expectedContent, $existingContent );
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->parentRevision ] );
		$services = $this->getServiceContainer();
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			new RequestContext(),
			$mockRevLookup,
			$services->getLanguageFactory(),
			EntitySchemaServices::getHookRunner( $services )
		);

		$this->assertStatusGood( $schemaUpdater->updateSchemaNameBadge(
			new EntitySchemaId( $id ),
			$language,
			$labels['en'],
			$descriptions['en'],
			$aliases['en'],
			$this->parentRevision->getId()
		) );
	}

	public function testUpdateMultiLingualSchemaNameBadgeSuccess() {
		$id = 'E1';
		$language = 'en';
		$englishLabel = 'Goat';
		$englishDescription = 'This is what a goat looks like';
		$englishAliases = [ 'Capra' ];
		$existingContent = new EntitySchemaContent( json_encode( [
			'id' => $id,
			'serializationVersion' => '3.0',
			'labels' => [
				'en' => 'Cat',
				'de' => 'Ziege',
			],
			'descriptions' => [
				'en' => 'This is what a cat looks like',
				'de' => 'Wichtigste Eigenschaften einer Ziege',
			],
			'aliases' => [
				'en' => [ 'Tiger', 'Lion' ],
				'de' => [ 'Capra', 'Hausziege' ],
			],
			'schemaText' => '# some schema about goats',
			'type' => 'ShExC',
		] ) );
		$expectedContent = new EntitySchemaContent( json_encode( [
			'id' => $id,
			'serializationVersion' => '3.0',
			'labels' => [
				'de' => 'Ziege',
				'en' => $englishLabel,
			],
			'descriptions' => [
				'de' => 'Wichtigste Eigenschaften einer Ziege',
				'en' => $englishDescription,
			],
			'aliases' => [
				'de' => [ 'Capra', 'Hausziege' ],
				'en' => $englishAliases,
			],
			'schemaText' => '# some schema about goats',
			'type' => 'ShExC',
		] ) );

		$pageUpdaterFactory = $this
			->getPageUpdaterFactoryProvidingAndExpectingContent( $expectedContent, $existingContent );
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->parentRevision ] );
		$services = $this->getServiceContainer();
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			new RequestContext(),
			$mockRevLookup,
			$services->getLanguageFactory(),
			EntitySchemaServices::getHookRunner( $services )
		);

		$this->assertStatusGood( $schemaUpdater->updateSchemaNameBadge(
			new EntitySchemaId( $id ),
			$language,
			$englishLabel,
			$englishDescription,
			$englishAliases,
			$this->parentRevision->getId()
		) );
	}

	/**
	 * @dataProvider provideNameBadgesWithComments
	 */
	public function testUpdateSchemaNameBadge_comment(
		?NameBadge $old,
		NameBadge $new,
		string $expectedAutocommentKey,
		string $expectedAutosummary
	) {
		$id = 'E1';
		$language = 'en';
		$oldArray = [
			'id' => $id,
			'serializationVersion' => '3.0',
			'labels' => [],
			'descriptions' => [],
			'aliases' => [],
			'schemaText' => 'schema text',
			'type' => 'ShExC',
		];

		if ( $old !== null ) {
			$oldArray['labels'][$language] = $old->label;
			$oldArray['descriptions'][$language] = $old->description;
			$oldArray['aliases'][$language] = $old->aliases;
		}

		$autocomment = $expectedAutocommentKey . ':' . $language;
		$expectedComment = CommentStoreComment::newUnsavedComment(
			'/* ' . $autocomment . ' */' . $expectedAutosummary,
			[
				'key' => $expectedAutocommentKey,
				'language' => $language,
				'label' => $new->label,
				'description' => $new->description,
				'aliases' => $new->aliases,
			]
		);

		$oldContent = new EntitySchemaContent( json_encode( $oldArray ) );
		$pageUpdaterFactory = $this->getPageUpdaterFactoryExpectingComment(
			$expectedComment,
			$oldContent
		);
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->parentRevision ] );
		$services = $this->getServiceContainer();
		$writer = new MediaWikiRevisionEntitySchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			new RequestContext(),
			$mockRevLookup,
			$services->getLanguageFactory(),
			EntitySchemaServices::getHookRunner( $services )
		);

		$writer->updateSchemaNameBadge(
			new EntitySchemaId( $id ),
			$language,
			$new->label,
			$new->description,
			$new->aliases,
			$this->parentRevision->getId()
		);
	}

	public static function provideNameBadgesWithComments(): iterable {
		$oldBadge = new NameBadge( 'old label', 'old description', [ 'old alias' ] );

		yield 'everything changed' => [
			$oldBadge,
			new NameBadge( 'new label', 'new description', [ 'new alias' ] ),
			MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_UPDATED_NAMEBADGE,
			'',
		];

		yield 'label changed' => [
			$oldBadge,
			new NameBadge( 'new label', $oldBadge->description, $oldBadge->aliases ),
			MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_UPDATED_LABEL,
			'new label',
		];

		yield 'description changed' => [
			$oldBadge,
			new NameBadge( $oldBadge->label, 'new description', $oldBadge->aliases ),
			MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_UPDATED_DESCRIPTION,
			'new description',
		];

		yield 'aliases changed' => [
			$oldBadge,
			new NameBadge( $oldBadge->label, $oldBadge->description, [ 'new alias', 'other' ] ),
			MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_UPDATED_ALIASES,
			'new alias, other',
		];

		yield 'label removed' => [
			$oldBadge,
			new NameBadge( '', $oldBadge->description, $oldBadge->aliases ),
			MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_UPDATED_LABEL,
			'',
		];

		yield 'description removed' => [
			$oldBadge,
			new NameBadge( $oldBadge->label, '', $oldBadge->aliases ),
			MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_UPDATED_DESCRIPTION,
			'',
		];

		yield 'aliases removed' => [
			$oldBadge,
			new NameBadge( $oldBadge->label, $oldBadge->description, [] ),
			MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_UPDATED_ALIASES,
			'',
		];

		yield 'label added in new language' => [
			null,
			new NameBadge( 'new label', '', [] ),
			MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_UPDATED_LABEL,
			'new label',
		];

		yield 'description added in new language' => [
			null,
			new NameBadge( '', 'new description', [] ),
			MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_UPDATED_DESCRIPTION,
			'new description',
		];

		yield 'aliases added in new language' => [
			null,
			new NameBadge( '', '', [ 'new alias' ] ),
			MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_UPDATED_ALIASES,
			'new alias',
		];

		yield 'label changed, alias removed' => [
			$oldBadge,
			new NameBadge( 'new label', $oldBadge->description, [] ),
			MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_UPDATED_NAMEBADGE,
			'',
		];
	}

	public function testUpdateSchemaNameBadge_failsForEditConflict() {
		$this->parentRevision = $this->createMockRevisionRecord( new EntitySchemaContent(
			json_encode(
				[
					'serializationVersion' => '3.0',
					'labels' => [ 'en' => 'conflicting label' ],
				]
			)
		), 2 );
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'grabParentRevision' )->willReturn( $this->parentRevision );
		$pageUpdaterFactory = $this->getPageUpdaterFactory( $pageUpdater );

		$this->baseRevision = $this->createMockRevisionRecord( new EntitySchemaContent(
			json_encode(
				[
					'serializationVersion' => '3.0',
					'labels' => [ 'en' => 'original label' ],
				]
			)
		) );
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->baseRevision, $this->parentRevision ] );

		$services = $this->getServiceContainer();
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater(),
			new RequestContext(),
			$mockRevLookup,
			$services->getLanguageFactory(),
			EntitySchemaServices::getHookRunner( $services )
		);

		$status = $schemaUpdater->updateSchemaNameBadge(
			new EntitySchemaId( 'E1' ),
			'en',
			'test label',
			'test description',
			[ 'test alias' ],
			$this->baseRevision->getId()
		);

		$this->assertStatusError( 'edit-conflict', $status );
	}

	public function testUpdateSchemaNameBadgeSuccessNonConflictingEdit() {
		$id = 'E1';
		$language = 'en';
		$labels = [ $language => 'englishLabel' ];
		$descriptions = [ $language => 'englishDescription' ];
		$aliases = [ $language => [ 'englishAlias' ] ];
		$existingContent = new EntitySchemaContent( json_encode( [
			'id' => $id,
			'serializationVersion' => '3.0',
			'labels' => [ 'en' => 'Cat' ],
			'descriptions' => [ 'en' => 'This is what a cat look like' ],
			'aliases' => [ 'en' => [ 'Tiger', 'Lion' ] ],
			'schemaText' => '# some schema about goats',
			'type' => 'ShExC',
		] ) );
		$expectedContent = new EntitySchemaContent( json_encode( [
			'id' => $id,
			'serializationVersion' => '3.0',
			'labels' => $labels,
			'descriptions' => $descriptions,
			'aliases' => $aliases,
			'schemaText' => '# some schema about goats',
			'type' => 'ShExC',
		] ) );

		$this->baseRevision = $this->createMockRevisionRecord( new EntitySchemaContent(
			json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => [ 'en' => 'Cat', 'de' => 'Katze' ],
				'descriptions' => [ 'en' => 'This is what a cat look like' ],
				'aliases' => [ 'en' => [ 'Tiger', 'Lion' ] ],
				'schemaText' => '# some schema about goats',
				'type' => 'ShExC',
			] )
		) );

		$pageUpdaterFactory = $this
			->getPageUpdaterFactoryProvidingAndExpectingContent( $expectedContent, $existingContent );
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->baseRevision, $this->parentRevision ] );
		$services = $this->getServiceContainer();
		$updater = new MediaWikiRevisionEntitySchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			new RequestContext(),
			$mockRevLookup,
			$services->getLanguageFactory(),
			EntitySchemaServices::getHookRunner( $services )
		);

		$this->assertStatusGood( $updater->updateSchemaNameBadge(
			new EntitySchemaId( $id ),
			$language,
			$labels['en'],
			$descriptions['en'],
			$aliases['en'],
			$this->baseRevision->getId()
		) );
	}

	public function testUpdateNameBadge_mergesChangesInSchemaText() {
		$id = 'E1';
		$oldLabels = [ 'en' => 'old label' ];
		$newLabels = [ 'en' => 'new label' ];
		$descriptions = [ 'en' => 'description' ];
		$aliases = [ 'en' => [ 'alias' ] ];
		$oldSchemaText = 'old schema text';
		$newSchemaText = 'new schema text';

		$this->baseRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $oldLabels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $oldSchemaText,
				'type' => 'ShExC',
			] ) )
		);
		$this->parentRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $oldLabels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $newSchemaText,
				'type' => 'ShExC',
			] ) ),
			2
		);
		$pageUpdaterFactory = $this->getPageUpdaterFactoryProvidingAndExpectingContent(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $newLabels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $newSchemaText,
				'type' => 'ShExC',
			] ) )
		);
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->baseRevision, $this->parentRevision ] );

		$services = $this->getServiceContainer();
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			new RequestContext(),
			$mockRevLookup,
			$services->getLanguageFactory(),
			EntitySchemaServices::getHookRunner( $services )
		);
		$this->assertStatusGood( $schemaUpdater->updateSchemaNameBadge(
			new EntitySchemaId( $id ),
			'en',
			$newLabels['en'],
			$descriptions['en'],
			$aliases['en'],
			$this->baseRevision->getId()
		) );
	}

	public function testUpdateNameBadge_mergesChangesInOtherLanguage() {
		$id = 'E1';
		$baseLabels = [ 'de' => 'alte Bezeichnung', 'en' => 'old label' ];
		$parentLabels = [ 'de' => 'neue Bezeichnung', 'en' => 'old label' ];
		$userLabels = [ 'de' => 'alte Bezeichnung', 'en' => 'new label' ];
		$finalLabels = [ 'de' => 'neue Bezeichnung', 'en' => 'new label' ];
		$descriptions = [ 'en' => 'description' ];
		$aliases = [ 'en' => [ 'alias' ] ];
		$schemaText = 'schema text';

		$this->baseRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $baseLabels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $schemaText,
				'type' => 'ShExC',
			] ) )
		);
		$this->parentRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $parentLabels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $schemaText,
				'type' => 'ShExC',
			] ) ),
			2
		);
		$pageUpdaterFactory = $this->getPageUpdaterFactoryProvidingAndExpectingContent(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $finalLabels,
				'descriptions' => $descriptions,
				'aliases' => $aliases,
				'schemaText' => $schemaText,
				'type' => 'ShExC',
			] ) )
		);
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->baseRevision, $this->parentRevision ] );

		$services = $this->getServiceContainer();
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			new RequestContext(),
			$mockRevLookup,
			$services->getLanguageFactory(),
			EntitySchemaServices::getHookRunner( $services )
		);
		$this->assertStatusGood( $schemaUpdater->updateSchemaNameBadge(
			new EntitySchemaId( $id ),
			'en',
			$userLabels['en'],
			$descriptions['en'],
			$aliases['en'],
			$this->baseRevision->getId()
		) );
	}

	public function testUpdateNameBadge_mergesChangesInSameLanguage() {
		$id = 'E1';
		$oldLabels = [ 'en' => 'old label' ];
		$newLabels = [ 'en' => 'new label' ];
		$oldDescriptions = [ 'en' => 'old description' ];
		$newDescriptions = [ 'en' => 'new description' ];
		$aliases = [ 'en' => [ 'alias' ] ];
		$schemaText = 'schema text';

		$this->baseRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $oldLabels,
				'descriptions' => $oldDescriptions,
				'aliases' => $aliases,
				'schemaText' => $schemaText,
				'type' => 'ShExC',
			] ) )
		);
		$this->parentRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $oldLabels,
				'descriptions' => $newDescriptions,
				'aliases' => $aliases,
				'schemaText' => $schemaText,
				'type' => 'ShExC',
			] ) ),
			2
		);
		$pageUpdaterFactory = $this->getPageUpdaterFactoryProvidingAndExpectingContent(
			new EntitySchemaContent( json_encode( [
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => $newLabels,
				'descriptions' => $newDescriptions,
				'aliases' => $aliases,
				'schemaText' => $schemaText,
				'type' => 'ShExC',
			] ) )
		);
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->baseRevision, $this->parentRevision ] );

		$services = $this->getServiceContainer();
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater( 'optionallyWatchEditedSchema' ),
			new RequestContext(),
			$mockRevLookup,
			$services->getLanguageFactory(),
			EntitySchemaServices::getHookRunner( $services )
		);
		$this->assertStatusGood( $schemaUpdater->updateSchemaNameBadge(
			new EntitySchemaId( $id ),
			'en',
			$newLabels['en'],
			$oldDescriptions['en'],
			$aliases['en'],
			$this->baseRevision->getId()
		) );
	}

	public function testUpdateSchemaNameBadge_saveFails() {
		$schemaUpdater = $this->newUpdaterFailingToSave();

		$status = $schemaUpdater->updateSchemaNameBadge(
			new EntitySchemaId( 'E1' ),
			'en',
			'test label',
			'test description',
			[ 'test alias' ],
			1
		);

		$this->assertStatusError( __CLASS__, $status );
	}

	public function testUpdateSchemaNameBadge_editFilterFails() {
		$schemaUpdater = $this->newUpdaterWithEditFilter( 'E1' );

		$status = $schemaUpdater->updateSchemaNameBadge(
			new EntitySchemaId( 'E1' ),
			'en',
			'test label',
			'test description',
			[ 'test alias' ],
			1
		);

		$this->assertStatusError( __CLASS__, $status );
	}

	public function testUpdateSchemaNameBadge_onlySerializationVersionChanges() {
		$this->parentRevision = $this->createMockRevisionRecord(
			new EntitySchemaContent( json_encode( [
				'serializationVersion' => '2.0',
				'labels' => [ 'en' => 'label' ],
			] ) )
		);
		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->method( 'grabParentRevision' )
			->willReturn( $this->parentRevision );
		$pageUpdater->expects( $this->never() )->method( 'setContent' );
		$pageUpdater->expects( $this->never() )->method( 'saveRevision' );
		$pageUpdaterFactory = $this->getPageUpdaterFactory( $pageUpdater );
		$mockRevLookup = $this->createMockRevisionLookup( [ $this->parentRevision ] );
		$services = $this->getServiceContainer();
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$pageUpdaterFactory,
			$this->getMockWatchlistUpdater(),
			new RequestContext(),
			$mockRevLookup,
			$services->getLanguageFactory(),
			EntitySchemaServices::getHookRunner( $services )
		);

		$schemaUpdater->updateSchemaNameBadge(
			new EntitySchemaId( 'E1' ),
			'en',
			'label',
			'',
			[],
			$this->parentRevision->getId()
		);
	}

	public function testUpdateSchemaNameBadge_createTempUser(): void {
		$this->enableAutoCreateTempUser();
		$services = $this->getServiceContainer();
		$expectedContent = null;
		$existingContent = new EntitySchemaContent( '{}' );
		$pageUpdaterFactory = $this->getPageUpdaterFactoryProvidingAndExpectingContent(
			$expectedContent, $existingContent );
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$pageUpdaterFactory,
			EntitySchemaServices::getWatchlistUpdater( $services ),
			new RequestContext(),
			$this->createMockRevisionLookup( [ $this->parentRevision ] ),
			$services->getLanguageFactory(),
			$this->createConfiguredMock( HookRunner::class, [ 'onEditFilterMergedContent' => true ] )
		);

		$status = $schemaUpdater->updateSchemaNameBadge(
			new EntitySchemaId( 'E1' ),
			'en',
			'label',
			'description',
			[ 'alias' ],
			$this->parentRevision->getId()
		);
		$this->assertStatusGood( $status );
		$savedTempUser = $status->getSavedTempUser();
		$newContext = $status->getContext();

		$this->assertTrue( $services->getUserIdentityUtils()->isTemp( $savedTempUser ) );
		$this->assertSame( $savedTempUser, $newContext->getUser() );
	}

	private function createMockRevisionRecord(
		?EntitySchemaContent $content = null,
		int $id = 1
	): RevisionRecord {
		$revisionRecord = $this->createMock( RevisionRecord::class );
		$revisionRecord->method( 'getContent' )->willReturn( $content );
		$revisionRecord->method( 'getId' )->willReturn( $id );
		return $revisionRecord;
	}

}
