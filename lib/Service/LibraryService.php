<?php

declare(strict_types=1);
// SPDX-License-Identifier: AGPL-3.0-or-later
namespace OCA\Songbook\Service;

use OCA\Songbook\Db\Catalog;
use OCA\Songbook\Exception\ApiException;
use OCP\Files\Folder;

final class LibraryService {
	public function __construct(private readonly Catalog $catalog, private readonly PermissionService $permissions) {
	}

	public function folders(int $folderId): array {
		$folder = $this->permissions->folder($folderId);
		$home = $this->permissions->home();
		$describe = static fn (Folder $node): array => [
			'id' => (int)$node->getId(), 'name' => $node->getName(),
			'path' => $home->getRelativePath($node->getPath()) ?: '/',
		];
		$children = $folder->getDirectoryListing('httpd/unix-directory');
		if (count($children) > SongService::MAX_ENTRIES) {
			throw new ApiException('Dieser Ordner enthält zu viele Unterordner. Wähle einen kleineren Ordner.', 413);
		}
		$folders = [];
		foreach ($children as $child) {
			if ($child instanceof Folder && $child->isReadable()) {
				$folders[] = $describe($child);
			}
		}
		usort($folders, static fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
		return ['folder' => $describe($folder), 'folders' => $folders];
	}

	public function describe(array $row, Folder $folder): array {
		return ['id' => (int)$row['id'], 'name' => $row['name'], 'folderId' => (int)$row['folder_id'], 'canImport' => (bool)$folder->isCreatable()];
	}

	public function listing(): array {
		$visible = [];
		foreach ($this->catalog->libraries() as $row) {
			try {
				$visible[] = $this->describe($row, $this->permissions->folder((int)$row['folder_id']));
			} catch (ApiException $error) {
				if ($error->getCode() !== 404) {
					throw $error;
				}
			}
		}
		return ['libraries' => $visible];
	}

	public function create(string $name, int $folderId): array {
		$name = trim($name);
		if ($name === '' || !mb_check_encoding($name, 'UTF-8') || mb_strlen($name, 'UTF-8') > 100 || preg_match('/[\x00-\x1f\x7f]/', $name)) {
			throw new ApiException('Der Bibliotheksname muss 1 bis 100 Zeichen enthalten.', 400);
		}
		$folder = $this->permissions->folder($folderId, true);
		if ((int)$folder->getId() === (int)$this->permissions->home()->getId()) {
			throw new ApiException('Wähle einen eigenen Bibliotheks-Unterordner.', 400);
		}
		$row = $this->catalog->createLibrary((int)$folder->getId(), $name, $this->permissions->uid());
		return ['library' => $this->describe($row, $folder)];
	}

	public function resolve(int $id, bool $write = false): Folder {
		$row = $this->catalog->library($id);
		if ($row === null) {
			throw new ApiException('Bibliothek nicht verfügbar.', 404);
		}
		return $this->permissions->folder((int)$row['folder_id'], $write);
	}
}
