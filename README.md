![Logo](https://github.com/markocupic/markocupic/blob/main/logo.png)

# Import from CSV (Backend Modul für Contao 4.x)

Mit dem Modul lassen sich in einem Rutsch über eine CSV Datei massenhaft Datensätze importieren.
  Sehr praktisch, wenn z.B. sehr viele Benutzer oder Mitglieder generiert werden müssen.
Die CSV Datei wird am besten in einem Tabellenkalkulationsprogramm  (MS-EXCEL o.ä.) erstellt
  und dann als kommaseparierte Datei (CSV) abgespeichert.
Ein Beispiel für diese Datei findet sich im Verzeichnis [docs](docs/import-file.csv).

https://user-images.githubusercontent.com/1525166/150694067-e4438409-d6b0-42c7-853b-1b273a2f5064.mp4

## Warnung!
Achtung! Das Modul bietet einen grossen Nutzen.
  Der Anwender sollte aber wissen, was er tut, da bei falscher Anwendung Datensätze gelöscht oder
  unbrauchbar gemacht werden können und Contao danach nicht mehr funktionstüchtig ist.

## Aufbau CSV-Importdatei
Mit MS-Excel oder einem Texteditor lässt sich eine (kommaseparierte) Textdatei anlegen (csv).
  In die erste Zeile gehören zwingend die Feldnamen. Die einzelnen Felder müssen durch ein Trennzeichen
  (üblicherweise das Semikolon ";") abgegrenzt werden. Feldinhalt, der in der Datenbank als serialisiertes
  Array abgelegt wird (z.B. Gruppenzugehörigkeiten, Newsletter-Abos, etc.), muss durch zwei aufeinanderfolgende
  pipe-Zeichen abgegrenzt werden z.B. "2||5". Feldbegrenzer und Feldtrennzeichen können individuell festgelegt werden.

Wichtig! Jeder Datensatz gehört in eine neue Zeile. Zeilenumbrüche im Datensatz verunmöglichen den Import.
 Die erstellte csv-Datei muss über die Dateiverwaltung auf den Webserver geladen werden und kann nachher bei
 der Import-Konfiguration ausgewählt werden.

Beim Importvorgang werden die Inhalte auf Gültigkeit überprüft. Als Grundlage dienen die DCA-Settings der Zieltabelle.

**Achtung! Das Modul sollte nur genutzt werden, wenn man sich seiner Sache sehr sicher ist. Gelöschte Daten können nur wiederhergestellt werden, wenn vorher ein Datenbankbackup erstellt worden ist.**


## Einstellungen


### Kommaseparierte Datei erstellen und hochladen
Als Erstes muss eine CSV-Datei erstellt werden. In die Kopfzeile gehören die Feldnamen.

```
firstname;lastname;dateOfBirth;gender;company;street;postal;city;state;country;phone;mobile;fax;email;website;language;login;username;password;groups
Hans;Meier;1778-05-22;male;Webdesign AG;Ringelnatterweg 1;6208;Oberkirch;Kanton Luzern;ch;041 921 99 97;079 620 99 91;045 789 56 89;h-meier@me.ch;www.hans-meier.ch;de;1;hansmeier;topsecret;1||2
Fritz;Nimmersatt;1978-05-29;male;Webdesign AG;Entenweg 10;6208;Oberkirch;Kanton Luzern;ch;041 921 99 98;079 620 99 92;046 789 56 89;f-nimmersatt@me.ch;www.fritz-nimmersatt.ch;de;1;fritznimmer;topsecret2;1||2
Annina;Meile;1878-05-29;female;Webdesign AG;Nashornstrasse 2;6208;Oberkirch;Kanton Luzern;ch;043 921 99 99;079 620 93 91;047 789 56 89;a-meile@me.ch;www.annina-meile.ch;de;1;anninameile;topsecret3;1
```

### Datentabelle für Import auswählen (Pflichtfeld)
Wählen Sie die Tabelle, in die die Datensätze importiert werden sollen.

### Felder für Importvorgang auswählen  (Pflichtfeld)
In der Datenbanktabelle wird nur in die ausgewählten Felder geschrieben. Meistens macht es Sinn, hier alle Felder auszuwählen.

### Felder getrennt von (Pflichtfeld)
Geben Sie an, durch welches Zeichen in der CSV Datei die Feldinhalte voneinander getrennt sind.

### Felder eingeschlossen von (Pflichtfeld)
Kontrollieren Sie, ob in der CSV Datei die Feldinhalte noch zusätzlich von einem Zeichen eingeschlossen sind. Oft ist das das doppelte Anführungszeichen. => "

### Import Modus (Pflichtfeld)
Legen Sie fest, ob die Datensätze aus der CSV Datei in der Zieltabelle angehängt werden oder die Zieltabelle vorher
  geleert werden soll (alter table). Achtung! Gelöschte Datensätze lassen sich, wenn kein Backup vorhanden ist, nicht mehr wiederherstellen.

### Datei auswählen (Pflichtfeld)
Abschliessend wählen Sie die Datei aus, aus der in die Datenbank geschrieben werden soll.
Tipp: Wenn Sie die Datei ausgewählt haben, klicken Sie voher auf "Speichern" um eine Vorschau des Dateiinhalts zu bekommen.

### Zeilenumbrüche
Alle [NEWLINE] tags in der CSV Datei werden beim Import-Vorgang in \r\n bzw. \n umgewandelt.

### Cronjob
Auf Wunsch kann CRON aktiviert werden. Der Import kann dadurch in einem festgelegten Intervall automatisch ausgeführt werden.

## Importmechanismus über Hook anpassen
Mit einem updatesicheren Hook lässt sich die Validierung umgehen oder anpassen. Im folgenden Beispiel sollen die Geokoordinaten beim Import anhand von Strasse, Stadt und Länderkürzel automatisch per Curl-Request von GoogleMaps bezogen werden. Auch lassen sich Fehlermeldungen generieren, wenn z.B. keine Geokoordinaten ermittelt werden konnten. Dadurch wird der Datensatz übersprungen und nicht in die Datenbank geschrieben.


Aufbau einer möglichen Hook-Klasse:

```php

<?php

// src/EventListener/MyImportFromCsvHook.php

declare(strict_types=1);

namespace App\EventListener;

use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\Widget;
use Markocupic\ImportFromCsvBundle\Import\ImportFromCsv;

/**
 * @Hook(MyImportFromCsvHook::HOOK, priority=MyImportFromCsvHook::PRIORITY)
 */
class MyImportFromCsvHook
{
    public const HOOK = 'importFromCsv';
    public const PRIORITY = 100;

    /**
     * @var string
     */
    private $curlErrorMsg;

    public function __invoke(Widget $objWidget, array $arrRecord, int $line, ImportFromCsv $importFromCsv = null): void
    {
        // tl_member
        if ('tl_super_member' === $objWidget->strTable) {
            // Get geolocation from a given address
            if ('geolocation' === $objWidget->strField) {
                // Do custom validation and skip the Contao-Widget-Input-Validation
                $arrSkip = $importFromCsv->getData('arrSkipValidationFields');
                $arrSkip[] = $objWidget->strField;
                $importFromCsv->setData('arrSkipValidationFields', $arrSkip);

                $strStreet = $arrRecord['street'];
                $strCity = $arrRecord['city'];
                $strCountry = $arrRecord['country'];

                $strStreet = str_replace(' ', '+', $strStreet);
                $strCity = str_replace(' ', '+', $strCity);
                $strAddress = $strStreet.',+'.$strCity.',+'.$strCountry;

                // Get Position from GoogleMaps
                $arrPos = $this->curlGetCoordinates(sprintf('https://maps.googleapis.com/maps/api/geocode/json?address=%s&sensor=false', $strAddress));

                if (null !== $arrPos && \is_array($arrPos['results'][0]['geometry'])) {
                    $latPos = $arrPos['results'][0]['geometry']['location']['lat'];
                    $lngPos = $arrPos['results'][0]['geometry']['location']['lng'];

                    $objWidget->value = $latPos.','.$lngPos;
                } else {
                    // Error handling
                    if ('' !== $this->curlErrorMsg) {
                        $objWidget->addError($this->curlErrorMsg);
                    } else {
                        $objWidget->addError(sprintf('Setting geolocation for (%s) failed!', $strAddress));
                    }
                }
            }
        }
    }

    /**
     * Curl helper method.
     */
    private function curlGetCoordinates(string $url): ?array
    {
        // is cURL installed on the webserver?
        if (!\function_exists('curl_init')) {
            $this->curlErrorMsg = 'Sorry cURL is not installed on your webserver!';

            return null;
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



Viel Spass mit "Import From CSV Bundle"!!!

