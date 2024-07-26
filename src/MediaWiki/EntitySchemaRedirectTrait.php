<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki;

use EntitySchema\DataAccess\EntitySchemaStatus;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\WebRequest;
use MediaWiki\Title\Title;
use Wikimedia\Assert\Assert;

/**
 * Trait for a special page or action to redirect to an EntitySchema after an edit.
 *
 * If a temporary account was created during the edit,
 * the redirect may be altered by a hook (e.g. CentralAuth might redirect to loginwiki).
 * @license GPL-2.0-or-later
 */
trait EntitySchemaRedirectTrait {

	/** @return OutputPage */
	abstract public function getOutput();

	/** @return HookRunner */
	abstract protected function getHookRunner();

	/** @return WebRequest */
	abstract public function getRequest();

	protected function redirectToEntitySchema( EntitySchemaStatus $status, string $redirectParams = '' ): void {
		Assert::parameter( $status->isOK(), '$status', 'must be OK' );
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $status->getEntitySchemaId()->getId() );
		$savedTempUser = $status->getSavedTempUser();
		$redirectUrl = '';
		if ( $savedTempUser !== null ) {
			$this->getHookRunner()->onTempUserCreatedRedirect(
				$this->getRequest()->getSession(),
				$savedTempUser,
				$title->getPrefixedDBkey(),
				$redirectParams,
				'',
				$redirectUrl
			);
		}
		if ( !$redirectUrl ) {
			$redirectUrl = $title->getFullURL( $redirectParams );
		}
		$this->getOutput()->redirect( $redirectUrl );
	}

}
