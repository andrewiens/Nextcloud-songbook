<?php

declare(strict_types=1);
// SPDX-License-Identifier: AGPL-3.0-or-later
namespace OCA\Songbook\Service;

use OCA\Songbook\Exception\ApiException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Lock\ILockingProvider;

/** Create-only writes using public storage APIs under the authorized folder lock.
 *
 * Folder::newFile() can overwrite and its nested parent lock conflicts with an
 * exclusive folder lock. Never replace exclusive fopen('x') with a 'w' fallback.
 */
final class ImportWriter {
	public function __construct(private readonly IEventDispatcher $events) {
	}

	public function create(Folder $folder, string $filename, string $content): File {
		$folder->verifyPath($filename);
		$folder->lock(ILockingProvider::LOCK_EXCLUSIVE);
		try {
			if (!$folder->isReadable() || !$folder->isCreatable()) {
				throw new ApiException('Keine Berechtigung zum Importieren.', 403);
			}
			$storage = $folder->getStorage();
			$parent = $folder->getInternalPath();
			$path = ($parent === '' ? '' : $parent . '/') . $filename;
			if ($folder->nodeExists($filename) || $storage->file_exists($path)) {
				throw new ApiException('Eine Datei mit diesem Namen existiert bereits.', 409);
			}
			if (count($folder->getDirectoryListing()) >= SongService::MAX_ENTRIES) {
				throw new ApiException('Die Bibliothek ist auf 1000 Einträge begrenzt.', 413);
			}
			$available = $storage->free_space($parent);
			if ($available !== false && $available >= 0 && $available < strlen($content)) {
				throw new ApiException('Nicht genügend freier Speicher.', 507);
			}
			$stream = $storage->fopen($path, 'x');
			if (!is_resource($stream)) {
				throw new ApiException('Der Speicher unterstützt momentan keinen sicheren Import.', 503);
			}
			try {
				$offset = 0;
				while ($offset < strlen($content)) {
					$written = fwrite($stream, substr($content, $offset));
					if ($written === false || $written === 0) {
						throw new ApiException('Die Datei konnte nicht vollständig gespeichert werden.', 507);
					}
					$offset += $written;
				}
				if (!fflush($stream)) {
					throw new ApiException('Die Datei konnte nicht vollständig gespeichert werden.', 507);
				}
			} catch (\Throwable $error) {
				fclose($stream);
				// Only our newly created file, while every Nextcloud writer is locked out.
				$storage->unlink($path);
				$storage->getUpdater()->remove($path);
				throw $error;
			}
			fclose($stream);
			$storage->getUpdater()->update($path);
			$file = $folder->get($filename);
			if (!$file instanceof File) {
				throw new ApiException('Die importierte Datei ist momentan nicht verfügbar.', 503);
			}
		} finally {
			$folder->unlock(ILockingProvider::LOCK_EXCLUSIVE);
		}
		$this->events->dispatchTyped(new NodeCreatedEvent($file));
		$this->events->dispatchTyped(new NodeWrittenEvent($file));
		return $file;
	}
}
