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
		['name' => 'library#folders', 'url' => '/api/v1/folders', 'verb' => 'GET'],
		['name' => 'library#index', 'url' => '/api/v1/libraries', 'verb' => 'GET'],
		['name' => 'library#create', 'url' => '/api/v1/libraries', 'verb' => 'POST'],
		['name' => 'library#sync', 'url' => '/api/v1/libraries/{id}/sync', 'verb' => 'POST'],
		['name' => 'library#songs', 'url' => '/api/v1/songs', 'verb' => 'GET'],
		['name' => 'library#import', 'url' => '/api/v1/songs/import', 'verb' => 'POST'],
		['name' => 'library#show', 'url' => '/api/v1/songs/{id}', 'verb' => 'GET'],
	],
];
