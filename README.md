![Logo](https://github.com/markocupic/markocupic/blob/main/logo.png)

# Import from CSV (Backend-Modul für Contao CMS)

Mit diesem Modul lassen sich über eine CSV-Datei in einem Rutsch grosse Mengen an Datensätzen importieren – praktisch, wenn z.B. sehr viele Benutzer oder Mitglieder angelegt werden müssen. Die CSV-Datei erstellt man am besten in einem Tabellenkalkulationsprogramm (z.B. MS Excel) und speichert sie als kommaseparierte Datei (CSV) ab. Ein Beispiel für eine solche Datei findet sich im Verzeichnis [docs](docs/import-file.csv).

https://user-images.githubusercontent.com/1525166/150694067-e4438409-d6b0-42c7-853b-1b273a2f5064.mp4

## Warnung!

> [!CAUTION]
> Das Modul bietet einen grossen Nutzen, erfordert aber Sorgfalt: Bei falscher Anwendung können Datensätze gelöscht oder unbrauchbar gemacht werden, sodass Contao anschliessend nicht mehr funktionstüchtig ist. Setzen Sie das Modul nur ein, wenn Sie wissen, was Sie tun.

## Aufbau der CSV-Importdatei

Die Importdatei ist eine kommaseparierte Textdatei (CSV), die sich mit MS Excel oder einem Texteditor erstellen lässt. In die erste Zeile gehören zwingend die Feldnamen. Die einzelnen Felder werden durch ein Trennzeichen (üblicherweise das Semikolon `;`) voneinander abgegrenzt. Feldinhalte, die in der Datenbank als serialisiertes Array abgelegt werden (z.B. Gruppenzugehörigkeiten, Newsletter-Abos usw.), werden durch zwei aufeinanderfolgende Pipe-Zeichen getrennt, z.B. `2||5`. Feldtrennzeichen und Feldbegrenzer lassen sich individuell festlegen.

> [!IMPORTANT]
> Jeder Datensatz gehört in eine eigene Zeile – Zeilenumbrüche innerhalb eines Datensatzes machen den Import unmöglich. Die fertige CSV-Datei wird über die Contao-Dateiverwaltung auf den Webserver geladen und kann anschliessend in der Import-Konfiguration ausgewählt werden.

Beim Import werden die Inhalte auf Gültigkeit geprüft; als Grundlage dienen die DCA-Einstellungen der Zieltabelle.

> [!CAUTION]
> Das Modul sollte nur genutzt werden, wenn man sich seiner Sache sehr sicher ist. Gelöschte Daten lassen sich nur wiederherstellen, wenn zuvor ein Datenbank-Backup erstellt wurde.

## Einstellungen

### Kommaseparierte Datei erstellen und hochladen

Als Erstes muss eine CSV-Datei erstellt werden. In die Kopfzeile gehören die Feldnamen.

```
firstname;lastname;dateOfBirth;gender;company;street;postal;city;state;country;phone;mobile;fax;email;website;language;login;username;password;groups
Hans;Meier;1778-05-22;male;Webdesign AG;Ringelnatterweg 1;6208;Oberkirch;Kanton Luzern;ch;041 921 99 97;079 620 99 91;045 789 56 89;h-meier@me.ch;www.hans-meier.ch;de;1;hansmeier;topsecret;1||2
Fritz;Nimmersatt;1978-05-29;male;Webdesign AG;Entenweg 10;6208;Oberkirch;Kanton Luzern;ch;041 921 99 98;079 620 99 92;046 789 56 89;f-nimmersatt@me.ch;www.fritz-nimmersatt.ch;de;1;fritznimmer;topsecret2;1||2
Annina;Meile;1878-05-29;female;Webdesign AG;Nashornstrasse 2;6208;Oberkirch;Kanton Luzern;ch;043 921 99 99;079 620 93 91;047 789 56 89;a-meile@me.ch;www.annina-meile.ch;de;1;anninameile;topsecret3;1
```

### Zeichenkodierung (UTF-8)

> [!WARNING]
> Speichern Sie die CSV-Datei **UTF-8-kodiert** ab, damit Umlaute (ä, ö, ü, …) und Sonderzeichen korrekt in die Datenbank importiert werden. Contao arbeitet intern mit UTF-8. Wird die Datei in einer anderen Kodierung (z.B. ISO-8859-1 / Windows-1252, oft als „ANSI" bezeichnet) gespeichert, landen Umlaute verstümmelt in der Datenbank – aus „Müller" wird dann z.B. „MÃ¼ller".

So speichern Sie UTF-8 aus den gängigen Programmen:

- **MS Excel:** „Speichern unter" und als Dateityp **„CSV UTF-8 (durch Trennzeichen getrennt) (*.csv)"** wählen. Nicht das einfache „CSV (Trennzeichen-getrennt)" nehmen – das speichert in Windows-1252/ANSI.
- **LibreOffice Calc:** „Speichern unter" → „Text CSV", im folgenden Dialog den *Zeichensatz* auf **Unicode (UTF-8)** stellen.
- **Texteditor (Notepad++, VS Code o.ä.):** die Kodierung explizit auf **UTF-8 (ohne BOM)** setzen bzw. „In UTF-8 konvertieren".

Tipp: Speichern Sie möglichst **UTF-8 ohne BOM**. Ein vorangestelltes BOM (Byte Order Mark) kann den Namen des ersten Feldes in der Kopfzeile verfälschen und so die Feldzuordnung stören.

### Datentabelle für den Import auswählen (Pflichtfeld)

Wählen Sie die Tabelle, in die die Datensätze importiert werden sollen.

### Felder für den Importvorgang auswählen (Pflichtfeld)

In der Datenbanktabelle wird nur in die ausgewählten Felder geschrieben. Meist ist es sinnvoll, hier alle Felder auszuwählen.

### Felder getrennt von (Pflichtfeld)

Geben Sie an, durch welches Zeichen die Feldinhalte in der CSV-Datei voneinander getrennt sind.

### Felder eingeschlossen von (Pflichtfeld)

Prüfen Sie, ob die Feldinhalte in der CSV-Datei zusätzlich von einem Zeichen eingeschlossen sind. Häufig ist das das doppelte Anführungszeichen (`"`).

### Import-Modus (Pflichtfeld)

Legen Sie fest, ob die Datensätze an die Zieltabelle angehängt werden oder ob die Zieltabelle vorher geleert werden soll (»alter table«).

> [!CAUTION]
> Ohne Backup lassen sich gelöschte Datensätze nicht mehr wiederherstellen.

### Datei auswählen (Pflichtfeld)

Wählen Sie abschliessend die Datei aus, aus der in die Datenbank geschrieben werden soll. Tipp: Klicken Sie nach der Auswahl zunächst auf „Speichern", um eine Vorschau des Dateiinhalts zu erhalten.

### Zeilenumbrüche

Alle `[NEWLINE]`-Tags in der CSV-Datei werden beim Import in `\r\n` bzw. `\n` umgewandelt.

### Leere Feldwerte

Leere Feldwerte werden nicht verarbeitet. Beim Import eines neuen Datensatzes wird stattdessen der Default-Wert aus `$GLOBALS['TL_DCA']['tl_my_table']['fields']['myField']['sql']` gesetzt.

### Cronjob

Auf Wunsch lässt sich CRON aktivieren, sodass der Import in einem festgelegten Intervall automatisch ausgeführt wird.

## Konfiguration

Über die Bundle-Konfiguration lassen sich zwei Werte anpassen. Erstellen Sie dazu – falls noch nicht vorhanden – im Projekt die Datei `config/config.yaml` und ergänzen Sie:

```yaml
# config/config.yaml
markocupic_import_from_csv:
  preview_limit: 200            # Standard: 200
  max_inserts_per_request: 25   # Standard: 25
```

| Parameter                 | Standard | Erklärung                                                                                                                                                                   |
|:--------------------------|:---------|:----------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `preview_limit`           | `200`    | Anzahl der Datensätze, die in der Vorschau (nach dem Speichern der Import-Konfiguration) angezeigt werden.                                                                  |
| `max_inserts_per_request` | `25`     | Anzahl der Datensätze, die pro Request importiert werden. Der Import wird in mehreren aufeinanderfolgenden Requests abgearbeitet, um Zeit- und Speicherlimits zu vermeiden. |

Beide Werte müssen ganze Zahlen grösser 0 sein. Ungültige Angaben (0, negativ oder keine Ganzzahl) führen bereits beim Aufbau des Containers zu einem Konfigurationsfehler.

> [!IMPORTANT]
> **Passwörter:** Werden beim Import Passwörter gehasht (z.B. beim Import in `tl_member` oder `tl_user` mit einer `password`-Spalte), ist das Hashing (bcrypt/argon2) bewusst sehr rechenintensiv. Wählen Sie `max_inserts_per_request` in diesem Fall **klein** (z.B. 5–10), damit ein einzelner Request nicht in das PHP-Zeitlimit (`max_execution_time`) oder ins Speicherlimit läuft. Ohne Passwort-Hashing kann der Wert deutlich höher gewählt werden.

## Importmechanismus über Event-Listener anpassen

Über einen Event Listener lässt sich die Validierung umgehen oder anpassen. Im folgenden Beispiel werden die Geokoordinaten beim Import anhand von Strasse, Stadt und Länderkürzel automatisch per cURL-Request von Google Maps ermittelt. Ausserdem lassen sich Fehlermeldungen erzeugen, etwa wenn keine Geokoordinaten ermittelt werden konnten – der betreffende Datensatz wird dann übersprungen und nicht in die Datenbank geschrieben.

Hier der Aufbau einer möglichen [Event-Listener-Klasse](src/EventListener/PreValidateWidget/PreValidateWidgetDemoListener.php).

```

Viel Spass mit dem Import From CSV Bundle!
