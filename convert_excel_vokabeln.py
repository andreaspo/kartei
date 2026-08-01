import csv
import re
import openpyxl

input_datei = '0514180101_0514190101_WM_199_Vocabularium_filterbar.xlsx'
output_gym3 = 'latein_Gym3_neu.csv'
output_gym4 = 'latein_Gym4.csv'
quelle_buch = 'Medias In Res! L6'

def clean_text(text):
    if text is None:
        return ''
    text = re.sub(r'\s+', ' ', str(text))
    return text.strip()

def erste_lektion(wert):
    # Mehrfach-Lektionen wie "4;8" -> erste genannte Lektion verwenden
    text = str(wert).split(';')[0].strip()
    return int(text)

def quelle_aus_wortart(wortart, deklkonj):
    wortart = clean_text(wortart)
    deklkonj = clean_text(deklkonj)
    if deklkonj:
        return f"{wortart} ({deklkonj})"
    return wortart

wb = openpyxl.load_workbook(input_datei, data_only=True)
ws = wb['Vocabularium']

rows_gym3 = []
rows_gym4 = []

for row in ws.iter_rows(min_row=2, values_only=True):
    lektion, wortart, deklkonj, _kategorie_thema, lateinisch, deutsch = row
    if lateinisch is None or deutsch is None:
        continue

    lat = clean_text(lateinisch)
    deu = clean_text(deutsch)
    if not lat or not deu:
        continue

    kapitel_nr = erste_lektion(lektion)
    kategorie = f"Kapitel {kapitel_nr}"
    quelle = quelle_aus_wortart(wortart, deklkonj)

    zeile = [kategorie, lat, deu, quelle]
    if kapitel_nr <= 14:
        rows_gym3.append(zeile)
    else:
        rows_gym4.append(zeile)

header = ["Kategorie", "Begriff", "Definition", "Quelle"]

with open(output_gym3, 'w', newline='', encoding='utf-8') as f_out:
    writer = csv.writer(f_out, delimiter=',', quotechar='"', quoting=csv.QUOTE_ALL)
    writer.writerow(header)
    writer.writerows(rows_gym3)

with open(output_gym4, 'w', newline='', encoding='utf-8') as f_out:
    writer = csv.writer(f_out, delimiter=',', quotechar='"', quoting=csv.QUOTE_ALL)
    writer.writerow(header)
    writer.writerows(rows_gym4)

print(f"Fertig! {len(rows_gym3)} Vokabeln -> {output_gym3} (Kapitel 0-14, 3. Schuljahr)")
print(f"Fertig! {len(rows_gym4)} Vokabeln -> {output_gym4} (Kapitel >14, 4. Schuljahr)")
