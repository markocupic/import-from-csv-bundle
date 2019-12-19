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

$GLOBALS['TL_LANG']['tl_import_from_csv']['import_table']['0'] = "Datentabelle für Import auswählen";
$GLOBALS['TL_LANG']['tl_import_from_csv']['import_table']['1'] = "Wählen Sie eine Tabelle, in welche die Daten importiert werden sollen, aus.";
$GLOBALS['TL_LANG']['tl_import_from_csv']['import_mode']['0'] = "Import-Modus";
$GLOBALS['TL_LANG']['tl_import_from_csv']['import_mode']['1'] = "Entscheiden Sie, ob die Tabelle vor dem Import gelöscht werden soll oder die Daten an die bestehenden Einträge angehängt werden sollen.";
$GLOBALS['TL_LANG']['tl_import_from_csv']['field_enclosure']['0'] = "Felder eingeschlossen von";
$GLOBALS['TL_LANG']['tl_import_from_csv']['field_enclosure']['1'] = "Zeichen, von welchem die Felder in der csv-Datei eingeschlossen sind. Normalerweise ein doppeltes Anführungszeichen: => \"";
$GLOBALS['TL_LANG']['tl_import_from_csv']['field_separator']['0'] = "Felder getrennt von";
$GLOBALS['TL_LANG']['tl_import_from_csv']['field_separator']['1'] = "Zeichen, mit dem die Felder in der csv-Datei voneinander getrennt sind. Normalerweise ein Semikolon: => ;";
$GLOBALS['TL_LANG']['tl_import_from_csv']['selected_fields']['0'] = "Felder für Importvorgang auswählen.";
$GLOBALS['TL_LANG']['tl_import_from_csv']['fileSRC']['0'] = "csv-Datei auswählen";
$GLOBALS['TL_LANG']['tl_import_from_csv']['fileContent']['0'] = "Datei-Inhalt";
$GLOBALS['TL_LANG']['tl_import_from_csv']['truncate_table']['0'] = "Tabelle vor dem Import löschen";
$GLOBALS['TL_LANG']['tl_import_from_csv']['append_entries']['0'] = "Datensätze nur anhängen";
$GLOBALS['TL_LANG']['tl_import_from_csv']['new']['0'] = "Neuen Importdatensatz anlegen";
$GLOBALS['TL_LANG']['tl_import_from_csv']['new']['1'] = "Einen neuen Importdatensatz anlegen";
$GLOBALS['TL_LANG']['tl_import_from_csv']['launchImportButton'] = "Importvorgang starten";
$GLOBALS['TL_LANG']['tl_import_from_csv']['testRunImportButton'] = "Importvorgang im Testmodus starten";
$GLOBALS['TL_LANG']['tl_import_from_csv']['datarecords'] = "Anzahl Datensätze";
$GLOBALS['TL_LANG']['tl_import_from_csv']['successful_inserts'] = "Erfolgreiche inserts";
$GLOBALS['TL_LANG']['tl_import_from_csv']['failed_inserts'] = "Missglückte inserts";
$GLOBALS['TL_LANG']['tl_import_from_csv']['skipValidationFields'][0] = "Validierung für diese Felder überspringen";
$GLOBALS['TL_LANG']['tl_import_from_csv']['skipValidationFields'][1] = "Geben Sie an für welche Felder die Validierung übersprungen werden soll.";
$GLOBALS['TL_LANG']['tl_import_from_csv']['offset'][0] = "Datensätze überspringen";
$GLOBALS['TL_LANG']['tl_import_from_csv']['offset'][1] = "Geben Sie an, wie viele Datensätze beim Import übersprungen werden sollen.";
$GLOBALS['TL_LANG']['tl_import_from_csv']['limit'][0] = "Datensätze limitieren";
$GLOBALS['TL_LANG']['tl_import_from_csv']['limit'][1] = "Geben Sie an, wie viele Datensätze importiert werden sollen (0=alle).";
