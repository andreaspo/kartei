<?php
$csvFiles = glob("*.csv");
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8" />
<title>Lernkartei – 3-Stufen-System</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<style>
  * { box-sizing: border-box; }
  body { 
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    margin: 0;
    padding: 0.5rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    color: #1a202c;
  }
  
  .container {
    max-width: 800px;
    margin: 0 auto;
    padding: 0.5rem;
  }
  
  h1 { 
    margin: 0 0 0.75rem 0;
    font-size: clamp(1.25rem, 4vw, 1.75rem);
    color: white;
    text-align: center;
    font-weight: 700;
    text-shadow: 0 2px 10px rgba(0,0,0,0.2);
  }
  
  .card-panel {
    background: white;
    padding: clamp(1rem, 3vw, 1.5rem);
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    margin-bottom: 0.75rem;
  }
  
  .controls { margin-bottom: 0; }
  
  .form-group {
    margin-bottom: 0.75rem;
  }
  
  label {
    display: block;
    margin-bottom: 0.35rem;
    color: #4a5568;
    font-size: 0.85rem;
    font-weight: 500;
  }
  
  select, button {
    font-family: inherit;
    font-size: 0.95rem;
    padding: 0.6rem 0.8rem;
    border-radius: 8px;
    border: 2px solid #e2e8f0;
    width: 100%;
    transition: all 0.2s;
  }
  
  select {
    background: white;
    cursor: pointer;
  }
  
  select:hover {
    border-color: #cbd5e0;
  }
  
  select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  }
  
  .radio-group {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
  }
  
  .radio-option {
    flex: 1;
    min-width: 200px;
  }
  
  .radio-option input[type="radio"] {
    display: none;
  }
  
  .radio-option label {
    display: block;
    padding: 0.65rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
    margin: 0;
  }
  
  .radio-option input[type="radio"]:checked + label {
    background: #667eea;
    border-color: #667eea;
    color: white;
  }
  
  .radio-option label:hover {
    border-color: #667eea;
  }
  
  button {
    background: #667eea;
    color: white;
    border: none;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s;
  }
  
  button:hover {
    background: #5568d3;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
  }
  
  button:active {
    transform: translateY(0);
  }
  
  button.secondary {
    background: #e2e8f0;
    color: #4a5568;
  }
  
  button.secondary:hover {
    background: #cbd5e0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  }
  
  .button-group {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
  }
  
  .button-group button {
    flex: 1;
    min-width: 140px;
  }
  
  #card {
    display: none;
  }
  
  .flashcard {
    background: linear-gradient(135deg, #f6f8fb 0%, #ffffff 100%);
    padding: clamp(1.5rem, 4vw, 2rem);
    border-radius: 12px;
    min-height: 180px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    border: 2px solid #e2e8f0;
    margin-bottom: 0.75rem;
  }
  
  #term {
    font-size: clamp(1.25rem, 4vw, 1.75rem);
    margin: 0;
    color: #1a202c;
    font-weight: 600;
    text-align: center;
    line-height: 1.3;
  }
  
  #definition {
    display: none;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 2px solid #e2e8f0;
    color: #2d3748;
    font-size: clamp(1rem, 3vw, 1.15rem);
    line-height: 1.5;
    text-align: center;
  }
  
  .metadata {
    display: flex;
    gap: 1rem;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid #e2e8f0;
    font-size: 0.8rem;
    color: #718096;
    flex-wrap: wrap;
    justify-content: center;
  }
  
  .metadata-item {
    display: flex;
    align-items: center;
    gap: 0.25rem;
  }
  
  .metadata-label {
    font-weight: 600;
  }
  
  .actions {
    margin-bottom: 0.75rem;
  }
  
  .action {
    padding: 0.7rem 1.2rem;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.95rem;
  }
  
  #showBtn {
    background: #667eea;
    color: white;
  }
  
  #wrongBtn {
    background: #fc8181;
    color: white;
  }
  
  #wrongBtn:hover {
    background: #f56565;
  }
  
  #rightBtn {
    background: #68d391;
    color: white;
  }
  
  #rightBtn:hover {
    background: #48bb78;
  }
  
  #stats {
    background: #f7fafc;
    padding: 0.75rem;
    border-radius: 8px;
    color: #4a5568;
    font-size: 0.85rem;
    text-align: center;
    border: 1px solid #e2e8f0;
  }
  
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 0.75rem;
    margin-top: 0.5rem;
  }
  
  .stat-item {
    text-align: center;
  }
  
  .stat-value {
    display: block;
    font-size: 1.25rem;
    font-weight: 700;
    color: #667eea;
  }
  
  .stat-label {
    display: block;
    font-size: 0.75rem;
    color: #718096;
    margin-top: 0.15rem;
  }
  
  @media (max-width: 640px) {
    body {
      padding: 0.5rem;
    }
    
    .container {
      padding: 0.5rem;
    }
    
    h1 {
      margin-bottom: 1rem;
    }
    
    .radio-group {
      flex-direction: column;
    }
    
    .radio-option {
      min-width: 100%;
    }
    
    .button-group {
      flex-direction: column;
    }
    
    .button-group button {
      width: 100%;
      min-width: 100%;
    }
  }
</style>
</head>
<body>
  <div class="container">
    <h1>🎓 Lernkartei – 3-Stufen-System</h1>

    <div class="card-panel controls">
      <div class="form-group">
        <label for="csvSelect">CSV-Datei auswählen:</label>
        <select id="csvSelect">
          <option value="">-- Bitte wählen --</option>
          <?php foreach ($csvFiles as $file): ?>
            <option value="<?= htmlspecialchars($file, ENT_QUOTES|ENT_SUBSTITUTE) ?>"><?= htmlspecialchars($file, ENT_QUOTES|ENT_SUBSTITUTE) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div class="form-group">
        <label>Abfragerichtung:</label>
        <div class="radio-group">
          <div class="radio-option">
            <input type="radio" id="dirBegriff" name="direction" value="begriff" checked>
            <label for="dirBegriff">
              <div style="font-weight: 600; margin-bottom: 0.25rem;">Begriff → Definition</div>
              <div style="font-size: 0.85rem; opacity: 0.8;">Ich sehe den Begriff und muss die Definition wissen</div>
            </label>
          </div>
          <div class="radio-option">
            <input type="radio" id="dirDefinition" name="direction" value="definition">
            <label for="dirDefinition">
              <div style="font-weight: 600; margin-bottom: 0.25rem;">Definition → Begriff</div>
              <div style="font-size: 0.85rem; opacity: 0.8;">Ich sehe die Definition und muss den Begriff wissen</div>
            </label>
          </div>
        </div>
      </div>
      
      <div class="button-group">
        <button id="loadBtn">Laden & Starten</button>
        <button id="resetBtn" class="secondary" style="display:none;">Fortschritt löschen</button>
      </div>
    </div>

    <div id="card">
      <div class="flashcard">
        <div id="term"></div>
        <div id="definition">
          <div id="definitionText"></div>
          <div class="metadata">
            <div class="metadata-item">
              <span class="metadata-label">Kategorie:</span>
              <span id="categoryText"></span>
            </div>
            <div class="metadata-item">
              <span class="metadata-label">Quelle:</span>
              <span id="sourceText"></span>
            </div>
          </div>
        </div>
      </div>

      <div class="actions">
        <div class="button-group">
          <button id="showBtn" class="action">Antwort zeigen</button>
          <button id="wrongBtn" class="action" style="display:none;">❌ Nicht gewusst</button>
          <button id="rightBtn" class="action" style="display:none;">✅ Gewusst</button>
        </div>
      </div>

      <div id="stats">
        <div>Fortschritt</div>
        <div class="stats-grid">
          <div class="stat-item">
            <span class="stat-value" id="stage1Count">0</span>
            <span class="stat-label">Stufe 1</span>
          </div>
          <div class="stat-item">
            <span class="stat-value" id="stage2Count">0</span>
            <span class="stat-label">Stufe 2</span>
          </div>
          <div class="stat-item">
            <span class="stat-value" id="stage3Count">0</span>
            <span class="stat-label">Stufe 3 ✓</span>
          </div>
        </div>
      </div>
    </div>
  </div>

<script>
/* Datenstrukturen & Persistenz */
let allCards = [];
let stage1 = [];
let stage2 = [];
let stage3 = [];
let currentCard = null;
let saveKey = "";
let csvFilename = "";
let currentStageMode = 1;
let learningDirection = "begriff";

function makeId(term, def) {
  return JSON.stringify([term || "", def || ""]);
}

function saveProgress() {
  if (!saveKey) return;
  const payload = {
    stage1, stage2, stage3, currentStageMode, learningDirection
  };
  localStorage.setItem(saveKey, JSON.stringify(payload));
}

function loadProgress() {
  if (!saveKey) return false;
  const raw = localStorage.getItem(saveKey);
  if (!raw) return false;
  try {
    const data = JSON.parse(raw);
    stage1 = data.stage1 || [];
    stage2 = data.stage2 || [];
    stage3 = data.stage3 || [];
    currentStageMode = data.currentStageMode || 1;
    learningDirection = data.learningDirection || "begriff";
    return true;
  } catch(e) {
    console.error("Fehler beim Laden des Fortschritts:", e);
    return false;
  }
}

/* CSV-Parsing */
function parseCSVLine(line) {
  const res = [];
  let cur = "";
  let inQuotes = false;
  for (let i = 0; i < line.length; i++) {
    const ch = line[i];
    if (ch === '"') {
      if (inQuotes && line[i+1] === '"') {
        cur += '"';
        i++;
      } else {
        inQuotes = !inQuotes;
      }
    } else if (ch === ',' && !inQuotes) {
      res.push(cur);
      cur = "";
    } else {
      cur += ch;
    }
  }
  res.push(cur);
  return res.map(s => s.trim().replace(/^"|"$/g, ''));
}

/* CSV laden */
function loadCSV(file) {
  fetch(file)
    .then(r => {
      if (!r.ok) throw new Error("HTTP " + r.status);
      return r.text();
    })
    .then(text => {
      const lines = text.replace(/\r\n/g,"\n").replace(/\r/g,"\n")
                        .split("\n")
                        .map(l => l.trim())
                        .filter(l => l.length > 0);

      if (lines.length === 0) {
        alert("Die CSV-Datei ist leer.");
        return;
      }

      let startIndex = 0;
      const firstFields = parseCSVLine(lines[0]).map(s => s.toLowerCase());
      if (firstFields.some(f => f.includes("kategorie") || f.includes("begriff") || f.includes("definition"))) {
        startIndex = 1;
      }

      allCards = [];
      for (let i = startIndex; i < lines.length; i++) {
        const fields = parseCSVLine(lines[i]);
        const category = fields[0] || "";
        const term = fields[1] || "";
        const def = fields[2] || "";
        const source = fields[3] || "";
        const id = makeId(term, def);
        allCards.push({ id, term, def, category, source });
      }

      stage1 = allCards.map(c => ({...c}));
      stage2 = [];
      stage3 = [];
      currentStageMode = 1;
      saveProgress();
      updateUIAfterLoad();
      pickNextCard();
    })
    .catch(err => {
      console.error("Fehler beim Laden der CSV:", err);
      alert("Fehler beim Laden der CSV-Datei. Öffne die Konsole für Details.");
    });
}

function inStageById(id, stageArray) {
  return stageArray.some(c => c.id === id);
}

function removeFromStageById(id, stageArray) {
  return stageArray.filter(c => c.id !== id);
}

function pickNextCard() {
  if (stage1.length === 0 && stage2.length === 0) {
    document.getElementById("card").style.display = "none";
    alert("🎉 Alle Vokabeln sind in Stufe 3 (gelernt)!");
    return;
  }

  if (stage1.length > 0) {
    currentStageMode = 1;
    currentCard = stage1[Math.floor(Math.random() * stage1.length)];
  } else {
    currentStageMode = 2;
    const pool = stage2.concat(stage1);
    currentCard = pool[Math.floor(Math.random() * pool.length)];
  }

  if (!currentCard) {
    console.warn("Keine Karte gefunden.");
    return;
  }

  showCardFront();
  updateStats();
}

function showCardFront() {
  if (learningDirection === "begriff") {
    document.getElementById("term").textContent = currentCard.term || "(kein Begriff)";
    document.getElementById("definitionText").textContent = currentCard.def || "(keine Definition)";
  } else {
    document.getElementById("term").textContent = currentCard.def || "(keine Definition)";
    document.getElementById("definitionText").textContent = currentCard.term || "(kein Begriff)";
  }
  
  document.getElementById("categoryText").textContent = currentCard.category || "–";
  document.getElementById("sourceText").textContent = currentCard.source || "–";
  
  document.getElementById("definition").style.display = "none";
  document.getElementById("showBtn").style.display = "inline-block";
  document.getElementById("wrongBtn").style.display = "none";
  document.getElementById("rightBtn").style.display = "none";
  document.getElementById("card").style.display = "block";
}

function showDefinition() {
  document.getElementById("definition").style.display = "block";
  document.getElementById("showBtn").style.display = "none";
  document.getElementById("wrongBtn").style.display = "inline-block";
  document.getElementById("rightBtn").style.display = "inline-block";
}

function updateStats() {
  document.getElementById("stage1Count").textContent = stage1.length;
  document.getElementById("stage2Count").textContent = stage2.length;
  document.getElementById("stage3Count").textContent = stage3.length;
}

function markCorrect() {
  if (!currentCard) return;

  const id = currentCard.id;
  const wasInStage1 = inStageById(id, stage1);
  const wasInStage2 = inStageById(id, stage2);

  if (wasInStage1 && !wasInStage2) {
    stage1 = removeFromStageById(id, stage1);
    if (!inStageById(id, stage2)) stage2.push(currentCard);
  } else if (wasInStage2) {
    stage2 = removeFromStageById(id, stage2);
    stage3.push(currentCard);
    if (inStageById(id, stage1)) stage1 = removeFromStageById(id, stage1);
  } else {
    if (!inStageById(id, stage2)) stage2.push(currentCard);
  }

  saveProgress();
  updateStats();
  pickNextCard();
}

function markWrong() {
  if (!currentCard) return;

  const id = currentCard.id;
  const wasInStage2 = inStageById(id, stage2);

  if (wasInStage2) {
    if (!inStageById(id, stage1)) {
      stage1.push(currentCard);
    }
  }

  saveProgress();
  updateStats();
  pickNextCard();
}

/* UI-Eventhandler */
document.getElementById("loadBtn").addEventListener("click", () => {
  const file = document.getElementById("csvSelect").value;
  if (!file) { alert("Bitte eine CSV-Datei auswählen."); return; }
  
  learningDirection = document.querySelector('input[name="direction"]:checked').value;
  
  csvFilename = file;
  saveKey = "kartei_progress_" + csvFilename + "_" + learningDirection;

  if (!loadProgress()) {
    loadCSV(file);
  } else {
    updateUIAfterLoad();
    pickNextCard();
  }
  document.getElementById("resetBtn").style.display = "inline-block";
});

document.getElementById("resetBtn").addEventListener("click", () => {
  if (!saveKey) return;
  if (!confirm("Fortschritt für diese Datei wirklich löschen?")) return;
  localStorage.removeItem(saveKey);
  location.reload();
});

document.getElementById("showBtn").addEventListener("click", showDefinition);
document.getElementById("rightBtn").addEventListener("click", markCorrect);
document.getElementById("wrongBtn").addEventListener("click", markWrong);

function updateUIAfterLoad() {
  document.getElementById("card").style.display = (stage1.length + stage2.length + stage3.length > 0) ? "block" : "none";
  updateStats();
}
</script>
</body>
</html>
