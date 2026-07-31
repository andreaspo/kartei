import csv
import random

input_file = 'input.csv'
output_file = 'output.csv'

with open(input_file, 'r', newline='', encoding='utf-8') as f:
    reader = list(csv.reader(f))

header = reader[0]
rows = reader[1:]

random.shuffle(rows)

with open(output_file, 'w', newline='', encoding='utf-8') as f:
    writer = csv.writer(f)
    writer.writerow(header)
    writer.writerows(rows)
