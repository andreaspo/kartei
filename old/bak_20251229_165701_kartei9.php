<?php
session_start();

$storageDir = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
if (!is_dir($storageDir)) mkdir($storageDir, 0777, true);

// --- SERVER LOGIK ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    if ($_POST['action'] === 'login' && isset($_POST['id_token'])) {
        $parts = explode('.', $_POST['id_token']);
        $payload = json_decode(base64_decode($parts[1]), true);
        if ($payload && isset($payload['sub'])) {
            $_SESSION['user_id'] = $payload['sub'];
            $_SESSION['user_name'] = $payload['name'];
            echo json_encode(["status" => "success"]); exit;
        }
    }
    if ($_POST['action'] === 'load' && isset($_SESSION['user_id'])) {
        $csvFile = $_POST['csvFile'] ?? '';
        $filename = $storageDir . DIRECTORY_SEPARATOR . 'prog_' . $_SESSION['user_id'] . '_' . md5($csvFile) . '.json';
        echo file_exists($filename) ? file_get_contents($filename) : json_encode(null);
        exit;
    }
}

$jsonInput = json_decode(file_get_contents('php://input'), true);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($jsonInput['action']) && $jsonInput['action'] === 'save') {
    header('Content-Type: application/json');
    if (isset($_SESSION['user_id'])) {
        $filename = $storageDir . DIRECTORY_SEPARATOR . 'prog_' . $_SESSION['user_id'] . '_' . md5($jsonInput['csvFile']) . '.json';
        file_put_contents($filename, json_encode($jsonInput['progress']));
        echo json_encode(["status" => "saved"]);
    }
    exit;
}

if (isset($_GET['logout'])) { session_destroy(); header("Location: kartei9.php"); exit; }
$csvFiles = glob("*.csv");
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <title>Lernkartei</title>
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, sans-serif; margin: 0; padding: 0.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: #1a202c; }
        .container { max-width: 800px; margin: 0 auto; padding: 0.5rem; }
        h1 { color: white; text-align: center; margin-bottom: 0.5rem; }
        .user-bar { text-align: right; color: white; font-size: 0.85rem; margin-bottom: 10px; background: rgba(255,255,255,0.15); padding: 8px 15px; border-radius: 20px; }
        .logout-link { color: #ffbcbc; text-decoration: none; margin-left: 10px; font-weight: bold; }
        .card-panel { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); margin-bottom: 1rem; }
        select, button { font-size: 1rem; padding: 0.75rem; border-radius: 8px; border: 2px solid #e2e8f0; width: 100%; cursor: pointer; margin-bottom: 0.5rem; }
        button { background: #667eea; color: white; border: none; font-weight: bold; }
        button.secondary { background: #e2e8f0; color: #4a5568; margin-top: 10px; }
        .radio-group { display: flex; gap: 1rem; margin-bottom: 1rem; }
        .radio-option { flex: 1; }
        .radio-option input { display: none; }
        .radio-option label { display: block; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; text-align: center; background: white; cursor: pointer; }
        .radio-option input:checked + label { background: #667eea; color: white; border-color: #667eea; }
        .flashcard { background: white; padding: 2rem; border-radius: 12px; min-height: 250px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center; border: 2px solid #e2e8f0; margin-bottom: 1rem; position: relative; }
        .meta-info { font-size: 0.75rem; color: #718096; margin-bottom: 1rem; text-transform: uppercase; }
        #term { font-size: 1.8rem; font-weight: 700; }
        #definition { display: none; margin-top: 1.5rem; border-top: 2px solid #f0f4f8; padding-top: 1rem; width: 100%; font-size: 1.4rem; color: #4a5568; }
        .source-info { font-size: 0.7rem; color: #a0aec0; margin-top: 1rem; font-style: italic; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; text-align: center; background: rgba(255,255,255,0.9); padding: 10px; border-radius: 8px; }
        .stat-val { display: block; font-size: 1.2rem; font-weight: bold; color: #667eea; }
        #login-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 1000; display: flex; justify-content: center; align-items: center; flex-direction: column; color: white; text-align: center; padding: 20px; }
    </style>
</head>
<body>

<?php if (!$isLoggedIn): ?>
<div id="login-overlay">
    <h2>Lernkartei</h2>
    <div id="g_id_onload" data-client_id="544492349379-8nu9c7b67aafju6eavq535mfv2570r76.apps.googleusercontent.com" data-callback="handleCredentialResponse"></div>
    <div class="g_id_signin" data-type="standard"></div>
</div>
<?php endif; ?>

<div class="container" style="display: <?= $isLoggedIn ? 'block' : 'none' ?>;">
    <?php if ($isLoggedIn): ?>
    <div class="user-bar">👤 <?= htmlspecialchars($_SESSION['user_name']) ?> <a href="?logout=1" class="logout-link">Abmelden</a></div>
    <?php endif; ?>

    <h1>Lernkartei</h1>
    
    <div class="card-panel controls" id="setup-area">
        <label>CSV-Datei wählen:</label>
        <select id="csvSelect">
            <option value="">-- Bitte wählen --</option>
            <?php foreach ($csvFiles as $file): ?>
                <option value="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars($file) ?></option>
            <?php endforeach; ?>
        </select>
        <label>Abfragerichtung:</label>
        <div class="radio-group">
            <div class="radio-option"><input type="radio" id="dirBeg" name="direction" value="begriff" checked><label for="dirBeg">Begriff → Def.</label></div>
            <div class="radio-option"><input type="radio" id="dirDef" name="direction" value="definition"><label for="dirDef">Def. → Begriff</label></div>
        </div>
        <button id="loadBtn">Lernen starten</button>
    </div>

    <div id="card-area" style="display:none;">
        <div class="flashcard">
            <div id="meta" class="meta-info"></div>
            <div id="term"></div>
            <div id="definition"></div>
            <div id="source" class="source-info"></div>
        </div>
        <button id="showBtn">Antwort zeigen</button>
        <div id="answer-options" style="display:none; gap:10px;">
            <button id="wrongBtn" style="background:#fc8181; flex:1;">❌ Nicht gewusst</button>
            <button id="rightBtn" style="background:#68d391; flex:1;">✅ Gewusst</button>
        </div>
        <div class="stats-grid">
            <div><span class="stat-val" id="s1">0</span><small>Stufe 1</small></div>
            <div><span class="stat-val" id="s2">0</span><small>Stufe 2</small></div>
            <div><span class="stat-val" id="s3">0</span><small>Stufe 3 ✓</small></div>
        </div>
        <button id="resetBtn" class="secondary">Fortschritt löschen</button>
    </div>
</div>

<script>
let allCards = [], stage1 = [], stage2 = [], stage3 = [], currentCard = null, csvFilename = "", direction = "begriff";

function shuffle(a) {
    for (let i = a.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
}

function handleCredentialResponse(response) {
    const body = new FormData();
    body.append('action', 'login');
    body.append('id_token', response.credential);
    fetch('', { method: 'POST', body }).then(() => location.reload());
}

async function saveProgress() {
    const payload = { action: 'save', csvFile: csvFilename, progress: { stage1, stage2, stage3, direction } };
    await fetch('', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
}

function parseCSV(text) {
    const lines = text.split(/\r?\n/).filter(l => l.trim() !== "");
    return lines.slice(1).map(line => {
        const f = line.split(/,(?=(?:(?:[^\""]*\""){2})*[^\""]*$)/).map(s => s.replace(/^\"|\"$/g, '').trim());
        return { id: btoa(f[1]+f[2]), cat: f[0] || '', term: f[1] || '', def: f[2] || '', src: f[3] || '' };
    });
}

document.getElementById("loadBtn").addEventListener("click", async () => {
    csvFilename = document.getElementById("csvSelect").value;
    direction = document.querySelector('input[name="direction"]:checked').value;
    if (!csvFilename) return;

    const text = await fetch(csvFilename).then(r => r.text());
    allCards = parseCSV(text);
    const body = new FormData();
    body.append('action', 'load');
    body.append('csvFile', csvFilename);
    const res = await fetch('', { method: 'POST', body });
    const saved = await res.json();

    if (saved) {
        stage1 = saved.stage1; stage2 = saved.stage2; stage3 = saved.stage3;
        if(saved.direction) direction = saved.direction;
    } else {
        stage1 = shuffle(allCards.map(c => ({...c})));
        stage2 = []; stage3 = [];
    }
    document.getElementById("setup-area").style.display = "none";
    document.getElementById("card-area").style.display = "block";
    pickNextCard();
});

function pickNextCard() {
    // Kombinierter Pool aus Stufe 1 und 2 für echte Durchmischung
    let pool = [...stage1, ...stage2];
    
    if (pool.length === 0) {
        document.getElementById("card-area").innerHTML = "<h2>🎉 Alles gelernt!</h2><button onclick='location.reload()'>Menü</button>";
        return;
    }

    // Wir ziehen zufällig aus dem kombinierten Pool von S1 und S2
    currentCard = pool[Math.floor(Math.random() * pool.length)];
    
    document.getElementById("meta").textContent = currentCard.cat;
    document.getElementById("term").textContent = (direction === "begriff") ? currentCard.term : currentCard.def;
    document.getElementById("definition").textContent = (direction === "begriff") ? currentCard.def : currentCard.term;
    document.getElementById("source").textContent = currentCard.src ? "Quelle: " + currentCard.src : "";
    document.getElementById("definition").style.display = "none";
    document.getElementById("showBtn").style.display = "block";
    document.getElementById("answer-options").style.display = "none";
    updateStats();
}

document.getElementById("showBtn").addEventListener("click", () => {
    document.getElementById("definition").style.display = "block";
    document.getElementById("showBtn").style.display = "none";
    document.getElementById("answer-options").style.display = "flex";
});

document.getElementById("rightBtn").addEventListener("click", async () => {
    const id = currentCard.id;
    // Wenn in S1 -> nach S2. Wenn in S2 -> nach S3.
    if (stage1.some(c => c.id === id)) {
        stage1 = stage1.filter(c => c.id !== id);
        stage2.push(currentCard);
    } else if (stage2.some(c => c.id === id)) {
        stage2 = stage2.filter(c => c.id !== id);
        stage3.push(currentCard);
    }
    await saveProgress();
    pickNextCard();
});

document.getElementById("wrongBtn").addEventListener("click", async () => {
    const id = currentCard.id;
    // Wenn falsch, landet Karte immer in S1 (egal ob sie in S1 oder S2 war)
    if (stage2.some(c => c.id === id)) {
        stage2 = stage2.filter(c => c.id !== id);
        stage1.push(currentCard);
    }
    // Falls sie schon in S1 war, bleibt sie dort (keine Änderung der Arrays nötig)
    
    shuffle(stage1); // S1 neu mischen
    await saveProgress();
    pickNextCard();
});

document.getElementById("resetBtn").addEventListener("click", async () => {
    if (!confirm("Reset?")) return;
    stage1 = shuffle(allCards.map(c => ({...c})));
    stage2 = []; stage3 = [];
    await saveProgress();
    location.reload();
});

function updateStats() {
    document.getElementById("s1").textContent = stage1.length;
    document.getElementById("s2").textContent = stage2.length;
    document.getElementById("s3").textContent = stage3.length;
}
</script>
</body>
</html>
