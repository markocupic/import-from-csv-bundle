<?php

/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/import-from-csv-bundle
 */

// Global operations
$GLOBALS['TL_LANG']['tl_import_from_csv']['new'] = ['Neuen Importdatensatz anlegen', 'Einen neuen Importdatensatz anlegen'];

// Operations
$GLOBALS['TL_LANG']['tl_import_from_csv']['renderAppAction'] =  ['Import mit ID %s durchführen', 'Import mit ID %s durchführen'];

// Legends
$GLOBALS['TL_LANG']['tl_import_from_csv']['title_legend'] = 'Titel Einstellungen';
$GLOBALS['TL_LANG']['tl_import_from_csv']['docs_legend'] = 'HowTo/Hilfe';
$GLOBALS['TL_LANG']['tl_import_from_csv']['settings_legend'] = 'Einstellungen';
$GLOBALS['TL_LANG']['tl_import_from_csv']['limitAndOffset_legend'] = 'Offset und Limit (max_execution_time)';
$GLOBALS['TL_LANG']['tl_import_from_csv']['cron_legend'] = 'Cron Einstellungen';

//Fields
$GLOBALS['TL_LANG']['tl_import_from_csv']['title'] = ['Titel', 'Geben Sie einen Titel ein.'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['importTable'] = ['Datentabelle für Import auswählen', 'Wählen Sie eine Tabelle, in welche die Daten importiert werden sollen, aus.'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['importMode'] = ['Import-Modus', 'Entscheiden Sie, ob die Tabelle vor dem Import gelöscht werden soll oder die Daten an die bestehenden Einträge angehängt werden sollen.'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['fieldEnclosure'] = ['Felder eingeschlossen von', 'Zeichen, von welchem die Felder in der csv-Datei eingeschlossen sind. Normalerweise ein doppeltes Anführungszeichen: => "'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['fieldSeparator'] = ['Felder getrennt von', 'Zeichen, mit dem die Felder in der csv-Datei voneinander getrennt sind. Normalerweise ein Semikolon: => ;'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['selectedFields'] = ['Felder für Importvorgang auswählen.', 'Wählen Sie die Felder aus, die Sie importieren möchten.'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['fileSRC'] = ['CSV-Datei auswählen', 'Wählen Sie eine CSV-Import-Datei aus dem Dateisystem aus.'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['fileContent'] = ['Datei-Inhalt', 'CSV-Datei-Inhalt'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['skipValidationFields'] = ['Validierung für diese Felder überspringen', 'Geben Sie an, für welche Felder die Validierung übersprungen werden soll.'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['offset'] = ['Datensätze überspringen', 'Geben Sie an, wie viele Datensätze beim Import übersprungen werden sollen.'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['limit'] = ['Datensätze limitieren', 'Geben Sie an, wie viele Datensätze importiert werden sollen (0=alle).'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['enableCron'] = ['Cron aktivieren', 'Aktivieren Sie den CSV-Import als Cronjob.'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['cronLevel'] = ['Cron Level', 'Bestimmen Sie, in welchem Intervall die Cronjobs durchgeführt werden sollen.'];

// References
$GLOBALS['TL_LANG']['tl_import_from_csv']['truncate_table'] = ['Tabelle vor dem Import löschen'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['append_entries'] = ['Datensätze nur anhängen'];

// Buttons
$GLOBALS['TL_LANG']['tl_import_from_csv']['runImportBtn'] = 'Importvorgang starten';
$GLOBALS['TL_LANG']['tl_import_from_csv']['testRunImportBtn'] = 'Importvorgang im Testmodus starten';
$GLOBALS['TL_LANG']['tl_import_from_csv']['showErrorsBtn'] = 'Zeige nur die Fehler';
$GLOBALS['TL_LANG']['tl_import_from_csv']['showAllBtn'] = 'Zeige alle';
$GLOBALS['TL_LANG']['tl_import_from_csv']['btnImport'] = 'Import starten';
$GLOBALS['TL_LANG']['tl_import_from_csv']['btnImportTest'] = 'Import testen';
$GLOBALS['TL_LANG']['tl_import_from_csv']['editItemTitle'] = 'Import bearbeiten';

// Messages
$GLOBALS['TL_LANG']['tl_import_from_csv']['datarecords'] = 'Anzahl Datensätze';
$GLOBALS['TL_LANG']['tl_import_from_csv']['successfulInserts'] = 'Erfolgreiche inserts';
$GLOBALS['TL_LANG']['tl_import_from_csv']['failedInserts'] = 'Missglückte inserts';
$GLOBALS['TL_LANG']['tl_import_from_csv']['infoText'] = '<span>Eine Einführung und viele Tipps finden sich auf der <a href="https://github.com/markocupic/import-from-csv-bundle">Projektwebseite</a>.</span>';
$GLOBALS['TL_LANG']['tl_import_from_csv']['importOverview'] = 'Import-Übersicht';
$GLOBALS['TL_LANG']['tl_import_from_csv']['datarecordInsertFailed'] = 'Datensatz konnte nicht angelegt werden!';
$GLOBALS['TL_LANG']['tl_import_from_csv']['datarecordInsertSucceed'] = 'Datensatz erfolgreich angelegt!';
$GLOBALS['TL_LANG']['tl_import_from_csv']['confirmStartImport'] = 'Sind Sie sicher, dass Sie den Importprozess starten möchten?';
$GLOBALS['TL_LANG']['tl_import_from_csv']['exceptionMsg'] = 'Während des Importvorganges ist es zu einem unerwarteten Fehler gekommen. Bitte wechseln Sie in den Contao Debug-Modus, um mehr zu erfahren.';
$GLOBALS['TL_LANG']['tl_import_from_csv']['importProcessCompleted'] = 'Importvorgang abgeschlossen. Sie dürfen das Fenster jetzt schliessen.';
$GLOBALS['TL_LANG']['tl_import_from_csv']['importProcessStarted'] = 'Importvorgang gestartet. Bitte dieses Fenster nicht schliessen, bevor der Import nicht abgeschlossen ist.';
$GLOBALS['TL_LANG']['tl_import_from_csv']['loadingApplication'] = 'lade App';
