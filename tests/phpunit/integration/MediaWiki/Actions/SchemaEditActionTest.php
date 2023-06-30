<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Actions;

use Article;
use EntitySchema\MediaWiki\Actions\SchemaEditAction;
use EntitySchema\Presentation\InputValidator;
use MediaWikiIntegrationTestCase;
use PermissionsError;
use ReadOnlyError;
use ReadOnlyMode;
use RequestContext;
use Title;

/**
 * @covers \EntitySchema\MediaWiki\Actions\SchemaEditAction
 *
 * @license GPL-2.0-or-later
 */
class SchemaEditActionTest extends MediaWikiIntegrationTestCase {

	public function testReadOnly() {
		$readOnlyMode = $this->getMockBuilder( ReadOnlyMode::class )
			->disableOriginalConstructor()
			->getMock();
		$readOnlyMode->method( 'isReadOnly' )->willReturn( true );
		$this->setService( 'ReadOnlyMode', $readOnlyMode );
		$context = RequestContext::getMain();
		$action = new SchemaEditAction(
			Article::newFromTitle(
				Title::newFromDBkey( 'E1' ),
				$context
			),
			$context,
			$this->getMockBuilder( InputValidator::class )
				->disableOriginalConstructor()->getMock(),
			false,
			$this->getServiceContainer()->getUserOptionsLookup()
		);

		$this->expectException( ReadOnlyError::class );
		$action->show();
	}

	public function testNoRights() {
		$this->mergeMwGlobalArrayValue( 'wgGroupPermissions',
			[ '*' => [ 'edit' => false ] ] );
		$context = RequestContext::getMain();
		$action = new SchemaEditAction(
			Article::newFromTitle(
				Title::newFromDBkey( 'E1' ),
				$context
			),
			$context,
			$this->getMockBuilder( InputValidator::class )
				->disableOriginalConstructor()->getMock(),
			false,
			$this->getServiceContainer()->getUserOptionsLookup()
		);

		$this->expectException( PermissionsError::class );
		$action->show();
	}

}
