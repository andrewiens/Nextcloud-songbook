<?php

declare(strict_types=1);
// SPDX-License-Identifier: AGPL-3.0-or-later
namespace OCA\Songbook\Db;

use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** Only a metadata cache. Never a substitute for filesystem authorization. */
class Catalog {
	public function __construct(private readonly IDBConnection $db) {
	}

	public function libraries(): array {
		$q = $this->db->getQueryBuilder();
		$result = $q->select('*')->from('songbook_libraries')->orderBy('id')->executeQuery();
		try {
			return $result->fetchAllAssociative();
		} finally {
			$result->closeCursor();
		}
	}

	private function find(string $table, array $keys): ?array {
		$q = $this->db->getQueryBuilder();
		$q->select('*')->from($table);
		foreach ($keys as $column => $value) {
			$q->andWhere($q->expr()->eq($column, $q->createNamedParameter($value, IQueryBuilder::PARAM_INT)));
		}
		$result = $q->executeQuery();
		try {
			return $result->fetchAssociative() ?: null;
		} finally {
			$result->closeCursor();
		}
	}

	public function library(int $id): ?array {
		return $this->find('songbook_libraries', ['id' => $id]);
	}

	public function song(int $id): ?array {
		return $this->find('songbook_songs', ['id' => $id]);
	}

	public function indexedSong(int $libraryId, int $fileId): ?array {
		return $this->find('songbook_songs', ['library_id' => $libraryId, 'file_id' => $fileId]);
	}

	private function insert(string $table, array $values): void {
		$q = $this->db->getQueryBuilder();
		$q->insert($table);
		foreach ($values as $column => $value) {
			$q->setValue($column, $q->createNamedParameter($value, is_int($value) ? IQueryBuilder::PARAM_INT : IQueryBuilder::PARAM_STR));
		}
		try {
			$q->executeStatement();
		} catch (Exception $error) {
			// Another request may have indexed/linked the same stable file ID.
			if ($error->getReason() !== Exception::REASON_CONSTRAINT_VIOLATION) {
				throw $error;
			}
		}
	}

	public function createLibrary(int $folderId, string $name, string $uid): array {
		$this->insert('songbook_libraries', ['folder_id' => $folderId, 'name' => $name, 'created_by' => $uid, 'created_at' => time()]);
		return $this->find('songbook_libraries', ['folder_id' => $folderId]) ?? throw new \RuntimeException('Library index unavailable');
	}

	public function saveSong(int $libraryId, int $fileId, array $metadata): array {
		$keys = ['library_id' => $libraryId, 'file_id' => $fileId];
		$this->insert('songbook_songs', $keys + $metadata + ['updated_at' => time()]);
		$q = $this->db->getQueryBuilder();
		$q->update('songbook_songs');
		foreach ($metadata + ['updated_at' => time()] as $column => $value) {
			$q->set($column, $q->createNamedParameter($value, is_int($value) ? IQueryBuilder::PARAM_INT : IQueryBuilder::PARAM_STR));
		}
		foreach ($keys as $column => $value) {
			$q->andWhere($q->expr()->eq($column, $q->createNamedParameter($value, IQueryBuilder::PARAM_INT)));
		}
		$q->executeStatement();
		return $this->indexedSong($libraryId, $fileId) ?? throw new \RuntimeException('Song index unavailable');
	}

	public function invalidate(int $fileId): void {
		$q = $this->db->getQueryBuilder();
		$q->update('songbook_songs')->set('version', $q->createNamedParameter(''))
			->where($q->expr()->eq('file_id', $q->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}
