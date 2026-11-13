<?php

declare(strict_types=1);
// SPDX-License-Identifier: AGPL-3.0-or-later
namespace OCA\Songbook\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version000200Date20261113000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('songbook_libraries')) {
			$table = $schema->createTable('songbook_libraries');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('folder_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('name', Types::STRING, ['length' => 100, 'notnull' => true]);
			$table->addColumn('created_by', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['folder_id'], 'sb_library_folder');
		}
		if (!$schema->hasTable('songbook_songs')) {
			$table = $schema->createTable('songbook_songs');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('library_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('file_id', Types::BIGINT, ['notnull' => true]);
			foreach (['filename' => 255, 'title' => 255, 'artist' => 255, 'musical_key' => 64, 'version' => 255] as $name => $length) {
				$table->addColumn($name, Types::STRING, ['length' => $length, 'notnull' => true, 'default' => '']);
			}
			$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['library_id', 'file_id'], 'sb_song_library_file');
			$table->addIndex(['file_id'], 'sb_song_file');
		}
		return $schema;
	}
}
