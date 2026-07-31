import csv
import re

input_datei = 'Vok8.txt'
output_datei = 'Latein8.csv'

prefix = "Kapitel 8"
suffix = "Lateinvokabel"

def clean_text(text):
    # Entfernt doppelte Leerzeichen und bereinigt Start/Ende
    text = re.sub(r'\s+', ' ', text)
    return text.strip()

vokabel_liste = []

# 1. Datei einlesen mit automatischer Kodierungserkennung
try:
    with open(input_datei, 'r', encoding='utf-8') as f_in:
        lines = f_in.readlines()
except UnicodeDecodeError:
    with open(input_datei, 'r', encoding='latin-1') as f_in:
        lines = f_in.readlines()

# 2. Daten verarbeiten
for line in lines:
    line = line.strip()
    if not line or '/' not in line:
        continue
    
    # Trennung am Schrägstrich
    parts = line.split('/')
    # Wir nehmen nur die ersten zwei Teile, falls jemand extra Schrägstriche hat
    lat = clean_text(parts[0])
    deu = clean_text(parts[1])
    
    # Struktur für Lernsoftware: [Kategorie/Tag], [Latein], [Deutsch], [Zusatz-Tag]
    vokabel_liste.append([prefix, lat, deu, suffix])

# 3. Speichern als saubere CSV
with open(output_datei, 'w', newline='', encoding='utf-8') as f_out:
    writer = csv.writer(f_out, delimiter=',', quotechar='"', quoting=csv.QUOTE_ALL)
    writer.writerows(vokabel_liste)

print(f"Fertig! {len(vokabel_liste)} Vokabeln wurden für Lernsoftware optimiert.")
