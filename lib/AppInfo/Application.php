<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 Songbook contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Songbook\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCA\Songbook\Listener\FileChangedListener;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;

final class Application extends App implements IBootstrap {
	public const APP_ID = 'songbook';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		foreach ([NodeWrittenEvent::class, NodeRenamedEvent::class, NodeDeletedEvent::class] as $event) {
			$context->registerEventListener($event, FileChangedListener::class);
		}
	}

	public function boot(IBootContext $context): void {
		// Navigation is declared in appinfo/info.xml; no global boot work is needed.
	}
}
