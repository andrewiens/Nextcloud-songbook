<?php

declare(strict_types=1);
// SPDX-License-Identifier: AGPL-3.0-or-later
namespace OCA\Songbook\Listener;

use OCA\Songbook\Db\Catalog;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use Psr\Log\LoggerInterface;

/** Invalidate cached metadata only; never open another user's file from an event. */
final class FileChangedListener implements IEventListener {
	public function __construct(private readonly Catalog $catalog, private readonly LoggerInterface $logger) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof NodeWrittenEvent && !$event instanceof NodeRenamedEvent && !$event instanceof NodeDeletedEvent) {
			return;
		}
		try {
			$node = $event instanceof NodeRenamedEvent ? $event->getTarget() : $event->getNode();
			$id = $node->getId();
			if ($id !== null) {
				$this->catalog->invalidate((int)$id);
			}
		} catch (\Throwable $error) {
			// A rebuildable cache must never break a Files write or delete. The next
			// listing also checks actual file IDs, names, etags and permissions.
			$this->logger->warning('Songbook index invalidation deferred.', ['app' => 'songbook', 'errorType' => get_class($error)]);
		}
	}
}
