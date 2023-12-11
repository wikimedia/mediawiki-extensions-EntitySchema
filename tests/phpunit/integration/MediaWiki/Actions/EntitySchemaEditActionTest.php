<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Actions;

use Article;
use EntitySchema\MediaWiki\Actions\EntitySchemaEditAction;
use EntitySchema\Presentation\InputValidator;
use MediaWiki\Block\BlockManager;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Permissions\RestrictionStore;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use PermissionsError;
use ReadOnlyError;
use RequestContext;
use Wikimedia\Rdbms\ReadOnlyMode;

/**
 * @covers \EntitySchema\MediaWiki\Actions\EntitySchemaEditAction
 *
 * @license GPL-2.0-or-later
 */
class EntitySchemaEditActionTest extends MediaWikiIntegrationTestCase {

	public function testReadOnly() {
		$this->setService( 'PermissionManager', $this->createMock( PermissionManager::class ) );
		$readOnlyMode = $this->getMockBuilder( ReadOnlyMode::class )
			->disableOriginalConstructor()
			->getMock();
		$readOnlyMode->method( 'isReadOnly' )->willReturn( true );
		$this->setService( 'ReadOnlyMode', $readOnlyMode );
		$context = RequestContext::getMain();
		$action = new EntitySchemaEditAction(
			Article::newFromTitle(
				Title::newFromDBkey( 'E1' ),
				$context
			),
			$context,
			$this->getMockBuilder( InputValidator::class )
				->disableOriginalConstructor()->getMock(),
			false,
			$this->getServiceContainer()->getUserOptionsLookup(),
			'https://example.com/license',
			'license text'
		);

		$this->expectException( ReadOnlyError::class );
		$action->show();
	}

	public function testNoRights() {
		$restrictionStore = $this->createMock( RestrictionStore::class );
		$restrictionStore->method( 'getCascadeProtectionSources' )->willReturn( [ [], [] ] );
		$this->setService( 'RestrictionStore', $restrictionStore );
		$this->setService( 'BlockManager', $this->createMock( BlockManager::class ) );
		$this->mergeMwGlobalArrayValue( 'wgGroupPermissions',
			[ '*' => [ 'edit' => false ] ] );
		$context = RequestContext::getMain();
		$title = Title::makeTitle( NS_MAIN, 'E1' );
		$title->resetArticleID( 0 );
		$action = new EntitySchemaEditAction(
			Article::newFromTitle(
				$title,
				$context
			),
			$context,
			$this->getMockBuilder( InputValidator::class )
				->disableOriginalConstructor()->getMock(),
			false,
			$this->getServiceContainer()->getUserOptionsLookup(),
			'https://example.com/license',
			'license text'
		);

		$this->expectException( PermissionsError::class );
		$action->show();
	}

}
