<?php

/**
 * Import from csv bundle: Backend module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package import-from-csv-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/resource-booking-bundle
 */

$GLOBALS['TL_LANG']['tl_import_from_csv']['manual'] = "Anleitung/Hilfe";
$GLOBALS['TL_LANG']['tl_import_from_csv']['settings'] = "Einstellungen";
$GLOBALS['TL_LANG']['tl_import_from_csv']['limitAndOffset_settings'] = "Offset und Limit (max_execution_time)";
$GLOBALS['TL_LANG']['tl_import_from_csv']['cron_settings'] = "Cron Einstellungen";

$GLOBALS['TL_LANG']['tl_import_from_csv']['import_table'][0] = "Datentabelle für Import auswählen";
$GLOBALS['TL_LANG']['tl_import_from_csv']['import_table'][1] = "Wählen Sie eine Tabelle, in welche die Daten importiert werden sollen, aus.";
$GLOBALS['TL_LANG']['tl_import_from_csv']['import_mode'][0] = "Import-Modus";
$GLOBALS['TL_LANG']['tl_import_from_csv']['import_mode'][1] = "Entscheiden Sie, ob die Tabelle vor dem Import gelöscht werden soll oder die Daten an die bestehenden Einträge angehängt werden sollen.";
$GLOBALS['TL_LANG']['tl_import_from_csv']['field_enclosure'][0] = "Felder eingeschlossen von";
$GLOBALS['TL_LANG']['tl_import_from_csv']['field_enclosure'][1] = "Zeichen, von welchem die Felder in der csv-Datei eingeschlossen sind. Normalerweise ein doppeltes Anführungszeichen: => \"";
$GLOBALS['TL_LANG']['tl_import_from_csv']['field_separator'][0] = "Felder getrennt von";
$GLOBALS['TL_LANG']['tl_import_from_csv']['field_separator'][1] = "Zeichen, mit dem die Felder in der csv-Datei voneinander getrennt sind. Normalerweise ein Semikolon: => ;";
$GLOBALS['TL_LANG']['tl_import_from_csv']['selected_fields'][0] = "Felder für Importvorgang auswählen.";
$GLOBALS['TL_LANG']['tl_import_from_csv']['fileSRC'][0] = "csv-Datei auswählen";
$GLOBALS['TL_LANG']['tl_import_from_csv']['fileContent'][0] = "Datei-Inhalt";
$GLOBALS['TL_LANG']['tl_import_from_csv']['truncate_table'][0] = "Tabelle vor dem Import löschen";
$GLOBALS['TL_LANG']['tl_import_from_csv']['append_entries'][0] = "Datensätze nur anhängen";
$GLOBALS['TL_LANG']['tl_import_from_csv']['new'][0] = "Neuen Importdatensatz anlegen";
$GLOBALS['TL_LANG']['tl_import_from_csv']['new'][1] = "Einen neuen Importdatensatz anlegen";
$GLOBALS['TL_LANG']['tl_import_from_csv']['runImportButton'] = "Importvorgang starten";
$GLOBALS['TL_LANG']['tl_import_from_csv']['testRunImportButton'] = "Importvorgang im Testmodus starten";
$GLOBALS['TL_LANG']['tl_import_from_csv']['showErrorsButton'] = 'Zeige nur die Fehler';
$GLOBALS['TL_LANG']['tl_import_from_csv']['showAllButton'] = 'Zeige alle';
$GLOBALS['TL_LANG']['tl_import_from_csv']['datarecords'] = "Anzahl Datensätze";
$GLOBALS['TL_LANG']['tl_import_from_csv']['successfullInserts'] = "Erfolgreiche inserts";
$GLOBALS['TL_LANG']['tl_import_from_csv']['failedInserts'] = "Missglückte inserts";
$GLOBALS['TL_LANG']['tl_import_from_csv']['skipValidationFields'][0] = "Validierung für diese Felder überspringen";
$GLOBALS['TL_LANG']['tl_import_from_csv']['skipValidationFields'][1] = "Geben Sie an für welche Felder die Validierung übersprungen werden soll.";
$GLOBALS['TL_LANG']['tl_import_from_csv']['offset'][0] = "Datensätze überspringen";
$GLOBALS['TL_LANG']['tl_import_from_csv']['offset'][1] = "Geben Sie an, wie viele Datensätze beim Import übersprungen werden sollen.";
$GLOBALS['TL_LANG']['tl_import_from_csv']['limit'][0] = "Datensätze limitieren";
$GLOBALS['TL_LANG']['tl_import_from_csv']['limit'][1] = "Geben Sie an, wie viele Datensätze importiert werden sollen (0=alle).";
$GLOBALS['TL_LANG']['tl_import_from_csv']['enableCron'][0] = "Cron aktivieren";
$GLOBALS['TL_LANG']['tl_import_from_csv']['enableCron'][1] = "Aktivieren Sie den Import als Cronjob.";
$GLOBALS['TL_LANG']['tl_import_from_csv']['cronLevel'][0] = "Cron Level";
$GLOBALS['TL_LANG']['tl_import_from_csv']['cronLevel'][1] = "Bestimmen Sie in welchenm Intervall die Cronjobs durchgeführt werden sollen.";
$GLOBALS['TL_LANG']['tl_import_from_csv']['infoText'] = "Mit MS-Excel oder einem Texteditor lässt sich eine kommaseparierte Textdatei anlegen (csv). In die erste Zeile gehören die Feldnamen. Die einzelnen Felder sollten durch ein Trennzeichen (üblicherweise das Semikolon \";\") abgegrenzt werden. Feldinhalt, der in der Datenbank als serialisiertes Array abgelegt wird (z.B. Gruppenzugehörigkeiten), muss durch zwei aufeinanderfolgende pipe-Zeichen abgegrenzt werden z.B. \"2||5\". Feldbegrenzer und Feldtrennzeichen können individuell festgelegt werden.<br><br>Wichtig! Jeder Datensatz gehört auf eine neue Zeile. Zeilenumbrüche im Datensatz verunmöglichen den Import. Die erstellte csv-Datei muss über die Dateiverwaltung auf den Webserver geladen werden. Anschliessend kann der Importvorgang mit dem entsprechenden Button gestartet werden.<br><br>Beim Importvorgang werden die Inhalte auf Gültigkeit überprüft.<br><br>Achtung! Das Modul sollte nur genutzt werden, wenn man sich seiner Sache sehr sicher ist. Gelöschte Daten können nur wiederhergestellt werden, wenn vorher ein Datenbankbackup erstellt worden ist.<br><br>Weitere Hilfe finden Sie unter: https://github.com/markocupic/import-from-csv-bundle";
$GLOBALS['TL_LANG']['tl_import_from_csv']['importOverview'] = "Import-Übersicht";
$GLOBALS['TL_LANG']['tl_import_from_csv']['datarecordInsertFailed'] = "Datensatz konnte nicht angelegt werden!";
$GLOBALS['TL_LANG']['tl_import_from_csv']['datarecordInsertSucceed'] = "Datensatz erfolgreich angelegt!!";

