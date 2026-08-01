"""Konvertiert ein Vocabularium-Excel in Lernkartei-CSVs, aufgeteilt nach Schuljahr.

Bei einem neuen Buch oder Import-Fehler: nur den KONFIGURATION-Block unten anpassen.
Ändert sich der Spaltenaufbau des Excels grundlegend, ggf. auch build_begriff()/
kapitel_aus_lektion() anpassen.
"""

import csv
import re
import openpyxl

# ============================= KONFIGURATION =============================

INPUT_DATEI = '0514180101_0514190101_WM_199_Vocabularium_filterbar.xlsx'
SHEET_NAME = 'Vocabularium'

# Wird für ALLE Einträge unverändert ins Quelle-Feld geschrieben.
BUCH_QUELLE = 'Medias In Res! L6. 3-4'

# Kapitelnummer <= SPLIT -> erste Ausgabedatei, > SPLIT -> zweite Ausgabedatei.
KAPITEL_SPLIT = 14
AUSGABE_1 = 'latein_Gym3.csv'       # Kapitel 0-14, 3. Schuljahr
AUSGABE_2 = 'latein_Gym4.csv'       # Kapitel >14, 4. Schuljahr

# Reihenfolge der Excel-Spalten (0-indiziert), Kopfzeile in Zeile 1.
COL_LEKTION, COL_WORTART, COL_DEKLKONJ, COL_THEMA, COL_LATEINISCH, COL_DEUTSCH = range(6)

# ============================================================================


def clean_text(text):
    if text is None:
        return ''
    text = re.sub(r'\s+', ' ', str(text))
    return text.strip()


def kapitel_aus_lektion(wert):
    # Mehrfach-Lektionen wie "4;8" -> erste genannte Lektion verwenden
    text = str(wert).split(';')[0].strip()
    return int(text)


def build_begriff(lateinisch, wortart, deklkonj):
    lateinisch = clean_text(lateinisch)
    wortart = clean_text(wortart)
    deklkonj = clean_text(deklkonj)

    if not wortart:
        return lateinisch
    info = f"{wortart} ({deklkonj})" if deklkonj else wortart
    return f"{lateinisch} {{{info}}}"


def konvertiere():
    wb = openpyxl.load_workbook(INPUT_DATEI, data_only=True)
    ws = wb[SHEET_NAME]

    rows_1, rows_2 = [], []

    for row in ws.iter_rows(min_row=2, values_only=True):
        lateinisch = clean_text(row[COL_LATEINISCH])
        deutsch = clean_text(row[COL_DEUTSCH])
        if not lateinisch or not deutsch:
            continue

        kapitel_nr = kapitel_aus_lektion(row[COL_LEKTION])
        kategorie = f"Kapitel {kapitel_nr}"
        begriff = build_begriff(lateinisch, row[COL_WORTART], row[COL_DEKLKONJ])

        zeile = [kategorie, begriff, deutsch, BUCH_QUELLE]
        (rows_1 if kapitel_nr <= KAPITEL_SPLIT else rows_2).append(zeile)

    header = ["Kategorie", "Begriff", "Definition", "Quelle"]
    for pfad, rows in ((AUSGABE_1, rows_1), (AUSGABE_2, rows_2)):
        with open(pfad, 'w', newline='', encoding='utf-8') as f_out:
            writer = csv.writer(f_out, delimiter=',', quotechar='"', quoting=csv.QUOTE_ALL)
            writer.writerow(header)
            writer.writerows(rows)
        print(f"Fertig! {len(rows)} Vokabeln -> {pfad}")


if __name__ == '__main__':
    konvertiere()
