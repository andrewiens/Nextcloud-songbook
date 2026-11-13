<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 Songbook contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Songbook\Controller;

use OCA\Songbook\AppInfo\Application;
use OCA\Songbook\Service\StatusService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

final class StatusController extends OCSController {
	public function __construct(IRequest $request, private readonly StatusService $statusService) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function show(): DataResponse {
		// The framework requires an authenticated user and performs CSRF checks.
		return new DataResponse($this->statusService->getStatus());
	}
}
