<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 Songbook contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Songbook\Service;

use OCA\Songbook\AppInfo\Application;
use OCP\App\IAppManager;

final class StatusService {
	public function __construct(private readonly IAppManager $appManager) {
	}

	/**
	 * @return array{app: string, version: string, apiVersion: int, capabilities: array{songLibrary: bool, setlists: bool}}
	 */
	public function getStatus(): array {
		return [
			'app' => Application::APP_ID,
			'version' => $this->appManager->getAppVersion(Application::APP_ID),
			'apiVersion' => 1,
			'capabilities' => [
				'songLibrary' => false,
				'setlists' => false,
			],
		];
	}
}
