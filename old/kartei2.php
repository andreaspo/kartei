<?php
$csvFiles = glob("*.csv");
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8" />
<title>Lernkartei – 3-Stufen-System</title>
<style>
body {
  font-family: system-ui, sans-serif;
  margin: 2em;
  background: #fafafa;
}
h1 { margin-bottom: 0.5em; }
select, button {
  font-size: 1em;
  margin: 0.25em 0;
}
#card {
  margin-top: 1em;
  padding: 2em;
  border-radius: 12px;
  background: white;
  box-shadow: 0 0 10px rgba(0,0,0,0.1);
  max-width: 600px;
}
#definition {
  display: none;
  margin-top: 1em;
  font-style: italic;
  color: #333;
}
button.action {
  margin: 0.5em;
  padding: 0.5em 1em;
  font-size: 1em;
}
#stats {
  margin-top: 1em;
  color: #666;
}
</style>
</head>
<body>
<h1>Lernkartei – 3-Stufen-System</h1>

<form id="csvForm">
  <label for="csvSelect">CSV-Datei auswählen:</label><br>
  <select id="csvSelect" name="file">
    <?php foreach ($csvFiles as $file): ?>
      <option value="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars($file) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="button" id="loadBtn">Laden</button>
  <button type="button" id="resetBtn" style="display:none;">Neu starten</button>
</form>

<div id="card" style="display:none;">
  <h2 id="term"></h2>
  <div id="definition"></div>
  <div>
    <button class="action" id="showBtn">Definition zeigen</button>
    <button class="action" id="wrongBtn" style="display:none;">Nicht gewusst</button>
    <button class="action" id="rightBtn" style="display:none;">Gewusst</button>
  </div>
  <div id="stats"></div>
</div>

<script>
let allCards = [];    // Alle Vokabeln aus CSV
let stage1 = [], stage2 = [], stage3 = [];
let currentStage = 1;
let currentCard = null;
let saveKey = "";

function saveProgress() {
  const data = { stage1, stage2, stage3, currentStage };
  localStorage.setItem(saveKey, JSON.stringify(data));
}

function loadProgress() {
  const json = localStorage.getItem(saveKey);
  if (!json) return false;
  try {
    const data = JSON.parse(json);
    stage1 = data.stage1 || [];
    stage2 = data.stage2 || [];
    stage3 = data.stage3 || [];
    currentStage = data.currentStage || 1;
    return true;
  } catch(e) { return false; }
}

function updateStats() {
  document.getElementById("stats").textContent =
    `Stufe 1: ${stage1.length} | Stufe 2: ${stage2.length} | Stufe 3: ${stage3.length}`;
}

function pickNextCard() {
  if (stage1.length > 0 && currentStage === 1) {
    currentCard = stage1[Math.floor(Math.random() * stage1.length)];
  } else if (stage1.length === 0 && stage2.length > 0) {
    currentStage = 2;
    currentCard = stage2[Math.floor(Math.random() * stage2.length)];
  } else if (stage2.length === 0 && stage1.length === 0) {
    alert("🎉 Alle Vokabeln gelernt! Stufe 3 vollständig!");
    document.getElementById("card").style.display = "none";
    return;
  } else {
    // Falls in Stufe 2, aber Rückfaller vorhanden
    let pool = stage2.concat(stage1);
    currentCard = pool[Math.floor(Math.random() * pool.length)];
  }
  showCardFront();
}

function showCardFront() {
  const term = document.getElementById("term");
  const def = document.getElementById("definition");
  term.textContent = currentCard.term;
  def.textContent = currentCard.def;
  def.style.display = "none";
  document.getElementById("showBtn").style.display = "inline-block";
  document.getElementById("wrongBtn").style.display = "none";
  document.getElementById("rightBtn").style.display = "none";
}

function showDefinition() {
  document.getElementById("definition").style.display = "block";
  document.getElementById("showBtn").style.display = "none";
  document.getElementById("wrongBtn").style.display = "inline-block";
  document.getElementById("rightBtn").style.display = "inline-block";
}

function markCorrect() {
  if (currentStage === 1) {
    stage1 = stage1.filter(c => c !== currentCard);
    stage2.push(currentCard);
  } else if (currentStage === 2) {
    stage2 = stage2.filter(c => c !== currentCard);
    stage3.push(currentCard);
  }
  saveProgress();
  updateStats();
  pickNextCard();
}

function markWrong() {
  if (currentStage === 2) {
    // Rückfall in Stufe 1
    if (!stage1.includes(currentCard)) stage1.push(currentCard);
  }
  saveProgress();
  updateStats();
  pickNextCard();
}

function parseCSVLine(line) {
  // Handhabt Felder mit Anführungszeichen und eingebetteten Kommas
  const result = [];
  let cur = "";
  let inQuotes = false;
  for (let i = 0; i < line.length; i++) {
    const ch = line[i];
    if (ch === '"' ) {
      if (inQuotes && line[i+1] === '"') { // escaped quote ""
        cur += '"';
        i++; // skip next quote
      } else {
        inQuotes = !inQuotes;
      }
    } else if (ch === ',' && !inQuotes) {
      result.push(cur);
      cur = "";
    } else {
      cur += ch;
    }
  }
  result.push(cur);
  // trim whitespace and optional surrounding quotes
  return result.map(s => {
    s = s.trim();
    if (s.startsWith('"') && s.endsWith('"')) s = s.slice(1, -1).replace(/""/g, '"');
    return s;
  });
}

function loadCSV(file) {
  fetch(file)
    .then(r => r.text())
    .then(text => {
      const lines = text.replace(/\r\n/g, "\n").replace(/\r/g, "\n")
                        .split("\n")
                        .filter(l => l.trim().length > 0);
      allCards = lines.map(line => {
        const fields = parseCSVLine(line);
        // Erwarte: erstes Feld = term, zweites Feld = definition
        const term = fields[0] || "";
        const def  = fields[1] || "";
        return { term, def };
      });
      // initialisiere die Stufen (neu starten)
      stage1 = [...allCards];
      stage2 = [];
      stage3 = [];
      currentStage = 1;
      saveProgress();
      document.getElementById("card").style.display = "block";
      updateStats();
      pickNextCard();
    })
    .catch(err => {
      console.error("Fehler beim Laden der CSV:", err);
      alert("Fehler beim Laden der CSV-Datei. Schau in die Konsole.");
    });
}


document.getElementById("loadBtn").addEventListener("click", () => {
  const file = document.getElementById("csvSelect").value;
  saveKey = "kartei_" + file;
  if (!loadProgress()) {
    loadCSV(file);
  } else {
    document.getElementById("card").style.display = "block";
    updateStats();
    pickNextCard();
  }
  document.getElementById("resetBtn").style.display = "inline-block";
});

document.getElementById("resetBtn").addEventListener("click", () => {
  if (confirm("Fortschritt wirklich löschen?")) {
    localStorage.removeItem(saveKey);
    location.reload();
  }
});

document.getElementById("showBtn").addEventListener("click", showDefinition);
document.getElementById("rightBtn").addEventListener("click", markCorrect);
document.getElementById("wrongBtn").addEventListener("click", markWrong);
</script>
</body>
</html>
