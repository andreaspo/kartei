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
  body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; margin: 2rem; background:#f6f7fb; color:#111; }
  h1 { margin:0 0 0.5rem 0; font-size:1.4rem; }
  .controls { margin-bottom:1rem; }
  select, button { font-size:1rem; padding:0.4rem 0.6rem; margin-right:0.4rem; }
  #card { display:none; background:white; padding:1.25rem; border-radius:12px; max-width:720px; box-shadow:0 6px 20px rgba(15,20,30,0.06); }
  #term { font-size:1.25rem; margin:0 0 0.6rem 0; }
  #definition { display:none; margin-top:0.6rem; color:#333; font-style:normal; }
  .actions { margin-top:0.8rem; }
  .action { padding:0.5rem 0.9rem; border-radius:8px; border:0; cursor:pointer; }
  #stats { margin-top:0.8rem; color:#666; font-size:0.95rem; }
  .muted { color:#777; font-size:0.9rem; }
</style>
</head>
<body>
  <h1>Lernkartei – 3-Stufen (SRS-ähnlich)</h1>

  <div class="controls">
    <label for="csvSelect" class="muted">CSV-Datei wählen (Komma getrennt, Kopfzeile erlaubt):</label><br>
    <select id="csvSelect">
      <?php foreach ($csvFiles as $file): ?>
        <option value="<?= htmlspecialchars($file, ENT_QUOTES|ENT_SUBSTITUTE) ?>"><?= htmlspecialchars($file, ENT_QUOTES|ENT_SUBSTITUTE) ?></option>
      <?php endforeach; ?>
    </select>
    <button id="loadBtn">Laden</button>
    <button id="resetBtn" style="display:none;">Fortschritt löschen</button>
  </div>

  <div id="card">
    <div id="term"></div>
    <div id="definition"></div>

    <div class="actions">
      <button id="showBtn" class="action">Definition zeigen</button>
      <button id="wrongBtn" class="action" style="display:none;">Nicht gewusst</button>
      <button id="rightBtn" class="action" style="display:none;">Gewusst</button>
    </div>

    <div id="stats" class="muted"></div>
  </div>

<script>
/* -----------------------------
   Datenstrukturen & Persistenz
   ----------------------------- */
let allCards = [];      // alle Karten (nur initial)
let stage1 = [];        // neu / niedrigste Stufe (Array of card objects)
let stage2 = [];        // mittlere Stufe
let stage3 = [];        // gelernte Stufe (zählt als "fertig")
let currentCard = null; // aktuell gezeigte Karte
let saveKey = "";       // key in localStorage (pro CSV-Datei)
let csvFilename = "";   // aktuell geladene Datei
let currentStageMode = 1; // welche Stufe ist aktiv (1 oder 2). 3 ist Endzustand

// Jede Karte als Objekt { id, term, def }
// id ist eine deterministische Kennung: JSON.stringify([term,def])

function makeId(term, def) {
  return JSON.stringify([term || "", def || ""]);
}

function saveProgress() {
  if (!saveKey) return;
  const payload = {
    stage1, stage2, stage3, currentStageMode
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
    return true;
  } catch(e) {
    console.error("Fehler beim Laden des Fortschritts:", e);
    return false;
  }
}

/* -----------------------------
   CSV-Parsing (robust für quoted fields)
   ----------------------------- */
function parseCSVLine(line) {
  // Einfacher CSV-Parser: unterstützt "..." quotes, escaped "" und Komma-Trennung.
  const res = [];
  let cur = "";
  let inQuotes = false;
  for (let i = 0; i < line.length; i++) {
    const ch = line[i];
    if (ch === '"') {
      if (inQuotes && line[i+1] === '"') {
        // escaped quote
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
  // Trim und entferne umschließende Quotes falls vorhanden (sollte durch Parser bereits gehandhabt sein)
  return res.map(s => s.trim());
}

/* -----------------------------
   CSV laden und initialisieren
   Erwartung: Spalten:
     0: Kategorie
     1: Begriff   <-- vorne anzeigen
     2: Definition <-- beim Klick zeigen
     3: Quelle (optional)
   Falls erste Zeile Kopfzeile ist, wird diese übersprungen.
   ----------------------------- */
function loadCSV(file) {
  fetch(file)
    .then(r => {
      if (!r.ok) throw new Error("HTTP " + r.status);
      return r.text();
    })
    .then(text => {
      // Normalisiere Zeilenenden
      const lines = text.replace(/\r\n/g,"\n").replace(/\r/g,"\n")
                        .split("\n")
                        .map(l => l.trim())
                        .filter(l => l.length > 0);

      if (lines.length === 0) {
        alert("Die CSV-Datei ist leer.");
        return;
      }

      let startIndex = 0;
      // Prüfe, ob erste Zeile Kopfzeile ist (enthält typischerweise 'Kategorie' oder 'Begriff')
      const firstFields = parseCSVLine(lines[0]).map(s => s.toLowerCase());
      if (firstFields.some(f => f.includes("kategorie") || f.includes("begriff") || f.includes("definition"))) {
        startIndex = 1;
      }

      allCards = [];
      for (let i = startIndex; i < lines.length; i++) {
        const fields = parseCSVLine(lines[i]);
        // sichere Indizes: Begriff = fields[1], Definition = fields[2]
        const term = (fields[1] || "").replace(/^"|"$/g, "");
        const def = (fields[2] || "").replace(/^"|"$/g, "");
        const id = makeId(term, def);
        allCards.push({ id, term, def });
      }

      // Initiale Stufen: Wenn noch kein gespeicherter Fortschritt existiert, alles in Stufe 1
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

/* -----------------------------
   Hilfsfunktionen: Existenz prüfen, entfernen etc.
   ----------------------------- */
function inStageById(id, stageArray) {
  return stageArray.some(c => c.id === id);
}

function removeFromStageById(id, stageArray) {
  return stageArray.filter(c => c.id !== id);
}

/* -----------------------------
   Auswahl der nächsten Karte
   Regeln (wie du beschrieben hast):
   - Solange stage1 nicht leer: arbeite mit Stufe 1 (currentStageMode = 1). Erkannte 'Gewusst' => Stufe2.
   - Sobald stage1 leer und stage2 nicht leer: wechsel zu Stufe 2 (currentStageMode = 2).
   - Bei Stufe2: Pool = stage2 + stage1 (d.h. zurückgestufte Karten aus stage1 können ebenfalls auftauchen).
     Wenn Karte aus stage2 gewusst -> in stage3 (und auch aus stage1 entfernen, falls sie dort liegt).
     Wenn Karte aus stage2 falsch -> bleibt in stage2 UND wird in stage1 eingefügt (falls noch nicht vorhanden).
   - Wenn stage1 & stage2 leer -> Ende (alles in stage3).
   ----------------------------- */
function pickNextCard() {
  // Ende prüfen
  if (stage1.length === 0 && stage2.length === 0) {
    document.getElementById("card").style.display = "none";
    alert("🎉 Alle Vokabeln sind in Stufe 3 (gelernt)!");
    return;
  }

  // Entscheide Modus
  if (stage1.length > 0) {
    // solange noch cards in stage1 vorhanden sind, arbeite in Stufe 1
    currentStageMode = 1;
    // wähle zufällig aus stage1
    currentCard = stage1[Math.floor(Math.random() * stage1.length)];
  } else {
    // stage1 leer, aber stage2 hat noch items -> Modus 2
    currentStageMode = 2;
    // Pool: alle stage2 + alle stage1 (falls stage1 wieder Items hat)
    const pool = stage2.concat(stage1);
    currentCard = pool[Math.floor(Math.random() * pool.length)];
  }

  // Falls currentCard nicht gesetzt (z.B. wenn stage1 leer und stage2 leer) -> return
  if (!currentCard) {
    console.warn("Keine Karte gefunden (pickNextCard).");
    return;
  }

  showCardFront();
  updateStats();
}

/* -----------------------------
   UI Darstellung
   ----------------------------- */
function showCardFront() {
  document.getElementById("term").textContent = currentCard.term || "(kein Begriff)";
  document.getElementById("definition").textContent = currentCard.def || "(keine Definition)";
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
  document.getElementById("stats").textContent =
    `Stufe 1: ${stage1.length} | Stufe 2: ${stage2.length} | Stufe 3: ${stage3.length} — Modus: Stufe ${currentStageMode}`;
}

/* -----------------------------
   Benutzeraktionen: Gewusst / Nicht gewusst
   WICHTIG:
     - Bestimme, in welcher(n) Stufe(n) die Karte aktuell steckt (stage1/stage2).
     - Handle entsprechend (siehe Regeln oben).
   ----------------------------- */
function markCorrect() {
  if (!currentCard) return;

  const id = currentCard.id;
  const wasInStage1 = inStageById(id, stage1);
  const wasInStage2 = inStageById(id, stage2);

  // Wenn Karte aktuell in stage1 (z.B. weil wir gerade noch in Modus 1 arbeiten)
  if (wasInStage1 && !wasInStage2) {
    // Normaler Aufstieg: Stufe1 -> Stufe2
    stage1 = removeFromStageById(id, stage1);
    if (!inStageById(id, stage2)) stage2.push(currentCard);
  } else if (wasInStage2) {
    // Karte war in Stufe 2 (evtl. gleichzeitig in stage1) -> bei Erfolg Stufe2 -> Stufe3
    stage2 = removeFromStageById(id, stage2);
    stage3.push(currentCard);
    // Entferne ggf. Duplikat in stage1 (falls vorhanden), weil gelernt.
    if (inStageById(id, stage1)) stage1 = removeFromStageById(id, stage1);
  } else {
    // Fallback: falls nicht gefunden (sollte nicht vorkommen), stecke in stage2
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
    // Rückstufung: Karte bleibt in stage2 (wird weiterhin mit stage2 abgefragt) UND
    // wird zusätzlich in stage1 aufgenommen, falls nicht bereits vorhanden.
    if (!inStageById(id, stage1)) {
      stage1.push(currentCard);
    }
    // Wichtig: Karte bleibt in stage2 (nicht entfernen)
  } else {
    // Wenn Karte aus stage1 falsch -> bleibt in stage1 (keine Änderung nötig)
    // (keine explizite Aktion)
  }

  saveProgress();
  updateStats();
  pickNextCard();
}

/* -----------------------------
   UI-Eventhandler & Initialisierung
   ----------------------------- */
document.getElementById("loadBtn").addEventListener("click", () => {
  const file = document.getElementById("csvSelect").value;
  if (!file) { alert("Bitte eine CSV-Datei auswählen."); return; }
  csvFilename = file;
  saveKey = "kartei_progress_" + csvFilename;

  // Falls bereits Fortschritt im localStorage existiert, lade ihn (sonst CSV neu laden)
  if (!loadProgress()) {
    loadCSV(file);
  } else {
    // progress geladen -> zeige Karte (aber ggf. arrays leer)
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

/* -----------------------------
   Hilf-Funktion: UI nach CSV-Load aktualisieren
   ----------------------------- */
function updateUIAfterLoad() {
  document.getElementById("card").style.display = (stage1.length + stage2.length + stage3.length > 0) ? "block" : "none";
  updateStats();
}

/* -----------------------------
   Falls die Seite neu geladen wird und eine Datei schon ausgewählt war:
   (optional) - wir belassen das passive; Benutzer muss Datei wieder auswählen,
   damit der Browser die CSV lokal laden und das zugehörige saveKey gesetzt wird.
   ----------------------------- */
</script>
</body>
</html>
