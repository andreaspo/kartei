<?php
session_start();
if (file_exists('config.php')) {
    include 'config.php';
} else {
    $authorizedUsers = [];
    $googleClientId = "";
    $debugMode = true;
}

if (isset($debugMode) && $debugMode) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// Kein eigener Login-Flow: Anmeldung läuft über kartei.php, hier wird nur die
// bestehende Session geprüft. Ohne Admin-Rechte -> zurück zum Lernbereich.
$isAdmin = isset($_SESSION['user_id']) && in_array($_SESSION['user_id'], $authorizedUsers, true);
if (!$isAdmin) {
    header('Location: kartei.php');
    exit;
}

$storageDir = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
if (!is_dir($storageDir)) mkdir($storageDir, 0777, true);

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf'];

function csrfValid($token) {
    return isset($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}

function getCsvFiles() {
    return glob("*.csv");
}

// Eingebettete Anführungszeichen durch Apostrophe ersetzen (bestehende
// Konvention dieses Projekts), damit das simple quotierte CSV-Format nicht bricht.
function csvEscapeField($value) {
    return str_replace('"', "'", $value);
}

function writeCsvRows($path, $header, $rows) {
    $lines = [];
    $lines[] = '"' . implode('","', array_map('csvEscapeField', $header)) . '"';
    foreach ($rows as $row) {
        $lines[] = '"' . implode('","', array_map('csvEscapeField', $row)) . '"';
    }
    file_put_contents($path, implode("\n", $lines) . "\n");
}

// Entspricht der Logik von convert2.py: Rohtext-Zeilen im Format "latein / deutsch"
// (mit oder ohne führendem Bullet-Zeichen) in CSV-Zeilen umwandeln.
function convertRawToRows($rawText, $kategorie, $quelle) {
    $rows = [];
    foreach (preg_split('/\r\n|\r|\n/', $rawText) as $line) {
        $line = trim($line);
        $line = ltrim($line, "•\t ");
        $line = trim($line);
        if ($line === '' || strpos($line, '/') === false) continue;
        [$lat, $deu] = array_pad(explode('/', $line, 2), 2, '');
        $lat = trim(preg_replace('/\s+/', ' ', $lat));
        $deu = trim(preg_replace('/\s+/', ' ', $deu));
        if ($lat === '' || $deu === '') continue;
        $rows[] = [$kategorie, $lat, $deu, $quelle];
    }
    return $rows;
}

// Setzt eine Flash-Message in der Session und leitet auf sich selbst um
// (Post-Redirect-Get), damit ein Reload keine Aktion erneut auslöst.
function redirectWithMessage($msg) {
    $_SESSION['flash'] = $msg;
    header('Location: admin.php');
    exit;
}

$message = '';
if (!empty($_SESSION['flash'])) {
    $message = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

$action = $_POST['action'] ?? '';
$csrfOk = csrfValid($_POST['csrf'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== '' && !$csrfOk) {
    $message = "❌ Sicherheitsprüfung fehlgeschlagen (CSRF). Bitte Seite neu laden und erneut versuchen.";
}

// --- UPLOAD ---
if ($csrfOk && $action === 'upload' && isset($_FILES['csvUpload'])) {
    $file = $_FILES['csvUpload'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $safeName = basename($file['name']);
    $dest = __DIR__ . DIRECTORY_SEPARATOR . $safeName;
    $content = @file_get_contents($file['tmp_name']);

    if ($ext !== 'csv') {
        redirectWithMessage("❌ Nur CSV erlaubt.");
    } elseif ($content !== false && stripos($content, '<?php') !== false) {
        redirectWithMessage("❌ Datei abgelehnt: enthält verdächtigen Inhalt.");
    } elseif (file_exists($dest)) {
        redirectWithMessage("⚠️ Datei existiert bereits.");
    } elseif (move_uploaded_file($file['tmp_name'], $dest)) {
        redirectWithMessage("✅ Upload erfolgreich: " . htmlspecialchars($safeName));
    } else {
        redirectWithMessage("❌ Upload fehlgeschlagen.");
    }
}

// --- MERGE ---
if ($csrfOk && $action === 'mergeFiles') {
    $allowedCsvFiles = getCsvFiles();
    $source = basename($_POST['sourceFile'] ?? '');
    $target = basename($_POST['targetFile'] ?? '');
    if ($source && $target && $source !== $target
        && in_array($source, $allowedCsvFiles, true)
        && in_array($target, $allowedCsvFiles, true)) {
        $sourceContent = file($source, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $targetContent = file($target, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $header = $targetContent[0];
        $existingLines = array_map('trim', array_slice($targetContent, 1));
        $newLines = array_map('trim', array_slice($sourceContent, 1));
        $combined = array_unique(array_merge($existingLines, $newLines));
        file_put_contents($target, $header . "\n" . implode("\n", $combined));
        redirectWithMessage("✅ Kombiniert in: " . htmlspecialchars($target));
    } else {
        redirectWithMessage("❌ Ungültige Dateiauswahl.");
    }
}

// --- CSV UMBENENNEN ---
if ($csrfOk && $action === 'renameCsv') {
    $allowedCsvFiles = getCsvFiles();
    $old = basename($_POST['oldName'] ?? '');
    $new = basename(trim($_POST['newName'] ?? ''));
    if ($new !== '' && strtolower(pathinfo($new, PATHINFO_EXTENSION)) !== 'csv') {
        $new .= '.csv';
    }
    if (!$old || !in_array($old, $allowedCsvFiles, true)) {
        redirectWithMessage("❌ Ausgangsdatei ungültig.");
    } elseif ($new === '' || $new === $old) {
        redirectWithMessage("❌ Neuer Dateiname ungültig.");
    } elseif (file_exists($new)) {
        redirectWithMessage("⚠️ Zieldatei existiert bereits.");
    } elseif (rename($old, $new)) {
        redirectWithMessage("✅ Umbenannt: " . htmlspecialchars($old) . " → " . htmlspecialchars($new));
    } else {
        redirectWithMessage("❌ Umbenennen fehlgeschlagen.");
    }
}

// --- CSV LÖSCHEN ---
if ($csrfOk && $action === 'deleteCsv') {
    $allowedCsvFiles = getCsvFiles();
    $target = basename($_POST['fileName'] ?? '');
    if ($target && in_array($target, $allowedCsvFiles, true) && unlink($target)) {
        redirectWithMessage("✅ Gelöscht: " . htmlspecialchars($target));
    } else {
        redirectWithMessage("❌ Löschen fehlgeschlagen oder Datei ungültig.");
    }
}

// --- LERNSTAND LÖSCHEN ---
if ($csrfOk && $action === 'deleteProgress') {
    $allowedProgressFiles = array_map('basename', glob($storageDir . DIRECTORY_SEPARATOR . 'prog_*.json'));
    $target = basename($_POST['fileName'] ?? '');
    if ($target && in_array($target, $allowedProgressFiles, true) && unlink($storageDir . DIRECTORY_SEPARATOR . $target)) {
        redirectWithMessage("✅ Lernstand gelöscht.");
    } else {
        redirectWithMessage("❌ Löschen fehlgeschlagen oder Datei ungültig.");
    }
}

// --- ROHTEXT-KONVERTER (Vorschau + Speichern, kein Redirect: Eingaben sollen
//     bei Fehlern erhalten bleiben) ---
$convertRawText = $_POST['rawText'] ?? '';
$convertKategorie = trim($_POST['kategorie'] ?? '');
$convertQuelle = trim($_POST['quelle'] ?? 'Latein Vokabel');
$convertDateiname = trim($_POST['dateiname'] ?? '');
$convertedRows = null;

if ($csrfOk && ($action === 'convertPreview' || $action === 'saveConverted')) {
    $convertedRows = convertRawToRows($convertRawText, $convertKategorie, $convertQuelle);

    if ($action === 'saveConverted') {
        $safeName = basename($convertDateiname);
        if ($safeName === '') {
            $message = "❌ Bitte einen Dateinamen angeben.";
        } else {
            if (strtolower(pathinfo($safeName, PATHINFO_EXTENSION)) !== 'csv') {
                $safeName .= '.csv';
            }
            if (file_exists($safeName)) {
                $message = "⚠️ Datei existiert bereits: " . htmlspecialchars($safeName);
            } elseif (empty($convertedRows)) {
                $message = "❌ Keine gültigen Zeilen zum Speichern gefunden.";
            } else {
                writeCsvRows($safeName, ["Kategorie", "Begriff", "Definition", "Quelle"], $convertedRows);
                $message = "✅ Gespeichert: " . htmlspecialchars($safeName) . " (" . count($convertedRows) . " Vokabeln)";
                $convertedRows = null;
                $convertRawText = '';
                $convertDateiname = '';
            }
        }
    }
}

// --- Daten für die Ansicht ---
$csvFiles = getCsvFiles();

$csvInfo = [];
foreach ($csvFiles as $csv) {
    $lines = @file($csv, FILE_SKIP_EMPTY_LINES);
    $csvInfo[] = [
        'name' => $csv,
        'rows' => $lines ? max(count($lines) - 1, 0) : 0,
        'size' => filesize($csv),
        'mtime' => filemtime($csv),
    ];
}

$hashMap = [];
foreach ($csvFiles as $csv) {
    $hashMap[md5($csv)] = $csv;
}
$progressRows = [];
foreach (glob($storageDir . DIRECTORY_SEPARATOR . "prog_*.json") as $fullPath) {
    $filename = basename($fullPath);
    $cleanName = str_replace(['prog_', '.json'], '', $filename);
    $parts = explode('_', $cleanName);
    $userId = $parts[0] ?? 'unbekannt';
    $fileHash = $parts[1] ?? 'unbekannt';
    $progressRows[] = [
        'filename' => $filename,
        'csvName'  => $hashMap[$fileHash] ?? null,
        'userId'   => $userId,
        'size'     => filesize($fullPath),
    ];
}
usort($progressRows, function($a, $b) {
    return strcasecmp($a['csvName'] ?? 'zzz_unbekannt', $b['csvName'] ?? 'zzz_unbekannt');
});
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <title>Lernkartei – Admin</title>
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, sans-serif; margin: 0; padding: 0.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: #1a202c; }
        .container { max-width: 900px; margin: 0 auto; padding: 0.5rem; }
        h1 { color: white; text-align: center; margin-bottom: 0.5rem; }
        .top-bar { text-align: right; margin-bottom: 10px; }
        .top-bar a { color: white; background: rgba(255,255,255,0.15); padding: 8px 15px; border-radius: 20px; text-decoration: none; font-size: 0.85rem; }
        .card-panel { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); margin-bottom: 1.5rem; }
        .card-panel h2 { margin-top: 0; font-size: 1.1rem; }
        select, button, input, textarea { font-size: 0.9rem; padding: 0.6rem; border-radius: 8px; border: 2px solid #e2e8f0; width: 100%; margin-bottom: 0.5rem; font-family: inherit; }
        textarea { min-height: 140px; resize: vertical; }
        button { background: #667eea; color: white; border: none; font-weight: bold; cursor: pointer; }
        button.danger { background: #fc8181; }
        button.small { width: auto; padding: 0.35rem 0.7rem; margin: 0; font-size: 0.8rem; }
        .message { padding: 10px 15px; border-radius: 8px; margin-bottom: 1rem; background: #f0f4f8; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 0.5rem; font-size: 0.85rem; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
        .inline-form { display: inline; margin: 0; }
        .row-actions { display: flex; gap: 6px; }
        .row-actions form { flex: 1; margin: 0; }
        .row-actions input, .row-actions select { margin-bottom: 0; }
    </style>
</head>
<body>
<div class="container">
    <div class="top-bar"><a href="kartei.php">⬅ Zurück zur Lernkartei</a></div>
    <h1>🛠 Admin-Bereich</h1>

    <?php if ($message): ?>
        <div class="message"><?= $message ?></div>
    <?php endif; ?>

    <div class="card-panel">
        <h2>CSV Upload</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="file" name="csvUpload" accept=".csv" required>
            <button type="submit">Hochladen</button>
        </form>
    </div>

    <div class="card-panel">
        <h2>CSV Zusammenführen</h2>
        <form method="post">
            <input type="hidden" name="action" value="mergeFiles">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>">
            <label>Quelle (wird angehängt):</label>
            <select name="sourceFile" required>
                <?php foreach ($csvFiles as $f): ?>
                    <option value="<?= htmlspecialchars($f) ?>"><?= htmlspecialchars($f) ?></option>
                <?php endforeach; ?>
            </select>
            <label>Ziel (wird erweitert):</label>
            <select name="targetFile" required>
                <?php foreach ($csvFiles as $f): ?>
                    <option value="<?= htmlspecialchars($f) ?>"><?= htmlspecialchars($f) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" style="background:#48bb78;">Zusammenführen</button>
        </form>
    </div>

    <div class="card-panel">
        <h2>CSV-Verwaltung</h2>
        <table>
            <tr><th>Datei</th><th>Vokabeln</th><th>Größe</th><th>Geändert</th><th>Aktionen</th></tr>
            <?php foreach ($csvInfo as $info): ?>
            <tr>
                <td><?= htmlspecialchars($info['name']) ?></td>
                <td><?= $info['rows'] ?></td>
                <td><?= number_format($info['size'] / 1024, 1) ?> KB</td>
                <td><?= date('d.m.Y H:i', $info['mtime']) ?></td>
                <td class="row-actions">
                    <form method="post" class="inline-form">
                        <input type="hidden" name="action" value="renameCsv">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="oldName" value="<?= htmlspecialchars($info['name']) ?>">
                        <input type="text" name="newName" placeholder="neuer Name" style="width:120px;">
                        <button type="submit" class="small">Umbenennen</button>
                    </form>
                    <form method="post" class="inline-form" onsubmit="return confirm('Datei &quot;<?= htmlspecialchars($info['name'], ENT_QUOTES) ?>&quot; wirklich löschen?');">
                        <input type="hidden" name="action" value="deleteCsv">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="fileName" value="<?= htmlspecialchars($info['name']) ?>">
                        <button type="submit" class="small danger">Löschen</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="card-panel">
        <h2>Fortschritts-Verwaltung</h2>
        <table>
            <tr><th>CSV-Datei</th><th>User-ID</th><th>Größe</th><th>Aktion</th></tr>
            <?php foreach ($progressRows as $row): ?>
            <tr>
                <td><?= $row['csvName'] ? htmlspecialchars($row['csvName']) : "<i style='color:grey;'>unbekannte CSV</i>" ?></td>
                <td><?= htmlspecialchars($row['userId']) ?></td>
                <td><?= number_format($row['size'] / 1024, 2) ?> KB</td>
                <td>
                    <form method="post" class="inline-form" onsubmit="return confirm('Diesen Lernstand wirklich löschen?');">
                        <input type="hidden" name="action" value="deleteProgress">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="fileName" value="<?= htmlspecialchars($row['filename']) ?>">
                        <button type="submit" class="small danger">Löschen</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$progressRows): ?>
            <tr><td colspan="4"><i>Keine gespeicherten Lernstände.</i></td></tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="card-panel">
        <h2>Rohtext → CSV Konverter</h2>
        <p style="font-size:0.85rem; color:#718096;">Eine Zeile pro Vokabel im Format <code>latein / deutsch</code> (führendes <code>•</code> ist ok).</p>
        <form method="post">
            <input type="hidden" name="action" value="convertPreview">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>">
            <label>Rohtext:</label>
            <textarea name="rawText" placeholder="filia / die Tochter&#10;tamen / dennoch, trotzdem"><?= htmlspecialchars($convertRawText) ?></textarea>
            <label>Kategorie:</label>
            <input type="text" name="kategorie" value="<?= htmlspecialchars($convertKategorie) ?>" placeholder="z.B. Kapitel 15">
            <label>Quelle:</label>
            <input type="text" name="quelle" value="<?= htmlspecialchars($convertQuelle) ?>">
            <button type="submit">Vorschau erzeugen</button>
        </form>

        <?php if ($convertedRows !== null): ?>
            <h3>Vorschau (<?= count($convertedRows) ?> Vokabeln)</h3>
            <table>
                <tr><th>Kategorie</th><th>Begriff</th><th>Definition</th><th>Quelle</th></tr>
                <?php foreach ($convertedRows as $row): ?>
                <tr>
                    <?php foreach ($row as $cell): ?><td><?= htmlspecialchars($cell) ?></td><?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </table>
            <form method="post" style="margin-top:1rem;">
                <input type="hidden" name="action" value="saveConverted">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="rawText" value="<?= htmlspecialchars($convertRawText) ?>">
                <input type="hidden" name="kategorie" value="<?= htmlspecialchars($convertKategorie) ?>">
                <input type="hidden" name="quelle" value="<?= htmlspecialchars($convertQuelle) ?>">
                <label>Dateiname:</label>
                <input type="text" name="dateiname" value="<?= htmlspecialchars($convertDateiname) ?>" placeholder="z.B. latein15.csv" required>
                <button type="submit" style="background:#48bb78;">Als CSV speichern</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
