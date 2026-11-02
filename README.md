# 🗃️ kartei — Webbasierter Spaced-Repetition Vokabeltrainer

> **Verzeichnis:** `~/KI-Projekte/kartei`  
> **Typ:** Eigenständiges Git-Repository (Branch `main`, Remote: `github.com/andreaspo/kartei`)  
> **Erstellt & gepflegt von:** Claude Code / Antigravity  
> **Live-URL:** [https://go.p5k.eu/kartei/](https://go.p5k.eu/kartei/)  
> **Stand:** 02.11.2026 · erstellt mit Claude Code GUI (Opus 5.5)

---

## 🎯 Überblick & Features

Leichtgewichtiger, extrem schneller Spaced-Repetition Vokabel- und Begriffstrainer (Leitner-5-Fächer-System). Entwickelt in Vanilla PHP 8.2 und modernem JavaScript ohne Framework- oder Build-Overhead.

- **Dual-Modus:**
  - 🔑 **Google Sign-In:** Personalisierter Lernfortschritt, geräteübergreifend synchronisiert.
  - 👤 **Gast-Modus:** Sofortiges Lernen ohne Login (Fortschritt im Browser-Session/LocalStorage).
- **Lehrstoff-Sammlungen:**
  - Latein Gymnasium 3. & 4. Klasse (`latein_Gym3.csv`, `latein_Gym4.csv`).
  - Auszug Kapitel 15–17 der 4. Klasse als eigene Sammlung (`latein_Gym4_Kap15-17.csv`).
  - Auszug Kapitel 18 der 4. Klasse als eigene Sammlung (`latein_Gym4_Kap18.csv`).
  - ITIL 4 Foundation & Monitoring (`ITIL/`).
- **Administrationsbereich (`admin.php`):**
  - CSV-Upload, Vokabel-Zusammenführung, Verwaltung von Lernständen, integrierte HTML-Dokumentation (`admin-doku.php`).
- **Excel-Importer (`convert_excel_vokabeln.py`):**
  - Wandelt Schulbuch-Exceldateien (`.xlsx`) in saubere, RFC-konforme Lern-CSVs um.

---

## 🏗️ Architektur & Dateistruktur

```text
kartei/
├── kartei.php            # Haupt-Lernanwendung (Symlinks: index.php, kartei4.php)
├── admin.php             # Admin-Dashboard (Upload, Merge, Fortschrittsverwaltung)
├── admin-doku.php        # Integrierte HTML-Dokumentation
├── config.php.example    # Vorlage für Secrets (Google Client ID, Admin Sub-IDs)
├── config.php            # Lokale Secrets (NICHT im Git-Repository!)
├── DOKU.md               # Ausführliche Architektur- & Sicherheitsdokumentation
├── AGENTS.md             # Werkzeug-neutrale Regeln für KI-Assistenten
├── convert_excel_vokabeln.py # Python-Skript für Excel-zu-CSV Importe
├── latein_Gym3.csv       # Aktive Vokabeldateien (automatisch im Dropdown)
├── latein_Gym4.csv
├── latein_Gym4_Kap15-17.csv # Auszug Kapitel 15–17 aus latein_Gym4.csv
├── latein_Gym4_Kap18.csv # Auszug Kapitel 18
├── ITIL/                 # ITIL-Fragenkataloge
├── old-latein/           # Archivierte Vokabel-Rohdaten
└── storage/              # JSON-Lernstände pro Nutzer (gehasht)
```

---

## 🚀 Deployment auf Server `go`

Das Produktivsystem läuft auf dem Shared Hoster `go.p5k.eu`:

```bash
# Vollständiges Deployment:
git archive --format=tar HEAD | ssh go 'tar -x -C go.p5k.eu/kartei'

# Einzeldatei aktualisieren:
scp kartei.php go:go.p5k.eu/kartei/kartei.php

# Remote-Syntaxprüfung (wichtig, da lokal kein PHP installiert ist):
ssh go 'php -l go.p5k.eu/kartei/kartei.php'
```

> [!WARNING]
> **Host `go`:** Keine verketteten Befehle (`&&`) nutzen. Einzelne, simple Aufrufe verwenden.

---

## 🔒 Sicherheits- & Datenschutzregeln

- `config.php` darf **niemals committet werden** (enthält Google OAuth Client-ID).
- Urheberrechtlich geschütztes Verlagsmaterial (`*.xlsx`) bleibt per `.gitignore` lokal.
- Echte Lernstände in `storage/*.json` werden nicht im Git versioniert.

---

*Dokumentation aktualisiert: 08.09.2026*
