<?php

declare(strict_types=1);
// SPDX-License-Identifier: AGPL-3.0-or-later
namespace OCA\Songbook\Service;

use OCA\Songbook\Exception\ApiException;

final class ChordProService {
	public const MAX_BYTES = 262144;
	public const EXTENSIONS = ['cho', 'pro', 'chordpro'];

	public function supported(string $filename): bool {
		return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), self::EXTENSIONS, true);
	}

	public function validateFilename(string $filename): void {
		if ($filename === '' || strlen($filename) > 255 || trim($filename) !== $filename || $filename[0] === '.'
			|| preg_match('/[\\x00-\\x1f\\x7f\/\\\\]/', $filename) || !mb_check_encoding($filename, 'UTF-8') || !$this->supported($filename)) {
			throw new ApiException('Ungültiger ChordPro-Dateiname. Erlaubt sind .cho, .pro und .chordpro.', 400);
		}
	}

	public function validateContent(string $content): void {
		if (strlen($content) > self::MAX_BYTES) {
			throw new ApiException('Eine Lieddatei darf höchstens 256 KiB groß sein.', 413);
		}
		if (!mb_check_encoding($content, 'UTF-8') || str_contains($content, "\0")) {
			throw new ApiException('ChordPro-Dateien müssen UTF-8-Text ohne Nullbytes enthalten.', 422);
		}
	}

	/** Extract only metadata; the original source is never rewritten. */
	public function metadata(string $content, string $filename): array {
		$this->validateContent($content);
		$fields = ['title' => pathinfo($filename, PATHINFO_FILENAME), 'artist' => '', 'musical_key' => ''];
		$aliases = ['title' => 'title', 't' => 'title', 'artist' => 'artist', 'key' => 'musical_key'];
		$found = [];
		$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
		foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
			if (preg_match('/^\s*\{\s*(title|t|artist|key)\s*:\s*(.*?)\s*\}\s*$/i', $line, $match)) {
				$key = $aliases[strtolower($match[1])];
				if (!isset($found[$key]) && trim($match[2]) !== '') {
					$fields[$key] = trim($match[2]);
					$found[$key] = true;
				}
			}
		}
		foreach ($fields as $name => $value) {
			$fields[$name] = mb_substr($value, 0, $name === 'musical_key' ? 64 : 255, 'UTF-8');
		}
		return $fields;
	}
}
