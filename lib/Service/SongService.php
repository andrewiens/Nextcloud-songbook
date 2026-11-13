<?php

declare(strict_types=1);
// SPDX-License-Identifier: AGPL-3.0-or-later
namespace OCA\Songbook\Service;

use OCA\Songbook\Db\Catalog;
use OCA\Songbook\Exception\ApiException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Lock\ILockingProvider;

final class SongService {
	public const MAX_ENTRIES = 1000;

	public function __construct(
		private readonly Catalog $catalog,
		private readonly LibraryService $libraries,
		private readonly ChordProService $chordpro,
	) {
	}

	private function describe(array $row): array {
		return [
			'id' => (int)$row['id'], 'fileId' => (int)$row['file_id'], 'filename' => $row['filename'],
			'title' => $row['title'], 'artist' => $row['artist'], 'key' => $row['musical_key'], 'version' => $row['version'],
		];
	}

	private function readContent(File $file): string {
		if (!$file->isReadable()) {
			throw new ApiException('Lied nicht verfügbar.', 404);
		}
		if ($file->getSize() > ChordProService::MAX_BYTES) {
			throw new ApiException('Die Lieddatei überschreitet 256 KiB.', 413);
		}
		$stream = $file->fopen('r');
		if (!is_resource($stream)) {
			throw new ApiException('Die Lieddatei kann nicht gelesen werden.', 503);
		}
		try {
			$content = stream_get_contents($stream, ChordProService::MAX_BYTES + 1);
		} finally {
			fclose($stream);
		}
		if ($content === false) {
			throw new ApiException('Die Lieddatei kann nicht gelesen werden.', 503);
		}
		$this->chordpro->validateContent($content);
		return $content;
	}

	/** The shared lock keeps metadata and source from different versions apart. */
	private function indexFile(int $libraryId, File $file, bool $includeContent = false): array {
		$file->lock(ILockingProvider::LOCK_SHARED);
		try {
			$version = (string)$file->getEtag();
			$filename = $file->getName();
			$row = $this->catalog->indexedSong($libraryId, (int)$file->getId());
			$content = null;
			if ($includeContent || $row === null || $row['version'] !== $version || $row['filename'] !== $filename) {
				$content = $this->readContent($file);
				$row = $this->catalog->saveSong($libraryId, (int)$file->getId(),
					$this->chordpro->metadata($content, $filename) + ['filename' => $filename, 'version' => $version]);
			}
			$song = $this->describe($row);
			if ($includeContent) {
				$song['content'] = $content;
			}
			return $song;
		} finally {
			$file->unlock(ILockingProvider::LOCK_SHARED);
		}
	}

	public function listing(int $libraryId, string $search = '', string $sort = 'title'): array {
		if (strlen($search) > 200 || !mb_check_encoding($search, 'UTF-8') || !in_array($sort, ['title', 'artist', 'key'], true)) {
			throw new ApiException('Ungültige Suche oder Sortierung.', 400);
		}
		$folder = $this->libraries->resolve($libraryId);
		$nodes = $folder->getDirectoryListing();
		if (count($nodes) > self::MAX_ENTRIES) {
			throw new ApiException('Die Bibliothek enthält mehr als 1000 Einträge. Wähle einen kleineren Ordner.', 413);
		}
		$songs = [];
		$warnings = [];
		foreach ($nodes as $node) {
			if (!$node instanceof File || !$node->isReadable() || !$this->chordpro->supported($node->getName())) {
				continue;
			}
			try {
				$song = $this->indexFile($libraryId, $node);
				if ($search === '' || mb_stripos($song['title'] . "\n" . $song['artist'] . "\n" . $song['key'] . "\n" . $song['filename'], $search, 0, 'UTF-8') !== false) {
					$songs[] = $song;
				}
			} catch (ApiException|NotFoundException|NotPermittedException $error) {
				// Filename belongs to the current user's readable directory listing.
				$warnings[] = $node->getName() . ': Datei konnte nicht eingelesen werden (UTF-8 und maximal 256 KiB erforderlich).';
			}
		}
		usort($songs, static fn (array $a, array $b): int => strnatcasecmp($a[$sort], $b[$sort]) ?: ($a['id'] <=> $b['id']));
		return ['songs' => $songs, 'warnings' => $warnings];
	}

	public function read(int $id): array {
		$row = $this->catalog->song($id);
		if ($row === null) {
			throw new ApiException('Lied nicht verfügbar.', 404);
		}
		$folder = $this->libraries->resolve((int)$row['library_id']);
		foreach ($folder->getById((int)$row['file_id']) as $node) {
			if ($node instanceof File && $node->isReadable()
				&& (int)$node->getParent()->getId() === (int)$folder->getId()
				&& $this->chordpro->supported($node->getName())) {
				return ['song' => $this->indexFile((int)$row['library_id'], $node, true)];
			}
		}
		throw new ApiException('Lied nicht verfügbar.', 404);
	}

	public function import(int $libraryId, string $filename, string $content): array {
		$folder = $this->libraries->resolve($libraryId, true);
		$this->chordpro->validateFilename($filename);
		$this->chordpro->validateContent($content);
		$folder->verifyPath($filename);
		$folder->lock(ILockingProvider::LOCK_EXCLUSIVE);
		try {
			if ($folder->nodeExists($filename)) {
				throw new ApiException('Eine Datei mit diesem Namen existiert bereits.', 409);
			}
			if (count($folder->getDirectoryListing()) >= self::MAX_ENTRIES) {
				throw new ApiException('Die Bibliothek ist auf 1000 Einträge begrenzt.', 413);
			}
			$file = $folder->newFile($filename, $content);
		} finally {
			$folder->unlock(ILockingProvider::LOCK_EXCLUSIVE);
		}
		// A failed index update leaves the original file intact; listing retries it.
		return ['song' => $this->indexFile($libraryId, $file, true)];
	}
}
