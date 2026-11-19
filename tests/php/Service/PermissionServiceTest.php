<?php

declare(strict_types=1);
// SPDX-License-Identifier: AGPL-3.0-or-later
namespace OCA\Songbook\Tests\Service;

use OCA\Songbook\Exception\ApiException;
use OCA\Songbook\Service\PermissionService;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class PermissionServiceTest extends TestCase {
	private function service(array $nodes): PermissionService {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('current-band-member');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$home = $this->createMock(Folder::class);
		$home->method('getId')->willReturn(10);
		$home->expects(self::once())->method('getById')->with(42)->willReturn($nodes);
		$root = $this->createMock(IRootFolder::class);
		$root->expects(self::once())->method('getUserFolder')->with('current-band-member')->willReturn($home);
		$root->expects(self::never())->method('getById');
		return new PermissionService($root, $session);
	}

	public function testReadsOnlyFromCurrentUsersMounts(): void {
		$folder = $this->createMock(Folder::class);
		$folder->method('isReadable')->willReturn(true);
		$folder->method('isCreatable')->willReturn(false);
		self::assertSame($folder, $this->service([$folder])->folder(42));
	}

	public function testReadOnlyShareCannotImport(): void {
		$folder = $this->createMock(Folder::class);
		$folder->method('isReadable')->willReturn(true);
		$folder->method('isCreatable')->willReturn(false);
		$this->expectException(ApiException::class);
		$this->expectExceptionCode(403);
		$this->service([$folder])->folder(42, true);
	}

	public function testUsesWritableAliasWhenSameFolderHasMultipleMounts(): void {
		$reader = $this->createMock(Folder::class);
		$reader->method('isReadable')->willReturn(true);
		$writer = $this->createMock(Folder::class);
		$writer->method('isReadable')->willReturn(true);
		$writer->method('isCreatable')->willReturn(true);
		self::assertSame($writer, $this->service([$reader, $writer])->folder(42, true));
	}

	public function testKnownIdWithoutCurrentShareIsUnavailable(): void {
		$this->expectException(ApiException::class);
		$this->expectExceptionCode(404);
		$this->service([])->folder(42);
	}

	public function testRequiresAnAuthenticatedSession(): void {
		$root = $this->createMock(IRootFolder::class);
		$root->expects(self::never())->method('getUserFolder');
		$this->expectException(ApiException::class);
		$this->expectExceptionCode(401);
		(new PermissionService($root, $this->createMock(IUserSession::class)))->home();
	}
}
