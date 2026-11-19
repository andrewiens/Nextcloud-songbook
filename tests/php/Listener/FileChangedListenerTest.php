<?php

declare(strict_types=1);
// SPDX-License-Identifier: AGPL-3.0-or-later
namespace OCA\Songbook\Tests\Listener;

use OCA\Songbook\Db\Catalog;
use OCA\Songbook\Listener\FileChangedListener;
use OCP\Files\File;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class FileChangedListenerTest extends TestCase {
	public function testRenameInvalidatesTargetIdWithoutReadingSource(): void {
		$source = $this->createMock(File::class);
		$target = $this->createMock(File::class);
		$target->method('getId')->willReturn(72);
		$target->expects(self::never())->method('getContent');
		$catalog = $this->createMock(Catalog::class);
		$catalog->expects(self::once())->method('invalidate')->with(72);
		(new FileChangedListener($catalog, $this->createMock(LoggerInterface::class)))->handle(new NodeRenamedEvent($source, $target));
	}

	public function testCacheFailureDoesNotBreakFilesOrLogPrivateExceptionText(): void {
		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(72);
		$catalog = $this->createMock(Catalog::class);
		$catalog->method('invalidate')->willThrowException(new \RuntimeException('/private/song/source'));
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())->method('warning')->with('Songbook index invalidation deferred.', ['app' => 'songbook', 'errorType' => \RuntimeException::class]);
		(new FileChangedListener($catalog, $logger))->handle(new NodeWrittenEvent($file));
	}
}
