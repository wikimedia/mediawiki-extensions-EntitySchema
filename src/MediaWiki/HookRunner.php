<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki;

use MediaWiki\Content\Content;
use MediaWiki\Context\IContextSource;
use MediaWiki\Hook\EditFilterMergedContentHook;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Status\Status;
use MediaWiki\User\User;

/**
 * @license GPL-2.0-or-later
 */
class HookRunner implements EditFilterMergedContentHook {

	private HookContainer $hookContainer;

	public function __construct( HookContainer $hookContainer ) {
		$this->hookContainer = $hookContainer;
	}

	/**
	 * @param IContextSource $context
	 * @param Content $content
	 * @param Status $status
	 * @param string $summary
	 * @param User $user
	 * @param bool $minoredit
	 * @return bool|void
	 */
	public function onEditFilterMergedContent(
		IContextSource $context,
		Content $content,
		Status $status,
		$summary,
		User $user,
		$minoredit
	) {
		return $this->hookContainer->run(
			'EditFilterMergedContent',
			[ $context, $content, $status, $summary, $user, $minoredit ]
		);
	}
}
