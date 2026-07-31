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
#controls {
  margin-top: 1em;
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
let cards = [];
let currentIndex = 0;
let stage = 1;
let saveKey = ""; // eindeutiger Speichername pro Datei

// ---- Hilfsfunktionen ----
function saveProgress() {
  const data = {
    cards,
    currentIndex,
    stage
  };
  localStorage.setItem(saveKey, JSON.stringify(data));
}

function loadProgress() {
  const json = localStorage.getItem(saveKey);
  if (!json) return false;
  const data = JSON.parse(json);
  cards = data.cards || [];
  currentIndex = data.currentIndex || 0;
  stage = data.stage || 1;
  return true;
}

function resetProgress() {
  localStorage.removeItem(saveKey);
  window.location.reload();
}

// ---- Lernlogik ----
async function loadCSV() {
  const file = document.getElementById('csvSelect').value;
  saveKey = "lernkartei_" + file;
  const res = await fetch('readcsv.php?file=' + encodeURIComponent(file));
  const text = await res.text();

  cards = text.trim().split(/\r?\n/).slice(1).map(line => {
    const parts = line.split(',');
    return {
      category: parts[0]?.trim() || "",
      term: parts[1]?.trim() || "",
      definition: parts[2]?.trim() || "",
      source: parts[3]?.trim() || "",
      level: 1
    };
  });

  // Prüfen, ob gespeicherter Fortschritt existiert
  if (loadProgress()) {
    if (confirm("Gespeicherten Lernstand fortsetzen?")) {
      document.getElementById('card').style.display = 'block';
      document.getElementById('resetBtn').style.display = 'inline';
      showCard();
      return;
    } else {
      resetProgress();
    }
  }

  stage = 1;
  currentIndex = 0;
  document.getElementById('card').style.display = 'block';
  document.getElementById('resetBtn').style.display = 'inline';
  showCard();
}

function showCard() {
  const card = cards[currentIndex];
  document.getElementById('term').textContent = card.term || "—";
  document.getElementById('definition').style.display = 'none';
  document.getElementById('definition').textContent = card.definition;
  document.getElementById('showBtn').style.display = 'inline';
  document.getElementById('wrongBtn').style.display = 'none';
  document.getElementById('rightBtn').style.display = 'none';
  updateStats();
  saveProgress();
}

function showDefinition() {
  document.getElementById('definition').style.display = 'block';
  document.getElementById('showBtn').style.display = 'none';
  document.getElementById('wrongBtn').style.display = 'inline';
  document.getElementById('rightBtn').style.display = 'inline';
}

function nextCard(correct) {
  if (correct) {
    cards[currentIndex].level = Math.min(3, cards[currentIndex].level + 1);
  } else {
    cards[currentIndex].level = 1;
  }

  saveProgress();

  let remaining = cards.filter(c => c.level === stage);
  if (remaining.length === 0) {
    if (stage < 3) {
      stage++;
      alert("Super! Weiter mit Stufe " + stage);
      currentIndex = 0;
    } else {
      alert("Alle Stufen geschafft 🎉");
      localStorage.removeItem(saveKey);
      document.getElementById('card').style.display = 'none';
      return;
    }
  } else {
    currentIndex = (currentIndex + 1) % remaining.length;
  }
  showCard();
}

function updateStats() {
  const levels = [1,2,3].map(l => cards.filter(c => c.level === l).length);
  document.getElementById('stats').textContent =
    `Stufe 1: ${levels[0]} | Stufe 2: ${levels[1]} | Stufe 3: ${levels[2]} (Aktuell: ${stage})`;
}

// ---- Event-Listener ----
document.getElementById('loadBtn').addEventListener('click', loadCSV);
document.getElementById('resetBtn').addEventListener('click', resetProgress);
document.getElementById('showBtn').addEventListener('click', showDefinition);
document.getElementById('wrongBtn').addEventListener('click', () => nextCard(false));
document.getElementById('rightBtn').addEventListener('click', () => nextCard(true));
</script>
</body>
</html>
