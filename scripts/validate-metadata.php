<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

if ($argc !== 2 || !is_file($argv[1])) {
	fwrite(STDERR, "Usage: php scripts/validate-metadata.php /path/to/official-info.xsd\n");
	exit(2);
}

libxml_use_internal_errors(true);
$document = new DOMDocument();
$loaded = $document->load(__DIR__ . '/../appinfo/info.xml', LIBXML_NONET);
$valid = $loaded && $document->schemaValidate($argv[1]);
if (!$valid) {
	foreach (libxml_get_errors() as $error) {
		fwrite(STDERR, sprintf("Line %d: %s", $error->line, $error->message));
	}
	exit(1);
}

fwrite(STDOUT, "appinfo/info.xml conforms to the supplied official XSD.\n");
