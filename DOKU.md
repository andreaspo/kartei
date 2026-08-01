# Lernkartei – Dokumentation

Eine PHP-basierte Vokabel-Lernkartei mit Google-Login, 3-Stufen-Lernsystem und
einem konsolidierten Admin-Bereich zur Verwaltung der Vokabel-Dateien.

## 1. Überblick

| Datei | Zweck |
|---|---|
| `kartei.php` (= `index.php`, `kartei4.php`) | Hauptanwendung – Google-Login **oder** Gast-Modus, gleiche Features |
| `admin.php` | Konsolidierter Admin-Bereich (Upload, Merge, Verwaltung, Konverter) |
| `admin-doku.php` | Diese Dokumentation als HTML, im Admin-Bereich verlinkt |
| `config.php` | Zugangsdaten/Einstellungen (siehe Abschnitt 3) |
| `convert_excel_vokabeln.py` | Excel-Vocabularium → Lernkartei-CSVs (siehe Abschnitt 8) |

`kartei.php` bietet auf der Login-Seite zusätzlich zum Google-Button "Ohne
Anmeldung fortfahren (Gast)". Beide Wege führen zur exakt gleichen
Lernoberfläche (3-Stufen-System, Fortschritts-Abgleich, Reset). Unterschied:
Google-Login speichert Fortschritt pro echter Google-Sub-ID, der Gast-Modus
unter der festen, gemeinsamen Kennung `guest` – alle Gäste am selben Server
teilen sich also bewusst denselben Lernstand pro Datei. Der Admin-Link
erscheint nur bei echtem Login mit einer autorisierten Sub-ID, nie im
Gast-Modus.

## 2. Benutzung (Lernmodus)

1. CSV-Datei aus der Dropdown-Liste wählen (nur Dateien **direkt** im
   Projektverzeichnis, siehe Abschnitt 4 – Unterordner werden nicht gelistet).
2. Abfragerichtung wählen: Begriff → Definition oder umgekehrt.
3. "Lernen starten". Jede Karte durchläuft drei Stufen:
   - **Stufe 1**: noch nicht gelernt / falsch beantwortet
   - **Stufe 2**: einmal richtig beantwortet
   - **Stufe 3 ✓**: zweimal in Folge richtig beantwortet → gilt als gelernt
4. Der Fortschritt wird nach jeder Antwort automatisch im Hintergrund
   gespeichert (pro User + pro CSV-Datei) und beim nächsten Laden mit dem
   aktuellen Inhalt der CSV abgeglichen: neue Vokabeln in der Datei landen
   automatisch in Stufe 1, entfernte Vokabeln verschwinden aus dem
   gespeicherten Fortschritt.
5. Sind alle Vokabeln in Stufe 3, bietet die App einen Reset für diese Datei an.

## 3. Konfiguration (`config.php`)

```php
$googleClientId = "...";      // Google OAuth Client-ID für den Login-Button
$authorizedUsers = [          // Google-Sub-IDs mit Admin-Rechten
    "115838474626364014898",  // Kommentar = Klarname zur Orientierung
    "105920009078675839078"
];
$debugMode = true;            // true = PHP-Fehler werden angezeigt (für Live-Betrieb auf false stellen)
```

- **`googleClientId`**: Wird im Login-Button (`kartei.php`) eingebettet und
  bei der Token-Prüfung serverseitig als erwartete `aud` (Audience) verglichen.
- **`authorizedUsers`**: Nur diese Google-Sub-IDs bekommen Zugriff auf
  `admin.php`. Die eigene Sub-ID findet man z. B. über die
  Fortschritts-Verwaltung in `admin.php`, wenn man eingeloggt ist.
- **`debugMode`**: Sollte im produktiven Betrieb auf `false` stehen, damit
  keine PHP-Fehlermeldungen (die interne Pfade preisgeben können) angezeigt werden.

## 4. Dateistruktur

```
kartei.php, admin.php, admin-doku.php, config.php
*.csv                  <- aktive Vokabel-Dateien (erscheinen im Dropdown)
old-latein/            <- archivierte/abgelöste Latein-Einzelkapitel & Sammel-CSVs
ITIL/                  <- themenfremde CSVs (nicht Latein-bezogen)
storage/               <- Lernfortschritt als JSON, pro User + CSV-Datei
```

CSV-Dateien in Unterordnern werden von `glob("*.csv")` **nicht** erfasst und
tauchen daher weder im Lern-Dropdown noch in der Admin-CSV-Verwaltung auf.
Sie bleiben aber unter ihrer alten URL direkt erreichbar (z. B. zum
Nachschlagen oder für einen späteren Merge zurück ins Hauptverzeichnis).

**Speicherformat** (`storage/prog_<user-id>_<md5(csvDateiname)>.json`):

```json
{
  "stage1": [ {"id": "...", "cat": "...", "term": "...", "def": "...", "src": "..."} ],
  "stage2": [ ... ],
  "stage3": [ ... ],
  "direction": "begriff"
}
```

Die `id` einer Karte wird aus Kategorie+Begriff+Definition gebildet – nicht
nur aus dem Begriff. Das verhindert, dass zwei verschiedene Vokabeln mit
zufällig gleichem Begriff/gleicher Definition sich beim Lernfortschritt in
die Quere kommen.

**Wichtig bei Umbenennungen**: Da der Dateiname Teil des Speicher-Hashs ist,
verwaist der Fortschritt, wenn eine CSV-Datei umbenannt wird, ohne die
zugehörige `storage/prog_..._<alterHash>.json` auf den neuen Hash
umzubenennen. Die CSV-Verwaltung in `admin.php` macht das beim Umbenennen
**nicht automatisch** – das ist beim manuellen Umbenennen einer aktiv
genutzten Datei zu beachten.

## 5. Admin-Bereich (`admin.php`)

Erreichbar über den "🛠 Admin"-Link in `kartei.php`, nur sichtbar/zugänglich
für Google-Sub-IDs aus `$authorizedUsers`. Ohne Berechtigung erfolgt ein
automatischer Redirect zu `kartei.php`.

| Bereich | Funktion |
|---|---|
| **CSV Upload** | Neue `.csv`-Datei hochladen. Nur `.csv`-Endung erlaubt, vorhandene Dateien werden nicht überschrieben, Inhalt wird grob auf verdächtigen PHP-Code geprüft. |
| **CSV Zusammenführen** | Zwei vorhandene CSVs kombinieren: Zeilen der Quelle werden an das Ziel angehängt, exakte Dubletten automatisch entfernt. |
| **CSV-Verwaltung** | Liste aller aktiven CSVs mit Vokabelanzahl, Größe, Änderungsdatum. Umbenennen und Löschen direkt im Browser (siehe Hinweis zu Hashes oben). |
| **Fortschritts-Verwaltung** | Übersicht aller gespeicherten Lernstände (welcher User, welche CSV, welche Größe). Einzelne Lernstände lassen sich gezielt löschen, z. B. um jemandem einen sauberen Neustart für eine Datei zu ermöglichen. |
| **Rohtext → CSV Konverter** | Wandelt eine einfache Zeilenliste im Format `latein / deutsch` (Bullet-Punkt `•` optional) in eine ordentliche CSV um – Vorschau vor dem Speichern, Kategorie und Quelle frei wählbar. |

## 6. Sicherheit

- **Login**: Das Google-ID-Token wird serverseitig über Googles
  `tokeninfo`-Endpunkt verifiziert (Signatur, `aud`, `iss`, `exp`) – nicht nur
  lokal dekodiert. Ohne gültiges, echtes Google-Token gibt es keine Session.
- **CSRF-Schutz**: Jedes Formular in `admin.php` schickt ein Session-Token
  mit, das serverseitig geprüft wird, bevor eine Aktion ausgeführt wird.
- **Datei-Whitelisting**: Merge, Umbenennen und Löschen akzeptieren nur
  Dateinamen, die tatsächlich in `glob("*.csv")` bzw.
  `glob("storage/prog_*.json")` auftauchen – keine rohen Pfade aus dem
  Formular, um Path Traversal zu verhindern.
- **Gast-Modus** hat bewusst **keine** Login-Hürde – das ist Absicht (schneller
  Zugang ohne Google-Konto), entsprechend teilen sich alle Gäste denselben
  Lernstand und sollten dort nichts Sensibles ablegen.

## 7. CSV-Parser & Fehlerbenachrichtigung

Der CSV-Parser in `kartei.php` ist ein echter (kleiner) CSV-Parser, kein
simpler Split-by-Komma: quotierte Felder mit Kommas darin werden korrekt
gelesen. Eine Zeile wird **übersprungen** (nicht abgebrochen), wenn:

- die Quotierung kaputt ist (z. B. ein nicht geschlossenes `"`), oder
- Begriff oder Definition nach dem Parsen leer sind.

Beim Laden einer Datei mit übersprungenen Zeilen schickt das Frontend eine
**gebündelte** Meldung (ein Request pro Ladevorgang, nicht pro Zeile) an
`https://ntfy.p5k.eu/kids-todos` – mit Dateiname, Anzahl und bis zu 10
Beispielen (Zeilennummer + Grund + Rohtext). Der Versand läuft serverseitig
über `sendNtfyNotification()` in `kartei.php` (offener ntfy-Endpunkt, kein
Auth nötig). Schlägt der Versand fehl, wird das nur in der Browser-Konsole
geloggt – der Lernvorgang selbst wird dadurch nicht blockiert.

## 8. Excel-Import (`convert_excel_vokabeln.py`)

Wandelt ein Vocabularium-Excel (Spalten Lektion, Wortart, Dekl./Konj., Kategorie,
Lateinisch, Deutsch) in zwei Lernkartei-CSVs um, aufgeteilt nach Schuljahr. Für
`latein_Gym3.csv`/`latein_Gym4.csv` diente das offizielle Vocabularium zu
"Medias In Res! L6" (3./4. Schuljahr) als Quelle, herunterladbar unter
<https://www.scook.at/produkt/c545cdd4-4cbc-41cf-b839-c3234acd7207?key=med030&section=Materialien>.
Die Original-Excel selbst liegt **nicht** im Git-Repo (Verlagsmaterial, siehe
`.gitignore: *.xlsx`).

- Läuft lokal per `python3 convert_excel_vokabeln.py` (benötigt `openpyxl`).
- Wortart/Deklination bzw. Konjugation werden direkt an den Begriff angehängt,
  z. B. `catta,-ae f. {Substantiv (a-Dekl. f.)}` — Quelle-Feld ist für alle
  Zeilen einheitlich der Buchtitel.
- Kapitelnummer entscheidet über die Zieldatei: `KAPITEL_SPLIT` (Standard 14)
  trennt 3. von 4. Schuljahr. Vokabeln mit Mehrfach-Lektion (z. B. `"4;8"`)
  werden nur der ersten genannten Lektion zugeordnet.
- **Für ein neues Buch oder künftige Import-Fehler**: den
  `KONFIGURATION`-Block am Anfang des Skripts anpassen (Eingabedatei,
  Sheet-Name, Buchtitel, Kapitel-Split, Ausgabedateien, Spaltenreihenfolge).
  Ändert sich der Excel-Aufbau grundlegender, ggf. zusätzlich
  `build_begriff()`/`kapitel_aus_lektion()` anpassen.

## 9. Entwicklung / Deployment

- Lokales Arbeitsverzeichnis ist ein eigenständiges Git-Repository
  (`git log` zeigt die Historie inkl. aller Sicherheits- und Bugfixes).
- Deployment aktuell manuell: `git archive` bzw. `scp` einzelner Dateien auf
  den Server (`go:go.p5k.eu/kartei/`), kein automatisierter Deploy-Prozess.
- Lokal fehlt ein PHP-Interpreter zum Testen – Syntax-Checks (`php -l`) und
  echte Funktionstests laufen auf dem Server.
