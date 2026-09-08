# AGENTS.md

> **Herkunft:** erstellt von Claude Code (Sonnet 5), 2026-07-31 · gepflegt von Claude Code (Stand: 21.08.2026)
> **Einbindung:** Claude Code lädt diese Datei nicht selbst — `CLAUDE.md` zieht sie per `@AGENTS.md` nach.
> Regeln immer hier pflegen, nie in der `CLAUDE.md`.

PHP-Vokabeltrainer ("Lernkartei"), vanilla PHP + JS, kein Framework, kein Build-Schritt.
Ausführliche Doku: `DOKU.md` (Architektur, Sicherheit) und `admin-doku.php` (HTML-Variante, live verlinkt).

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
