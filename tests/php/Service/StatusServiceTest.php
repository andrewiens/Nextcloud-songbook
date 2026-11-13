<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 Songbook contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Songbook\Tests\Service;

use OCA\Songbook\Service\StatusService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;

final class StatusServiceTest extends TestCase {
	public function testReportsInstalledVersionWithoutAdvertisingUnimplementedFeatures(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->expects(self::once())
			->method('getAppVersion')
			->with('songbook')
			->willReturn('0.1.1-dev');

		$status = (new StatusService($appManager))->getStatus();

		self::assertSame([
			'app' => 'songbook',
			'version' => '0.1.1-dev',
			'apiVersion' => 1,
			'capabilities' => ['songLibrary' => false, 'setlists' => false],
		], $status);
	}
}
