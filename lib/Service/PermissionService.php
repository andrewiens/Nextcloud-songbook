<?php

declare(strict_types=1);
// SPDX-License-Identifier: AGPL-3.0-or-later
namespace OCA\Songbook\Service;

use OCA\Songbook\Exception\ApiException;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IUserSession;

class PermissionService {
	public function __construct(private readonly IRootFolder $root, private readonly IUserSession $session) {
	}

	public function uid(): string {
		return $this->session->getUser()?->getUID() ?? throw new ApiException('Anmeldung erforderlich.', 401);
	}

	public function home(): Folder {
		return $this->root->getUserFolder($this->uid());
	}

	public function folder(int $id, bool $write = false): Folder {
		$home = $this->home();
		$nodes = $id === 0 || (int)$home->getId() === $id ? [$home] : $home->getById($id);
		$readable = null;
		foreach ($nodes as $node) {
			if ($node instanceof Folder && $node->isReadable()) {
				if ($node->isCreatable()) {
					return $node;
				}
				$readable ??= $node;
			}
		}
		if ($readable !== null && !$write) {
			return $readable;
		}
		throw new ApiException($readable ? 'Keine Berechtigung zum Importieren in diesen Ordner.' : 'Ordner nicht verfügbar.', $readable ? 403 : 404);
	}
}
