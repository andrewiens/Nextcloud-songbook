<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 Songbook contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Songbook\Tests\Controller;

use OCA\Songbook\Controller\StatusController;
use OCA\Songbook\Service\StatusService;
use OCP\App\IAppManager;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

final class StatusControllerTest extends TestCase {
	public function testReturnsStatusAsDataForTheFrameworkOcsResponder(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppVersion')->willReturn('0.1.0-dev');
		$controller = new StatusController(
			$this->createMock(IRequest::class),
			new StatusService($appManager),
		);

		$response = $controller->show();

		self::assertSame(200, $response->getStatus());
		self::assertSame('songbook', $response->getData()['app']);
		self::assertSame('0.1.0-dev', $response->getData()['version']);
		self::assertArrayNotHasKey('ocs', $response->getData());
	}
}
