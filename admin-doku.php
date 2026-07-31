<?php
session_start();
if (file_exists('config.php')) {
    include 'config.php';
} else {
    $authorizedUsers = [];
}

$isAdmin = isset($_SESSION['user_id']) && in_array($_SESSION['user_id'], $authorizedUsers, true);
if (!$isAdmin) {
    header('Location: kartei.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <title>Lernkartei – Doku</title>
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, sans-serif; margin: 0; padding: 0.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: #1a202c; }
        .container { max-width: 900px; margin: 0 auto; padding: 0.5rem; }
        h1 { color: white; text-align: center; margin-bottom: 0.5rem; }
        .top-bar { text-align: right; margin-bottom: 10px; }
        .top-bar a { color: white; background: rgba(255,255,255,0.15); padding: 8px 15px; border-radius: 20px; text-decoration: none; font-size: 0.85rem; margin-left: 8px; }
        .card-panel { background: white; padding: 1.5rem 1.8rem; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); margin-bottom: 1.5rem; line-height: 1.55; }
        .card-panel h2 { margin-top: 0; font-size: 1.15rem; border-bottom: 2px solid #f0f4f8; padding-bottom: 0.5rem; }
        .card-panel h3 { font-size: 1rem; color: #4a5568; }
        code, pre { background: #f0f4f8; border-radius: 6px; font-size: 0.85rem; }
        code { padding: 2px 6px; }
        pre { padding: 12px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 0.8rem 0; font-size: 0.88rem; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        th { background: #f7fafc; }
        .hinweis { background: #fffbea; border-left: 4px solid #f6ad55; padding: 10px 14px; border-radius: 6px; margin: 0.8rem 0; font-size: 0.9rem; }
        .badge { display: inline-block; background: #667eea; color: white; border-radius: 6px; padding: 2px 8px; font-size: 0.75rem; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <div class="top-bar">
        <a href="admin.php">🛠 Admin-Bereich</a>
        <a href="kartei.php">⬅ Lernkartei</a>
    </div>
    <h1>📖 Doku: Einstellungen &amp; Admin-Möglichkeiten</h1>

    <div class="card-panel">
        <h2>Überblick</h2>
        <p>Diese Seite beschreibt die Konfiguration und die Funktionen im Admin-Bereich.
        Eine ausführlichere technische Fassung liegt zusätzlich als <code>DOKU.md</code>
        im Projektverzeichnis.</p>
        <table>
            <tr><th>Datei</th><th>Zweck</th></tr>
            <tr><td><code>kartei.php</code></td><td>Hauptanwendung – Google-Login <strong>oder</strong> Gast-Modus, gleiche Lernfunktionen (auch erreichbar als <code>index.php</code>, <code>kartei4.php</code>)</td></tr>
            <tr><td><code>admin.php</code></td><td>Diese Admin-Werkzeuge</td></tr>
            <tr><td><code>admin-doku.php</code></td><td>Diese Seite</td></tr>
            <tr><td><code>config.php</code></td><td>Zugangsdaten und Einstellungen</td></tr>
        </table>
        <p>Auf der Login-Seite gibt es neben dem Google-Button auch "Ohne Anmeldung
        fortfahren (Gast)". Beide führen zur gleichen Lernoberfläche; der Admin-Link
        erscheint nur bei echtem Login mit einer autorisierten Sub-ID, nie im
        Gast-Modus. Alle Gäste teilen sich bewusst einen gemeinsamen Lernstand pro Datei.</p>
    </div>

    <div class="card-panel">
        <h2>Einstellungen (<code>config.php</code>)</h2>
        <pre>$googleClientId = "...";      // Google OAuth Client-ID für den Login-Button
$authorizedUsers = [           // Google-Sub-IDs mit Admin-Rechten
    "115838474626364014898",
    "105920009078675839078"
];
$debugMode = true;             // true = PHP-Fehler sichtbar (im Live-Betrieb auf false stellen)</pre>
        <ul>
            <li><strong>googleClientId</strong> – wird im Login-Button eingebettet und bei der
                Token-Prüfung serverseitig als erwartete "Audience" (<code>aud</code>) verglichen.</li>
            <li><strong>authorizedUsers</strong> – nur diese Google-Sub-IDs sehen den "Admin"-Link
                in <code>kartei.php</code> und dürfen <code>admin.php</code> aufrufen. Die eigene
                Sub-ID findet man in der Fortschritts-Verwaltung weiter unten.</li>
            <li><strong>debugMode</strong> – sollte im Live-Betrieb auf <code>false</code> stehen,
                damit keine PHP-Fehlermeldungen mit internen Pfaden angezeigt werden.</li>
        </ul>
        <div class="hinweis">Änderungen an <code>config.php</code> sind nur direkt auf dem Server
        möglich (Datei-Upload/Editor) – es gibt bewusst keine Weboberfläche dafür, da hier die
        Zugriffsrechte selbst definiert werden.</div>
    </div>

    <div class="card-panel">
        <h2>Admin-Möglichkeiten (<code>admin.php</code>)</h2>

        <h3>CSV Upload</h3>
        <p>Lädt eine neue <code>.csv</code>-Datei ins Projektverzeichnis. Nur die Endung
        <code>.csv</code> wird akzeptiert, eine bereits vorhandene Datei gleichen Namens wird
        <strong>nicht</strong> überschrieben. Der Inhalt wird zusätzlich grob auf verdächtigen
        PHP-Code geprüft.</p>

        <h3>CSV Zusammenführen</h3>
        <p>Hängt die Zeilen einer "Quelle" an eine "Ziel"-Datei an. Exakte Dubletten (identische
        Zeile) werden automatisch entfernt. Beide Dateien müssen bereits im Projektverzeichnis
        liegen und sich unterscheiden.</p>

        <h3>CSV-Verwaltung</h3>
        <p>Tabelle aller aktiven CSV-Dateien mit Vokabelanzahl, Größe und Änderungsdatum.
        Direkt <strong>umbenennen</strong> oder <strong>löschen</strong> möglich.</p>
        <div class="hinweis">
            <strong>Achtung beim Umbenennen:</strong> Der Lernfortschritt wird pro Datei unter
            einem Hash des Dateinamens gespeichert. Wird eine aktiv genutzte CSV umbenannt, ohne
            die zugehörige Datei in <code>storage/</code> mit umzubenennen, verliert der
            zugehörige Lernstand seine Zuordnung. Bei wichtigen, bereits genutzten Dateien vorher
            kurz Rücksprache halten bzw. den Fortschritt manuell mit migrieren.
        </div>

        <h3>Fortschritts-Verwaltung</h3>
        <p>Zeigt, welche gespeicherten Lernstände existieren: zu welcher CSV-Datei, von welcher
        User-ID, wie groß. Einzelne Lernstände lassen sich gezielt löschen – z. B. um jemandem
        einen sauberen Neustart für eine bestimmte Datei zu ermöglichen, ohne alle anderen
        Lernstände anzufassen.</p>

        <h3>Rohtext → CSV Konverter</h3>
        <p>Für schnell eingetippte oder aus einem Foto abgetippte Vokabellisten im Format
        <code>latein / deutsch</code> (ein Bullet-Punkt <code>•</code> davor ist ok). Kategorie
        und Quelle werden für alle Zeilen gemeinsam gesetzt. Vor dem Speichern gibt es immer
        eine Vorschau-Tabelle; gespeichert wird nur unter einem neuen Dateinamen (keine
        bestehende Datei wird überschrieben).</p>
    </div>

    <div class="card-panel">
        <h2>CSV-Fehler &amp; Benachrichtigung</h2>
        <p>Kann eine Zeile beim Lernen nicht korrekt gelesen werden (kaputte Quotierung,
        fehlender Begriff oder fehlende Definition), wird sie übersprungen statt die App
        abstürzen zu lassen. Gibt es in einer Datei übersprungene Zeilen, schickt
        <code>kartei.php</code> beim Laden <strong>eine</strong> gesammelte Meldung (Dateiname,
        Anzahl, bis zu 10 Beispiele mit Zeilennummer und Grund) an
        <code>https://ntfy.p5k.eu/kids-todos</code>. So bekommt man mit, wenn eine CSV
        fehlerhafte Zeilen enthält, ohne dass die App bei jedem Lernstart wegen einer einzelnen
        kaputten Zeile hakt.</p>
    </div>

    <div class="card-panel">
        <h2>Wo landen die Dateien?</h2>
        <table>
            <tr><th>Ort</th><th>Bedeutung</th></tr>
            <tr><td><code>*.csv</code> im Hauptverzeichnis</td><td>Aktive Vokabel-Dateien, erscheinen im Lern-Dropdown und in der CSV-Verwaltung</td></tr>
            <tr><td><code>old-latein/</code></td><td>Archivierte/abgelöste Latein-Dateien – bleiben erhalten, tauchen aber nicht mehr in der Auswahl auf</td></tr>
            <tr><td><code>ITIL/</code></td><td>Themenfremde CSVs, aus der Latein-Auswahl ausgelagert</td></tr>
            <tr><td><code>storage/</code></td><td>Lernfortschritt als JSON, pro User + CSV-Datei</td></tr>
        </table>
        <p>Unterordner werden von der CSV-Auswahl <strong>nicht</strong> durchsucht – Dateien
        darin bleiben aber über ihre bisherige Adresse direkt erreichbar (z. B. zum
        Nachschlagen oder für einen späteren Merge zurück ins Hauptverzeichnis).</p>
    </div>

    <div class="card-panel">
        <h2>Sicherheit auf einen Blick</h2>
        <table>
            <tr><td><span class="badge">Login</span></td><td>Google-ID-Token wird serverseitig bei Google verifiziert (Signatur, Empfänger, Ablaufzeit) – kein selbstgebautes Token kann eine fremde Identität vortäuschen.</td></tr>
            <tr><td><span class="badge">CSRF</span></td><td>Jedes Formular in <code>admin.php</code> schickt ein Sitzungs-Token mit, das vor jeder Aktion geprüft wird.</td></tr>
            <tr><td><span class="badge">Whitelist</span></td><td>Umbenennen/Löschen/Zusammenführen akzeptieren nur Dateinamen, die tatsächlich im Verzeichnis vorhanden sind – keine beliebigen Pfade aus dem Formular.</td></tr>
            <tr><td><span class="badge">Gast-Modus</span></td><td>Bewusst ohne Login-Hürde – schnell nutzbar, aber ohne echte Identität, daher teilen sich alle Gäste einen gemeinsamen Lernstand pro Datei.</td></tr>
        </table>
    </div>
</div>
</body>
</html>
