<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 Songbook contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
	],
	'ocs' => [
		['name' => 'status#show', 'url' => '/api/v1/status', 'verb' => 'GET'],
	],
];
