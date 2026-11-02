# AGENTS.md

> **Herkunft:** erstellt von Claude Code (Sonnet 5), 2026-07-31 · gepflegt von Claude Code (Stand: 02.11.2026)
> **Einbindung:** Claude Code lädt diese Datei nicht selbst — `CLAUDE.md` zieht sie per `@AGENTS.md` nach.
> Regeln immer hier pflegen, nie in der `CLAUDE.md`.

PHP-Vokabeltrainer ("Lernkartei"), vanilla PHP + JS, kein Framework, kein Build-Schritt.
Ausführliche Doku: `DOKU.md` (Architektur, Sicherheit) und `admin-doku.php` (HTML-Variante, live verlinkt).

Themen: [go](~/KI-Projekte/themen/go.md), [web](~/KI-Projekte/themen/web.md) — Querschnittswissen, vor Arbeit am Thema lesen

## Stack & Commands

- **PHP 8.2**, kein Composer/Framework. **Kein lokaler PHP-Interpreter** — Syntax-Checks laufen
  über den Deploy-Server: `ssh go 'php -l go.p5k.eu/kartei/<datei>'`.
- **Python 3** für CSV-Konverter (`convert2.py`, `convert_excel_vokabeln.py`), Abhängigkeit
  `openpyxl` für Excel-Import.
- Kein Test-Framework, keine CI. Verifikation = Syntax-Check + manueller Live-Test im Browser
  (golden path + Edge Cases), bevor eine Änderung als erledigt gilt.

## Project Structure

```
kartei.php            Hauptanwendung (= Symlinks index.php, kartei4.php). Google-Login + Gast-Modus.
admin.php             Admin-Bereich: CSV Upload/Merge/Verwaltung, Fortschritts-Verwaltung, Konverter.
admin-doku.php        Doku als HTML, im Admin-Bereich verlinkt — bei Doku-Änderungen mitpflegen.
config.php            Secrets (Google Client-ID, Admin-Sub-IDs) — NICHT in Git, siehe Boundaries.
config.php.example    Vorlage für config.php.
*.csv (Root)           Aktive Vokabel-Dateien, per glob("*.csv") automatisch im Dropdown.
old-latein/, ITIL/    Archiv/themenfremd — von glob("*.csv") nicht erfasst, absichtlich.
storage/prog_*.json   Lernfortschritt pro User+CSV (Dateiname-Hash im Namen, siehe unten).
convert_excel_vokabeln.py  Konfigurierbarer Excel→CSV-Importer, KONFIGURATION-Block am Anfang anpassen.
```

## Teil-Auszüge einzelner Kapitel

Neben den beiden Schuljahres-Dateien kann eine **Teilmenge einzelner Kapitel** als eigene
CSV im Root liegen (derzeit `latein_Gym4_Kap15-17.csv` und `latein_Gym4_Kap18.csv`,
beide aus `latein_Gym4.csv`). Sie erscheint durch `glob("*.csv")` automatisch im Dropdown.

Erzeugt wird sie aus derselben Excel-Quelle wie die Vollversionen, mit der Logik aus
`convert_excel_vokabeln.py` (`kapitel_aus_lektion()`, `build_begriff()`, `BUCH_QUELLE`) —
der KONFIGURATION-Block dort bleibt unverändert auf dem 14er-Split für die beiden
Schuljahres-Dateien, der Auszug wird über ein separates Filterskript gezogen. So bleiben
Format, Wortart-Klammern und Quellenangabe identisch zur Vollversion; Gegenprobe ist die
Zeilenzahl (`grep -c '^"Kapitel 1[567]"' latein_Gym4.csv` muss zum Auszug passen).

**Der Lernfortschritt eines Auszugs ist unabhängig von dem der Vollversion.** Der
Speichername ist `prog_<user>_<md5(csv-dateiname)>.json` (`kartei.php`) — ein anderer
Dateiname ist damit ein anderer Lernstand, obwohl es dieselben Vokabeln sind. Wer in
`latein_Gym4_Kap15-17.csv` übt, bewegt in `latein_Gym4.csv` keine Karte. Das ist gewollt
(fokussiertes Üben vor einer Schularbeit), muss aber beim Anlegen weiterer Auszüge
mitbedacht werden: Jeder neue Auszug beginnt bei Fach 1.

## Code Style

- Keine Frameworks/Build-Tools einführen — bewusst minimalistisch gehalten.
- CSV-Format: `"Kategorie","Begriff","Definition","Quelle"`, RFC-quotiert (`csv.QUOTE_ALL` in Python-Skripten).
- Kommentare im Code nur bei nicht-offensichtlichem WARUM (z. B. Sicherheits-Workarounds), nicht als
  Nacherzählung des Codes — bestehender Stil in `kartei.php`/`admin.php` als Referenz nehmen.
- Karten-ID = `btoa(Kategorie|Begriff|Definition)` + Zähler bei echten Dubletten — beim Ändern der
  CSV-Struktur beachten, dass sich daraus IDs und Fortschritts-Zuordnung ableiten.

## Git Workflow

- Nur committen, wenn explizit vom User gewünscht (nicht automatisch nach jeder Änderung).
- Neue Commits statt `--amend`, außer explizit anders gewünscht.
- Repo ist öffentlich (github.com/andreaspo/kartei) — vor jedem Commit prüfen, ob Verlagsmaterial
  (`*.xlsx`) oder Secrets versehentlich mitgehen.

## Deployment (Server "go")

- Ziel: `go.p5k.eu/kartei/` (SSH-Alias `go`, User `dh_td5fy7`, Shared-Hosting).
- Vollständig: `git archive --format=tar HEAD | ssh go 'tar -x -C go.p5k.eu/kartei'`.
- Einzeldatei: `scp <datei> go:go.p5k.eu/kartei/<datei>`.
- **Bekannt flaky**: `ssh go` hängt gelegentlich bei Mehrfach-Transfers oder verketteten
  `&&`-Befehlen ohne Fehlermeldung. Bei Hänger: einzelne, einfache Befehle statt Ketten verwenden,
  nicht von einem echten Fehler ausgehen, sondern mit frischem Einzelbefehl erneut prüfen.
- Nach jedem PHP-Deploy: `php -l` auf dem Server, dann Live-Test (curl/Browser).

## Boundaries

**✅ Immer**
- Nach PHP-Änderungen Syntax-Check via `ssh go 'php -l ...'` vor "erledigt"-Meldung.
- Bei CSV-Strukturänderungen prüfen, ob `storage/prog_*.json`-Fortschritt kompatibel bleibt.

**⚠️ Erst nachfragen**
- Force-Push, History-Rewrite, Löschen/Umbenennen aktiv genutzter CSV-Dateien.
- Sichtbarkeit des Repos ändern, neue Secrets/Zugänge in Doku oder Code aufnehmen.
- Neue Abhängigkeiten, Frameworks oder ein Build-Schritt.

**🚫 Nie**
- `config.php` committen (enthält Google Client-ID + Admin-Sub-IDs) — nur `config.php.example` pflegen.
- Verlagsmaterial (`*.xlsx` o. ä. Lehrbuch-Quellen) committen — Original bleibt lokal, per `.gitignore`.
- Storage-Dateien (`storage/*.json`, echte Lernstände) committen.

## Offene Punkte

- **Auszüge werden von Hand gezogen.** `convert_excel_vokabeln.py` kann bisher nur den
  festen 14er-Split; ein Kapitelbereich als Parameter (`--kapitel 15-17 --out <datei>`)
  wäre der nächste sinnvolle Schritt. Seit 02.11.2026 gibt es zwei Auszüge (Kapitel 15–17
  und 18) — spätestens beim dritten lohnt sich der Parameter.
- **`latein_Gym3_1by1.csv`** liegt unverändert im Root und ist damit im Dropdown, ohne dass
  dokumentiert wäre, wie es sich von `latein_Gym3.csv` unterscheidet (467 statt 422
  Zeilen). Vor dem Aufräumen mit dem User klären — aktiv genutzte CSVs werden nicht
  ungefragt entfernt.
