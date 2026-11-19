<?php

declare(strict_types=1);
// SPDX-License-Identifier: AGPL-3.0-or-later
namespace OCA\Songbook\Tests\Service;

use OCA\Songbook\Exception\ApiException;
use OCA\Songbook\Service\ImportWriter;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Folder;
use OCP\Files\Storage\IStorage;
use OCP\Lock\ILockingProvider;
use PHPUnit\Framework\TestCase;

final class ImportWriterTest extends TestCase {
	private function folder(IStorage $storage): Folder {
		$folder = $this->createMock(Folder::class);
		$folder->method('isReadable')->willReturn(true);
		$folder->method('isCreatable')->willReturn(true);
		$folder->method('getInternalPath')->willReturn('band');
		$folder->method('getStorage')->willReturn($storage);
		$folder->method('getDirectoryListing')->willReturn([]);
		$folder->expects(self::once())->method('lock')->with(ILockingProvider::LOCK_EXCLUSIVE);
		$folder->expects(self::once())->method('unlock')->with(ILockingProvider::LOCK_EXCLUSIVE);
		return $folder;
	}

	public function testChecksActualStorageEvenWhenFileCacheMissesAnExistingFile(): void {
		$storage = $this->createMock(IStorage::class);
		$storage->method('file_exists')->with('band/song.cho')->willReturn(true);
		$storage->expects(self::never())->method('fopen');
		$storage->expects(self::never())->method('unlink');
		$this->expectException(ApiException::class);
		$this->expectExceptionCode(409);
		(new ImportWriter($this->createMock(IEventDispatcher::class)))->create($this->folder($storage), 'song.cho', 'source');
	}

	public function testUnsupportedExclusiveCreationNeverFallsBackToOverwriting(): void {
		$storage = $this->createMock(IStorage::class);
		$storage->method('free_space')->willReturn(1000);
		$storage->expects(self::once())->method('fopen')->with('band/song.cho', 'x')->willReturn(false);
		$storage->expects(self::never())->method('file_put_contents');
		$storage->expects(self::never())->method('unlink');
		$this->expectException(ApiException::class);
		$this->expectExceptionCode(503);
		(new ImportWriter($this->createMock(IEventDispatcher::class)))->create($this->folder($storage), 'song.cho', 'source');
	}

	public function testInsufficientQuotaIsRejectedBeforeCreatingAnything(): void {
		$storage = $this->createMock(IStorage::class);
		$storage->method('free_space')->willReturn(2);
		$storage->expects(self::never())->method('fopen');
		$this->expectException(ApiException::class);
		$this->expectExceptionCode(507);
		(new ImportWriter($this->createMock(IEventDispatcher::class)))->create($this->folder($storage), 'song.cho', 'source');
	}
}
