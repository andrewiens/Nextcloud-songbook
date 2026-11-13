<?php

declare(strict_types=1);
// SPDX-License-Identifier: AGPL-3.0-or-later
namespace OCA\Songbook\Controller;

use OCA\Songbook\AppInfo\Application;
use OCA\Songbook\Exception\ApiException;
use OCA\Songbook\Service\LibraryService;
use OCA\Songbook\Service\SongService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\NotEnoughSpaceException;
use OCP\IRequest;
use OCP\Lock\LockedException;
use Psr\Log\LoggerInterface;

final class LibraryController extends OCSController {
	public function __construct(IRequest $request, private readonly LibraryService $libraries, private readonly SongService $songs, private readonly LoggerInterface $logger) {
		parent::__construct(Application::APP_ID, $request);
	}

	private function number(mixed $value, bool $allowZero = false): int {
		if ((!is_int($value) && !is_string($value)) || filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < ($allowZero ? 0 : 1)) {
			throw new ApiException('Ungültige ID.', 400);
		}
		return (int)$value;
	}

	private function text(mixed $value): string {
		if (!is_string($value)) {
			throw new ApiException('Textwert erwartet.', 400);
		}
		return $value;
	}

	private function execute(callable $operation): DataResponse {
		try {
			return new DataResponse($operation());
		} catch (ApiException $error) {
			throw $error;
		} catch (NotFoundException $error) {
			throw new ApiException('Datei oder Ordner nicht verfügbar.', 404);
		} catch (NotPermittedException $error) {
			throw new ApiException('Keine Berechtigung für diese Dateioperation.', 403);
		} catch (InvalidPathException $error) {
			throw new ApiException('Ungültiger Dateiname.', 400);
		} catch (LockedException $error) {
			throw new ApiException('Datei oder Ordner ist momentan gesperrt. Bitte erneut versuchen.', 409);
		} catch (NotEnoughSpaceException $error) {
			throw new ApiException('Nicht genügend freier Speicher.', 507);
		} catch (\Throwable $error) {
			// No exception message/stack: these may contain paths or source text.
			$this->logger->error('Songbook catalog operation failed.', ['app' => Application::APP_ID, 'errorType' => get_class($error)]);
			throw new ApiException('Die Bibliothek ist momentan nicht verfügbar. Bitte erneut versuchen.', 503);
		}
	}

	#[NoAdminRequired]
	public function folders(mixed $folderId = 0): DataResponse {
		return $this->execute(fn () => $this->libraries->folders($this->number($folderId, true)));
	}

	#[NoAdminRequired]
	public function index(): DataResponse {
		return $this->execute(fn () => $this->libraries->listing());
	}

	#[NoAdminRequired]
	public function create(mixed $name = '', mixed $folderId = null): DataResponse {
		return $this->execute(fn () => $this->libraries->create($this->text($name), $this->number($folderId)));
	}

	#[NoAdminRequired]
	public function songs(mixed $libraryId = null, mixed $search = '', mixed $sort = 'title'): DataResponse {
		return $this->execute(fn () => $this->songs->listing($this->number($libraryId), $this->text($search), $this->text($sort)));
	}

	#[NoAdminRequired]
	public function show(mixed $id = null): DataResponse {
		return $this->execute(fn () => $this->songs->read($this->number($id)));
	}

	#[NoAdminRequired]
	public function import(mixed $libraryId = null, mixed $filename = '', mixed $content = ''): DataResponse {
		return $this->execute(fn () => $this->songs->import($this->number($libraryId), $this->text($filename), $this->text($content)));
	}

	#[NoAdminRequired]
	public function sync(mixed $id = null): DataResponse {
		return $this->execute(fn () => $this->songs->listing($this->number($id)));
	}
}
