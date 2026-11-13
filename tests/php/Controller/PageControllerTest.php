<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 Songbook contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Songbook\Tests\Controller;

use OCA\Songbook\Controller\PageController;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

final class PageControllerTest extends TestCase {
	public function testRendersTheAppInsideNextcloudsAuthenticatedUserLayout(): void {
		$response = (new PageController($this->createMock(IRequest::class)))->index();

		self::assertSame(200, $response->getStatus());
		self::assertSame('songbook', $response->getApp());
		self::assertSame('main', $response->getTemplateName());
		self::assertSame(TemplateResponse::RENDER_AS_USER, $response->getRenderAs());
		self::assertSame([], $response->getParams());
	}
}
