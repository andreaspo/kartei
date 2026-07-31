<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

if (file_exists('config.php')) {
    include 'config.php';
} else {
    $authorizedUsers = [];
}

// Diese Seite listet User-IDs auf und ist damit nur für Admins gedacht -
// ohne diese Prüfung war sie für jeden Besucher ohne Login einsehbar.
$currentUserId = $_SESSION['user_id'] ?? null;
$isAdmin = $currentUserId && in_array($currentUserId, $authorizedUsers, true);
if (!$isAdmin) {
    http_response_code(403);
    echo "<p>Kein Zugriff. Diese Seite ist nur für Admins sichtbar.</p>";
    exit;
}

echo "<h2>Speicherstand-Diagnose</h2>";

echo "<div style='background:#f0f8ff; padding:15px; border:1px solid #add8e6; border-radius:8px; margin-bottom:20px;'>";
echo "Dein Name: <strong>" . htmlspecialchars($_SESSION['user_name'] ?? 'Unbekannt') . "</strong><br>";
echo "Deine User-ID (sub): <strong style='color:blue; font-size:1.2em;'>" . htmlspecialchars($currentUserId) . "</strong>";
echo "<p><small>Deine Einträge sind in der Tabelle unten <span style='color:blue; font-weight:bold;'>blau markiert</span>.</small></p>";
echo "</div>";

$storageDir = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
if (!is_dir($storageDir)) {
    die("<p style='color:red;'>Ordner 'storage/' nicht gefunden!</p>");
}

// 2. CSV-Hashes vorbereiten
$csvFiles = glob("*.csv");
$hashMap = [];
foreach ($csvFiles as $csv) {
    $hashMap[md5($csv)] = $csv;
}

// 3. JSON-Dateien einlesen und für Sortierung vorbereiten
$jsonFiles = glob($storageDir . DIRECTORY_SEPARATOR . "*.json");
$dataRows = [];

foreach ($jsonFiles as $fullPath) {
    $filename = basename($fullPath);
    $cleanName = str_replace(['prog_', '.json'], '', $filename);
    $parts = explode('_', $cleanName);
    
    $userId = $parts[0] ?? 'unbekannt';
    $fileHash = $parts[1] ?? 'unbekannt';
    $csvName = $hashMap[$fileHash] ?? "Z_Unbekannt_$fileHash"; // Z_ für Sortierung nach hinten

    $dataRows[] = [
        'filename' => $filename,
        'csvName'  => $csvName,
        'userId'   => $userId,
        'size'     => filesize($fullPath),
        'isMe'     => ($currentUserId && $userId === $currentUserId)
    ];
}

// 4. Sortieren nach CSV-Name
usort($dataRows, function($a, $b) {
    return strcasecmp($a['csvName'], $b['csvName']);
});

// 5. Tabelle ausgeben
echo "<h3>Vorhandene Speicherstände (sortiert nach CSV)</h3>";
echo "<table border='1' cellpadding='8' style='border-collapse:collapse; width:100%; font-family:sans-serif;'>
        <tr style='background:#eee; text-align:left;'>
            <th>CSV-Datei</th>
            <th>User-ID</th>
            <th>Dateiname in /storage/</th>
            <th>Größe</th>
        </tr>";

foreach ($dataRows as $row) {
    // Blau-Markierung für eigene User-ID
    $style = $row['isMe'] ? "style='color:blue; font-weight:bold;'" : "";
    $csvDisplay = ($row['csvName'][0] === 'Z' && strpos($row['csvName'], 'Unbekannt') !== false) 
                  ? "<i style='color:grey;'>Unbekannte CSV (Hash: ".substr($row['csvName'],12).")</i>" 
                  : "<strong>" . htmlspecialchars($row['csvName']) . "</strong>";

    echo "<tr $style>
            <td>$csvDisplay</td>
            <td>" . htmlspecialchars($row['userId']) . "</td>
            <td><code>" . htmlspecialchars($row['filename']) . "</code></td>
            <td>" . number_format($row['size'] / 1024, 2) . " KB</td>
          </tr>";
}
echo "</table>";

echo "<p><br><a href='kartei.php'>Zurück zum Hauptskript</a></p>";
?>
