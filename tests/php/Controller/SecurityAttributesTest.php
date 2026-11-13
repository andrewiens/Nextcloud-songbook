<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 Songbook contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Songbook\Tests\Controller;

use OCA\Songbook\Controller\PageController;
use OCA\Songbook\Controller\StatusController;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/** Guard against accidentally opening routes while extending the app. HTTP tests verify enforcement. */
final class SecurityAttributesTest extends TestCase {
	public function testStatusRequiresAuthenticationAndKeepsCsrfProtection(): void {
		$class = new ReflectionClass(StatusController::class);
		$method = new ReflectionMethod(StatusController::class, 'show');

		self::assertEmpty($class->getAttributes(PublicPage::class));
		self::assertEmpty($method->getAttributes(PublicPage::class));
		self::assertEmpty($class->getAttributes(NoCSRFRequired::class));
		self::assertEmpty($method->getAttributes(NoCSRFRequired::class));
		self::assertCount(1, $method->getAttributes(NoAdminRequired::class));
	}

	public function testPageAllowsAuthenticatedNonAdminsAndDirectNavigation(): void {
		$class = new ReflectionClass(PageController::class);
		$method = new ReflectionMethod(PageController::class, 'index');

		self::assertEmpty($class->getAttributes(PublicPage::class));
		self::assertEmpty($method->getAttributes(PublicPage::class));
		self::assertCount(1, $method->getAttributes(NoAdminRequired::class));
		self::assertCount(1, $method->getAttributes(NoCSRFRequired::class));
	}
}
