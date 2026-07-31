<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Config optional laden
if (file_exists('config.php')) {
    include 'config.php';
}

// Verzeichnis für Speicherstände
$storageDir = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
if (!is_dir($storageDir)) @mkdir($storageDir, 0777, true);

// Fallback falls Variablen in config.php fehlen
if (!isset($googleClientId)) $googleClientId = "";
if (!isset($authorizedUsers)) $authorizedUsers = [];

// Admin-Status prüfen (nur wenn eingeloggt)
$isAdmin = isset($_SESSION['user_id']) && in_array($_SESSION['user_id'], $authorizedUsers);

// --- AJAX LOGIK ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jsonInput = json_decode(file_get_contents('php://input'), true);
    $action = $_POST['action'] ?? ($jsonInput['action'] ?? null);

    if ($action === 'load') {
        header('Content-Type: application/json');
        $csvFile = $_POST['csvFile'] ?? '';
        // Nutze 'guest' als ID, wenn nicht eingeloggt
        $userId = $_SESSION['user_id'] ?? 'guest';
        $filename = $storageDir . DIRECTORY_SEPARATOR . 'prog_' . $userId . '_' . md5($csvFile) . '.json';
        
        if (file_exists($filename)) {
            echo file_get_contents($filename);
        } else {
            echo json_encode(["status" => "no_file"]);
        }
        exit;
    }

    if ($action === 'save') {
        header('Content-Type: application/json');
        $userId = $_SESSION['user_id'] ?? 'guest';
        $filename = $storageDir . DIRECTORY_SEPARATOR . 'prog_' . $userId . '_' . md5($jsonInput['csvFile']) . '.json';
        file_put_contents($filename, json_encode($jsonInput['progress']));
        echo json_encode(["status" => "saved", "user" => $userId]);
        exit;
    }
}

$csvFiles = glob("*.csv");
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <title>Lernkartei (Gast-Modus)</title>
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <style>
        body { font-family: sans-serif; background: #f0f2f5; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        select, button { width: 100%; padding: 12px; margin: 10px 0; border-radius: 8px; border: 1px solid #ccc; font-size: 16px; }
        button { background: #4A90E2; color: white; border: none; cursor: pointer; font-weight: bold; }
        .flashcard { border: 2px solid #eee; padding: 40px; text-align: center; margin: 20px 0; min-height: 150px; border-radius: 8px; }
        #term { font-size: 24px; font-weight: bold; }
        #definition { font-size: 20px; color: #555; margin-top: 20px; display: none; border-top: 1px solid #eee; padding-top: 10px; }
        .stats { display: flex; justify-content: space-around; font-size: 12px; color: #888; }
        .user-info { font-size: 12px; text-align: right; margin-bottom: 10px; color: #666; }
    </style>
</head>
<body>

<div class="container">
    <div class="user-info">
        Modus: <strong><?= $isLoggedIn ? 'Eingeloggt (' . htmlspecialchars($_SESSION['user_name']) . ')' : 'Gast (Lokal)' ?></strong>
    </div>

    <h1>Vokabeltrainer</h1>
    
    <div id="setup-area">
        <label>Datei wählen:</label>
        <select id="csvSelect">
            <option value="">-- Bitte wählen --</option>
            <?php foreach ($csvFiles as $file): ?>
                <option value="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars($file) ?></option>
            <?php endforeach; ?>
        </select>
        <button id="loadBtn">Starten</button>
    </div>

    <div id="card-area" style="display:none;">
        <div class="flashcard">
            <div id="term"></div>
            <div id="definition"></div>
        </div>
        <button id="showBtn">Lösung anzeigen</button>
        <div id="controls" style="display:none; display: flex; gap: 10px;">
            <button id="wrongBtn" style="background:#e74c3c;">Falsch</button>
            <button id="rightBtn" style="background:#2ecc71;">Richtig</button>
        </div>
        <div class="stats">
            <span>Stufe 1: <b id="s1">0</b></span>
            <span>Stufe 2: <b id="s2">0</b></span>
            <span>Stufe 3: <b id="s3">0</b></span>
        </div>
        <button onclick="location.reload()" style="background:#95a5a6; margin-top:20px;">Abbrechen</button>
    </div>
</div>

<script>
let stage1 = [], stage2 = [], stage3 = [], currentCard = null, csvFilename = "";

document.getElementById("loadBtn").addEventListener("click", async () => {
    csvFilename = document.getElementById("csvSelect").value;
    if (!csvFilename) return;

    const text = await fetch(csvFilename).then(r => r.text());
    const lines = text.split('\n').filter(l => l.trim() !== "").slice(1);
    const cards = lines.map((l, index) => {
        const parts = l.split(','); // Einfacher Split für Debugging
        return { id: index, term: parts[1]?.replace(/"/g,''), def: parts[2]?.replace(/"/g,'') };
    });

    const formData = new FormData();
    formData.append('action', 'load');
    formData.append('csvFile', csvFilename);
    const res = await fetch('', { method: 'POST', body: formData });
    const saved = await res.json();

    if (saved && saved.stage1) {
        stage1 = saved.stage1; stage2 = saved.stage2; stage3 = saved.stage3;
    } else {
        stage1 = cards; stage2 = []; stage3 = [];
    }

    document.getElementById("setup-area").style.display = "none";
    document.getElementById("card-area").style.display = "block";
    renderNext();
});

function renderNext() {
    let pool = [...stage1, ...stage2];
    if (pool.length === 0) {
        alert("Glückwunsch! Alles gelernt.");
        location.reload();
        return;
    }
    currentCard = pool[Math.floor(Math.random() * pool.length)];
    document.getElementById("term").textContent = currentCard.term;
    document.getElementById("definition").textContent = currentCard.def;
    document.getElementById("definition").style.display = "none";
    document.getElementById("showBtn").style.display = "block";
    document.getElementById("controls").style.display = "none";
    
    document.getElementById("s1").textContent = stage1.length;
    document.getElementById("s2").textContent = stage2.length;
    document.getElementById("s3").textContent = stage3.length;
}

document.getElementById("showBtn").onclick = () => {
    document.getElementById("definition").style.display = "block";
    document.getElementById("showBtn").style.display = "none";
    document.getElementById("controls").style.display = "flex";
};

async function save() {
    await fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save', csvFile: csvFilename, progress: { stage1, stage2, stage3 } })
    });
}

document.getElementById("rightBtn").onclick = async () => {
    const id = currentCard.id;
    if (stage1.some(c => c.id === id)) {
        stage1 = stage1.filter(c => c.id !== id); stage2.push(currentCard);
    } else {
        stage2 = stage2.filter(c => c.id !== id); stage3.push(currentCard);
    }
    await save(); renderNext();
};

document.getElementById("wrongBtn").onclick = async () => {
    const id = currentCard.id;
    if (stage2.some(c => c.id === id)) {
        stage2 = stage2.filter(c => c.id !== id); stage1.push(currentCard);
    }
    await save(); renderNext();
};
</script>
</body>
</html>
