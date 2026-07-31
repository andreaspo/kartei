# Lernkartei – Dokumentation

Eine PHP-basierte Vokabel-Lernkartei mit Google-Login, 3-Stufen-Lernsystem und
einem konsolidierten Admin-Bereich zur Verwaltung der Vokabel-Dateien.

## 1. Überblick

| Datei | Zweck |
|---|---|
| `kartei.php` (= `index.php`, `kartei4.php`) | Hauptanwendung mit Google-Login |
| `kartei-guest.php` | Gast-Modus ohne Login, bewusst ohne Zugriffsschutz |
| `admin.php` | Konsolidierter Admin-Bereich (Upload, Merge, Verwaltung, Konverter) |
| `admin-doku.php` | Diese Dokumentation als HTML, im Admin-Bereich verlinkt |
| `config.php` | Zugangsdaten/Einstellungen (siehe Abschnitt 3) |

`kartei.php` und `kartei-guest.php` sind vom Konzept her unabhängig: Die
Google-Login-Variante speichert Fortschritt pro eingeloggtem User, der
Gast-Modus speichert ihn unter der festen Kennung `guest` – zwei Gäste am
selben Server teilen sich also denselben Lernstand pro Datei.

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
kartei.php, kartei-guest.php, admin.php, admin-doku.php, config.php
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
- **`kartei-guest.php`** hat bewusst **keinen** dieser Schutzmechanismen –
  das ist ein offener Modus ohne Hürden, entsprechend sollte dort auch
  nichts Sensibles abgelegt werden.

## 7. Entwicklung / Deployment

- Lokales Arbeitsverzeichnis ist ein eigenständiges Git-Repository
  (`git log` zeigt die Historie inkl. aller Sicherheits- und Bugfixes).
- Deployment aktuell manuell: `git archive` bzw. `scp` einzelner Dateien auf
  den Server (`go:go.p5k.eu/kartei/`), kein automatisierter Deploy-Prozess.
- Lokal fehlt ein PHP-Interpreter zum Testen – Syntax-Checks (`php -l`) und
  echte Funktionstests laufen auf dem Server.
