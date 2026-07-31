<?php
session_start();
if (file_exists('config.php')) {
    include 'config.php';
} else {
    // Fallback falls config.php fehlt (nur für Testzwecke, sollte im Live-Betrieb existieren)
    $authorizedUsers = [];
    $googleClientId = "";
    $debugMode = true;
}

if (isset($debugMode) && $debugMode) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

$storageDir = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
if (!is_dir($storageDir)) mkdir($storageDir, 0777, true);

// Prüft ein Google-ID-Token über Googles tokeninfo-Endpunkt (Signatur, aud, iss, exp).
// Gibt das Payload-Array zurück, wenn gültig, sonst null.
function verifyGoogleIdToken($idToken, $expectedAud) {
    if (!$idToken || !$expectedAud) return null;
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
    $response = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_FAILONERROR => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
    } elseif (ini_get('allow_url_fopen')) {
        $response = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 5]]));
    }
    if (!$response) return null;

    $payload = json_decode($response, true);
    if (!is_array($payload) || isset($payload['error'])) return null;
    if (empty($payload['sub'])) return null;
    if (!isset($payload['aud']) || !hash_equals((string)$expectedAud, (string)$payload['aud'])) return null;
    if (!isset($payload['iss']) || !in_array($payload['iss'], ['accounts.google.com', 'https://accounts.google.com'], true)) return null;
    if (!isset($payload['exp']) || (int)$payload['exp'] <= time()) return null;

    return $payload;
}

$isAdmin = isset($_SESSION['user_id']) && in_array($_SESSION['user_id'], $authorizedUsers);

// Upload/Merge/Datei-Verwaltung sind nach admin.php umgezogen (konsolidierter Admin-Bereich).

// --- SERVER LOGIK: AJAX (Login / Load) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        header('Content-Type: application/json');
        $payload = verifyGoogleIdToken($_POST['id_token'] ?? '', $googleClientId);
        if ($payload) {
            $_SESSION['user_id'] = $payload['sub'];
            $_SESSION['user_name'] = $payload['name'] ?? '';
            echo json_encode(["status" => "success"]); exit;
        }
        echo json_encode(["status" => "error", "message" => "Ungültiges Login-Token"]); exit;
    }
    if ($_POST['action'] === 'load') {
        header('Content-Type: application/json');
        $csvFile = $_POST['csvFile'] ?? '';
        $filename = $storageDir . DIRECTORY_SEPARATOR . 'prog_' . ($_SESSION['user_id'] ?? 'guest') . '_' . md5($csvFile) . '.json';
        if (file_exists($filename)) {
            echo file_get_contents($filename);
        } else {
            echo json_encode(["status" => "no_file"]);
        }
        exit;
    }
}

// --- SERVER LOGIK: SAVE ---
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

if (isset($_GET['logout'])) { session_destroy(); header("Location: kartei.php"); exit; }
$csvFiles = glob("*.csv");
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <title>Lernkartei Pro</title>
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        /* Styles identisch wie in deiner Version, nur Card-Panel leicht optimiert */
        * { box-sizing: border-box; }
        body { font-family: -apple-system, sans-serif; margin: 0; padding: 0.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: #1a202c; }
        .container { max-width: 800px; margin: 0 auto; padding: 0.5rem; }
        h1 { color: white; text-align: center; margin-bottom: 0.5rem; }
        .user-bar { text-align: right; color: white; font-size: 0.85rem; margin-bottom: 10px; background: rgba(255,255,255,0.15); padding: 8px 15px; border-radius: 20px; }
        .logout-link { color: #ffbcbc; text-decoration: none; margin-left: 10px; font-weight: bold; }
        .admin-link { color: white; text-decoration: underline; margin-right: 15px; }
        .card-panel { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); margin-bottom: 1rem; }
        select, button, input { font-size: 0.9rem; padding: 0.6rem; border-radius: 8px; border: 2px solid #e2e8f0; width: 100%; margin-bottom: 0.5rem; }
        button { background: #667eea; color: white; border: none; font-weight: bold; cursor: pointer; transition: opacity 0.2s; }
        button:active { opacity: 0.8; }
        .flashcard { background: white; padding: 2rem; border-radius: 12px; min-height: 250px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center; border: 2px solid #e2e8f0; margin-bottom: 1rem; position: relative; }
        #term { font-size: 1.8rem; font-weight: 700; }
        #definition { display: none; margin-top: 1.5rem; border-top: 2px solid #f0f4f8; padding-top: 1rem; width: 100%; font-size: 1.4rem; color: #4a5568; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; text-align: center; background: rgba(255,255,255,0.9); padding: 10px; border-radius: 8px; margin-top: 10px; }
        #login-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 1000; display: flex; justify-content: center; align-items: center; flex-direction: column; color: white; }
    </style>
</head>
<body>

<?php if (!$isLoggedIn): ?>
<div id="login-overlay">
    <h2>Lernkartei</h2>
    <div id="g_id_onload" data-client_id="<?= $googleClientId ?>" data-callback="handleCredentialResponse"></div>
    <div class="g_id_signin" data-type="standard"></div>
</div>
<?php endif; ?>

<div class="container" style="display: <?= $isLoggedIn ? 'block' : 'none' ?>;">
    <div class="user-bar">
        <?php if ($isAdmin): ?><a href="admin.php" class="admin-link">🛠 Admin</a><?php endif; ?>
        👤 <?= htmlspecialchars($_SESSION['user_name'] ?? '') ?> <a href="?logout=1" class="logout-link">Logout</a>
    </div>

    <h1>Lernkartei</h1>
    
    <div class="card-panel" id="setup-area">
        <label>CSV-Datei wählen:</label>
        <select id="csvSelect">
            <option value="">-- Bitte wählen --</option>
            <?php foreach ($csvFiles as $file): ?>
                <option value="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars($file) ?></option>
            <?php endforeach; ?>
        </select>
        <label>Abfragerichtung:</label>
        <select id="directionSelect">
            <option value="begriff">Begriff → Definition</option>
            <option value="definition">Definition → Begriff</option>
        </select>
        <button id="loadBtn">Lernen starten</button>
    </div>

    <div id="card-area" style="display:none;">
        <div class="flashcard">
            <div id="meta" style="font-size:0.75rem; color:#718096; margin-bottom:1rem;"></div>
            <div id="term"></div>
            <div id="definition"></div>
            <div id="source" style="font-size:0.7rem; color:#a0aec0; margin-top:10px;"></div>
        </div>
        
        <button id="showBtn">Antwort zeigen</button>
        
        <div id="answer-options" style="display:none; gap:10px;">
            <button id="wrongBtn" style="background:#fc8181; flex:1;">❌ Falsch</button>
            <button id="rightBtn" style="background:#68d391; flex:1;">✅ Richtig</button>
        </div>

        <div class="stats-grid">
            <div><span style="display:block; font-weight:bold; color:#667eea;" id="s1">0</span><small>Stufe 1</small></div>
            <div><span style="display:block; font-weight:bold; color:#667eea;" id="s2">0</span><small>Stufe 2</small></div>
            <div><span style="display:block; font-weight:bold; color:#667eea;" id="s3">0</span><small>Stufe 3 ✓</small></div>
        </div>
        <button id="resetBtn" style="background:#e2e8f0; color:#4a5568; margin-top:15px;">Fortschritt zurücksetzen</button>
    </div>
</div>

<script>
let allCards = [], stage1 = [], stage2 = [], stage3 = [], sessionQueue = [];
let currentCard = null, csvFilename = "", direction = "begriff";

function shuffle(a) {
    let array = [...a];
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
}

function handleCredentialResponse(response) {
    const body = new FormData();
    body.append('action', 'login');
    body.append('id_token', response.credential);
    fetch('', { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert('Login fehlgeschlagen: ' + (data.message || 'Unbekannter Fehler'));
            }
        });
}

async function saveProgress() {
    const payload = { 
        action: 'save', 
        csvFile: csvFilename, 
        progress: { stage1, stage2, stage3, direction } 
    };
    await fetch('', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
}

function parseCSV(text) {
    const lines = text.split(/\r?\n/).filter(l => l.trim() !== "");
    const idCounts = new Map();
    return lines.slice(1).map(line => {
        const f = line.match(/(".*?"|[^",\s]+)(?=\s*,|\s*$)/g) || [];
        const c = f.map(s => s.replace(/^"|"$/g, '').trim());
        const cat = c[0] || '', term = c[1] || '', def = c[2] || '';
        // ID aus Kategorie+Begriff+Definition, damit gleicher Begriff in
        // unterschiedlichen Kapiteln nicht kollidiert. Echte Dubletten
        // (identische Kategorie+Begriff+Definition) bekommen zusätzlich
        // einen Zähler angehängt, damit jede Karte eine eindeutige ID behält.
        const baseId = btoa(unescape(encodeURIComponent(cat + '|' + term + '|' + def)));
        const n = idCounts.get(baseId) || 0;
        idCounts.set(baseId, n + 1);
        const id = n === 0 ? baseId : (baseId + '_' + n);
        return { id, cat, term, def, src: c[3]||'' };
    }).filter(c => c.term !== "");
}

// Gleicht den gespeicherten Lernfortschritt mit den aktuell aus der CSV
// geladenen Karten ab (per ID statt per Objekt-Snapshot). So bleiben
// Lernstände auch erhalten, wenn die CSV-Datei nachträglich ergänzt oder
// korrigiert wird - neue Karten landen automatisch in Stufe 1, entfernte
// Karten verschwinden aus dem Fortschritt.
// Rückgabewert: true, wenn bereits performFullReset() (inkl. startLearning) ausgelöst wurde.
function reconcileProgress(saved) {
    const byId = new Map(allCards.map(c => [c.id, c]));
    const known = new Set();

    function rebuild(savedStage) {
        const result = [];
        for (const oldCard of (savedStage || [])) {
            const fresh = byId.get(oldCard && oldCard.id);
            if (fresh) {
                result.push(fresh);
                known.add(fresh.id);
            }
        }
        return result;
    }

    stage1 = rebuild(saved.stage1);
    stage2 = rebuild(saved.stage2);
    stage3 = rebuild(saved.stage3);
    sessionQueue = [];

    const neueKarten = allCards.filter(c => !known.has(c.id));
    stage1.push(...shuffle(neueKarten));

    // Nur wenn wirklich alle aktuellen Karten in Stufe 3 sind (keine neuen,
    // keine offenen) -> Reset anbieten.
    if (stage1.length === 0 && stage2.length === 0 && stage3.length > 0) {
        if (confirm("Du hast diese Datei bereits abgeschlossen! Möchtest du von vorne beginnen?")) {
            performFullReset();
            return true;
        }
    }
    return false;
}

document.getElementById("loadBtn").addEventListener("click", async () => {
    csvFilename = document.getElementById("csvSelect").value;
    direction = document.getElementById("directionSelect").value;
    if (!csvFilename) return;

    const text = await fetch(csvFilename).then(r => r.text());
    allCards = parseCSV(text);

    const body = new FormData();
    body.append('action', 'load');
    body.append('csvFile', csvFilename);
    const res = await fetch('', { method: 'POST', body });
    const saved = await res.json();

    let handled = false;
    if (saved && saved.stage1) {
        handled = reconcileProgress(saved);
    } else {
        performFullReset(false);
    }

    if (!handled) startLearning();
});

function performFullReset(doSave = true) {
    stage1 = shuffle(allCards.map(c => ({...c})));
    stage2 = [];
    stage3 = [];
    sessionQueue = [];
    if(doSave) saveProgress().then(startLearning);
    else startLearning();
}

function startLearning() {
    document.getElementById("setup-area").style.display = "none";
    document.getElementById("card-area").style.display = "block";
    pickNextCard();
}

function pickNextCard() {
    // Wenn die Warteschlange leer ist, fülle sie mit Stufe 1 und 2 neu
    if (sessionQueue.length === 0) {
        sessionQueue = shuffle([...stage1, ...stage2]);
    }

    if (sessionQueue.length === 0) {
        // Falls wirklich gar nichts mehr da ist -> Fertig!
        document.getElementById("card-area").innerHTML = "<h2>🎉 Alles gelernt!</h2><button onclick='location.reload()'>Zum Hauptmenü</button>";
        return;
    }

    // Nimm die nächste Karte aus der Warteschlange
    currentCard = sessionQueue.pop();
    
    // UI Update
    document.getElementById("meta").textContent = currentCard.cat;
    document.getElementById("term").textContent = (direction === "begriff") ? currentCard.term : currentCard.def;
    document.getElementById("definition").textContent = (direction === "begriff") ? currentCard.def : currentCard.term;
    document.getElementById("source").textContent = currentCard.src ? "Quelle: " + currentCard.src : "";
    
    document.getElementById("definition").style.display = "none";
    document.getElementById("showBtn").style.display = "block";
    document.getElementById("answer-options").style.display = "none";
    
    document.getElementById("s1").textContent = stage1.length;
    document.getElementById("s2").textContent = stage2.length;
    document.getElementById("s3").textContent = stage3.length;
}

document.getElementById("showBtn").addEventListener("click", () => {
    document.getElementById("definition").style.display = "block";
    document.getElementById("showBtn").style.display = "none";
    document.getElementById("answer-options").style.display = "flex";
});

document.getElementById("rightBtn").addEventListener("click", () => {
    const id = currentCard.id;
    // Verschiebe Karte in der Logik
    if (stage1.some(c => c.id === id)) {
        stage1 = stage1.filter(c => c.id !== id);
        stage2.push(currentCard);
    } else {
        stage2 = stage2.filter(c => c.id !== id);
        stage3.push(currentCard);
    }
    // Sofort weiter zur nächsten Karte - nicht auf den Netzwerk-Roundtrip warten.
    // Das Speichern läuft im Hintergrund; das entkoppelt gleichzeitig zwei
    // schnell aufeinanderfolgende Klicks, da currentCard schon aktualisiert ist,
    // bevor ein zweiter Klick überhaupt verarbeitet werden könnte.
    pickNextCard();
    saveProgress().catch(err => console.error("Fortschritt konnte nicht gespeichert werden:", err));
});

document.getElementById("wrongBtn").addEventListener("click", () => {
    const id = currentCard.id;
    // Wenn in Stufe 2, zurück zu Stufe 1
    if (stage2.some(c => c.id === id)) {
        stage2 = stage2.filter(c => c.id !== id);
        stage1.push(currentCard);
    }
    // Hinweis: Karte bleibt für diese Session aus der sessionQueue draußen,
    // kommt aber im nächsten Durchgang (wenn Queue leer) wieder in Stufe 1 dran.
    pickNextCard();
    saveProgress().catch(err => console.error("Fortschritt konnte nicht gespeichert werden:", err));
});

document.getElementById("resetBtn").addEventListener("click", () => {
    if (confirm("Wirklich den gesamten Fortschritt für diese Datei löschen?")) {
        performFullReset();
    }
});
</script>
</body>
</html>
