:: Run easy-coding-standard (ecs) via this batch file inside your IDE e.g. PhpStorm (Windows only)
:: Install inside PhpStorm the  "Batch Script Support" plugin
cd..
cd..
cd..
cd..
cd..
cd..
:: src
vendor\bin\ecs check vendor/markocupic/import-from-csv-bundle/src --config vendor/markocupic/import-from-csv-bundle/.ecs/config/default.php
:: tests
vendor\bin\ecs check vendor/markocupic/import-from-csv-bundle/tests --config vendor/markocupic/import-from-csv-bundle/.ecs/config/default.php
:: legacy
vendor\bin\ecs check vendor/markocupic/import-from-csv-bundle/src/Resources/contao --config vendor/markocupic/import-from-csv-bundle/.ecs/config/legacy.php
:: templates
vendor\bin\ecs check vendor/markocupic/import-from-csv-bundle/src/Resources/contao/templates --config vendor/markocupic/import-from-csv-bundle/.ecs/config/template.php
::
cd vendor/markocupic/import-from-csv-bundle/.ecs./batch/check
