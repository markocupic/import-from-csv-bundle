<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2015 Leo Feyer
 *
 * @license LGPL-3.0+
 */


/**
 * Register the namespaces
 */
ClassLoader::addNamespaces(array
(
	'MCupic',
));


/**
 * Register the classes
 */
ClassLoader::addClasses(array
(
	// Classes
	'MCupic\\ImportFromCsv\ImportFromCsvHookExample' => 'system/modules/import_from_csv/classes/ImportFromCsvHookExample.php',
	'MCupic\\ImportFromCsv\ImportFromCsv'            => 'system/modules/import_from_csv/classes/ImportFromCsv.php',

	// Models
	'MCupic\ImportFromCsvModel'       => 'system/modules/import_from_csv/models/ImportFromCsvModel.php',
));
