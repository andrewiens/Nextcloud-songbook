<?php

declare(strict_types=1);
// SPDX-License-Identifier: AGPL-3.0-or-later
namespace OCA\Songbook\Tests\Service;

use OCA\Songbook\Exception\ApiException;
use OCA\Songbook\Service\ChordProService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ChordProServiceTest extends TestCase {
	public function testReadsAliasesAndFirstNonemptyMetadataWithoutChangingTheSource(): void {
		$source = "\xEF\xBB\xBF{t: }\r\n{t: Grüße}\r\n{title: Second title}\r\n{artist: Band}\r\n{KEY: F#m}\r\n{future: <script>}\r\nA [D]line\r\n";
		$original = $source;
		self::assertSame(['title' => 'Grüße', 'artist' => 'Band', 'musical_key' => 'F#m'],
			(new ChordProService())->metadata($source, 'original.CHO'));
		self::assertSame($original, $source);
	}

	public function testMissingTitleUsesFilenameAndMetadataKeepsUtf8Boundaries(): void {
		$parser = new ChordProService();
		self::assertSame('Mein Lied', $parser->metadata('[G]Text', 'Mein Lied.pro')['title']);
		$fields = $parser->metadata('{title: ' . str_repeat('ä', 300) . "}\n{key: " . str_repeat('ö', 80) . '}', 'song.cho');
		self::assertSame(str_repeat('ä', 255), $fields['title']);
		self::assertSame(str_repeat('ö', 64), $fields['musical_key']);
	}

	public static function invalidNames(): array {
		return array_map(static fn ($value) => [$value], ['', '../song.cho', 'sub/song.cho', 'sub\\song.cho', '.song.cho', "bad\0.cho", "bad\x7f.cho", "bad\xff.cho", 'song.txt', ' song.cho', 'song.cho ', str_repeat('ä', 126) . '.cho']);
	}

	#[DataProvider('invalidNames')]
	public function testRejectsUnsafeImportNames(string $filename): void {
		$this->expectException(ApiException::class);
		$this->expectExceptionCode(400);
		(new ChordProService())->validateFilename($filename);
	}

	public static function invalidContent(): array {
		return [['', 422], ["bad\0text", 422], ["\xff\xfe", 422], [str_repeat('x', ChordProService::MAX_BYTES + 1), 413]];
	}

	#[DataProvider('invalidContent')]
	public function testRejectsInvalidOrOversizedContent(string $source, int $code): void {
		$this->expectException(ApiException::class);
		$this->expectExceptionCode($code);
		(new ChordProService())->validateContent($source);
	}

	public function testAcceptsTheExactSizeLimitAndAllThreeExtensions(): void {
		$parser = new ChordProService();
		$parser->validateContent(str_repeat('x', ChordProService::MAX_BYTES));
		foreach (['Lied.cho', 'Lied.PRO', 'Lied.chordpro'] as $name) {
			$parser->validateFilename($name);
			self::assertTrue($parser->supported($name));
		}
	}
}
