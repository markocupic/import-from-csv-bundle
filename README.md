# Import from CSV

Backend Modul für Contao 3

Mit dem Modul lassen sich in einem Rutsch über eine csv-Datei massenhaft Datensätze importieren. Sehr praktisch, wenn z.B. sehr viele Benutzer oder Mitglieder generiert werden müssen.
Die csv-Datei wird am besten in einem Tabellenkalkulationsprogramm  (excel o.ä.) erstellt und dann als Kommaseparierte Datei (csv) abgespeichert.
Ein Beispiel für diese Datei findet sich im Verzeichnis import_from_csv/csv/example_csv.csv.

## Warnung!

Achtung! Das Modul bietet einen grossen Nutzen. Der Anwender sollte aber wissen, was er tut, da bei falscher Anwendung Datenbanktabellen "zerschossen" werden können und Contao danach nicht mehr funktionstüchtig ist.

## Einstellungen

### Datentabelle für Import auswählen (Pflichtfeld)

Wählen Sie die Tabelle, in die die Datensätze geschrieben werden sollen.

### Felder für Importvorgang auswählen  (Pflichtfeld)

In der Datenbanktabelle wird nur in die ausgewählten Felder geschrieben. Meistens macht es Sinn, hier alle Felder auszuwählen.

### Felder getrennt von (Pflichtfeld)

Geben Sie an, durch welches Zeichen in der csv-Datei die Feldinhalte voneinander getrennt sind.

### Felder eingeschlossen von (Pflichtfeld)

Kontrollieren Sie, ob in der csv-Datei die Feldinhalte noch zusätzlich von einem Zeichen eingeschlossen sind. Oft ist das das doppelte Anführungszeichen. => "

### Import Modus (Pflichtfeld)
Legen Sie fest, ob die Datensätze aus der csv-Datei in der Zieltabelle angehängt werden oder die Zieltabelle vorher geleert werden soll (alter table). Achtung! Gelöschte Datensätze lassen sich, wenn kein Backup vorhanden, nicht mehr wiederherstellen.

### Datei auswählen (Pflichtfeld)

Abschliessend wählen Sie die Datei aus, von der in die Datenbank geschrieben werden soll.
Tipp: Wenn Sie die Datei ausgewählt haben, klicken Sie voher auf "Speichern" und Sie kriegen eine Vorschau.

## Importmechanismus über Hook anpassen

Mit einem updatesicheren Hook lässt sich die Validierung umgehen oder anpassen. Im folgenden Beispiel sollen die Geokoordinaten beim Import anhand von Strasse, Stadt und Länderkürzel automatisch per Curl-Request von GoogleMaps bezogen werden. Die Koordinaten werden danach in $arrCustomValidation['value'] gespeichert und das Array am Ende der Methode als Methodenrückgabewert zurückgegeben. Auch lassen sich Fehlermeldungen generieren, wenn z.B. keine Geokoordinaten ermittelt werden konnten. Dadurch wird der Datensatz übersprungen und nicht in die Datenbank geschrieben.

Um einen Hook zu nutzen, erstellen Sie folgende Ordner und Dateistruktur:

    system/modules/my_import_from_csv_hook/

        config/

            config.php

            autoload.php

            autoload.ini

        classes/

            MyValidateImportFromCsv.php


In die config.php schreibt man folgendes:
```php
<?php

/**
 * HOOKS
 */
if (TL_MODE == 'BE' && \Input::get('do') == 'import_from_csv')
{
    $GLOBALS['TL_HOOKS']['importFromCsv'][] = array('MCupic\ImportFromCsv\ValidateImportFromCsv', 'addGeolocation');
}

```

In die ValidateImportFromCsv.php schreiben Sie folgendes. In die addGeolocation()-Methode scheiben Sie Ihren Validierungslogik. Die Methode erwartet 2 Parameter und gibt als Rückgabewert ein assoziatives Array mit Feldwert, Fehlermeldung, etc. zurück.

```php
<?php

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2014 Leo Feyer
 * @package import_from_csv
 * @author Marko Cupic 2014, extension sponsered by Rainer-Maria Fritsch - Fast-Doc UG, Berlin
 * @link https://github.com/markocupic/import_from_csv
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

/**
 * Run in a custom namespace, so the class can be replaced
 */
namespace MCupic\ImportFromCsv;

/**
 * Class ImportFromCsvHookExample
 * Copyright: 2014 Marko Cupic Sponsor der Erweiterung: Fast-Doc UG, Berlin
 * @author Marko Cupic <m.cupic@gmx.ch>
 * @package import_from_csv
 */
class ImportFromCsvHookExample extends \System
{

    /**
     * cURL error messages
     */
    public $curlErrorMsg = null;

    /**
     * @param $arrCustomValidation
     * @param null $objBackendModule
     * @return array
     */
    public function addGeolocation($arrCustomValidation, $objBackendModule = null)
    {
        /**
         * $arrCustomValidation = array(
         *
         * 'strTable'               => 'tablename',
         * 'arrDCA'                 => 'Datacontainer array (DCA) of the current field.',
         * 'fieldname'              => 'fieldname',
         * 'value'                  => 'value',
         * 'arrayLine'              => 'Contains the current line/dataset as associative array.',
         * 'line'                   => 'current line in the csv-spreadsheet',
         * 'objCsvFile'             => 'the Contao file object'
         * 'skipWidgetValidation'   => 'Skip widget-input-validation? (default is set to false)',
         * 'hasErrors'              => 'Should be set to true if custom validation fails. (default is set to false)',
         * 'errorMsg'               => 'Define a custom text message if custom validation fails.',
         * 'doNotSave'              => 'Set this item to true if you don't want to save the datarecord into the database. (default is set to false)',
         * );
         */

        // tl_member
        if ($arrCustomValidation['strTable'] == 'tl_member')
        {
            // Get geolocation from a given address
            if ($arrCustomValidation['fieldname'] == 'geolocation')
            {

                // Do custom validation and skip the Contao-Widget-Input-Validation
                $arrCustomValidation['skipWidgetValidation'] = true;

                $strStreet = $arrCustomValidation['arrayLine']['street'];
                $strCity = $arrCustomValidation['arrayLine']['city'];
                $strCountry = $arrCustomValidation['arrayLine']['country'];

                $strStreet = str_replace(' ', '+', $strStreet);
                $strCity = str_replace(' ', '+', $strCity);
                $strAddress = $strStreet . ',+' . $strCity . ',+' . $strCountry;

                // Get Position from GoogleMaps
                $arrPos = $this->curlGetCoordinates(sprintf('http://maps.googleapis.com/maps/api/geocode/json?address=%s&sensor=false', $strAddress));

                if (is_array($arrPos['results'][0]['geometry']))
                {
                    $latPos = $arrPos['results'][0]['geometry']['location']['lat'];
                    $lngPos = $arrPos['results'][0]['geometry']['location']['lng'];

                    // Save geolocation in $arrCustomValidation['value']
                    $arrCustomValidation['value'] = $latPos . ',' . $lngPos;
                }
                else
                {
                    // Error handling
                    if ($this->curlErrorMsg != '')
                    {
                        $arrCustomValidation['errorMsg'] = $this->curlErrorMsg;
                    }
                    else
                    {
                        $arrCustomValidation['errorMsg'] = sprintf("Setting geolocation for (%s) failed!", $strAddress);
                    }
                    $arrCustomValidation['hasErrors'] = true;
                    $arrCustomValidation['doNotSave'] = true;
                }
            }
        }

        return $arrCustomValidation;
    }


    /**
     * Curl helper method
     * @param $url
     * @return bool|mixed
     */
    public function curlGetCoordinates($url)
    {
        // is cURL installed on the webserver?
        if (!function_exists('curl_init'))
        {
            $this->curlErrorMsg = 'Sorry cURL is not installed on your webserver!';
            return false;
        }

        // Set a timout to avoid the OVER_QUERY_LIMIT
        usleep(25000);

        // Create a new cURL resource handle
        $ch = curl_init();

        // Set URL to download
        curl_setopt($ch, CURLOPT_URL, $url);

        // Should cURL return or print out the data? (true = return, false = print)
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Timeout in seconds
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        // Download the given URL, and return output
        $arrOutput = json_decode(curl_exec($ch), true);

        // Close the cURL resource, and free system resources
        curl_close($ch);

        return $arrOutput;
    }
}


```

Damit Contao weiss, wo die Klasse zu finden ist, sollte zum Schluss im Backend für das neu erstellte Modul der Autoload-Creator gestartet werden. Dieser füllt die autoload-Dateien mit dem nötigen Code.
Et voilà!
Viel Spass!!!

