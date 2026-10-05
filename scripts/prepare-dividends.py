"""Validate source workbooks and prepare private import data; never publish this JSON."""
import argparse
import json
import re
from decimal import Decimal, ROUND_HALF_UP
from pathlib import Path
import openpyxl

parser = argparse.ArgumentParser()
parser.add_argument('files', nargs='+')
parser.add_argument('--output', default='storage/private/dividends.json')
parser.add_argument('--year', type=int, help='Buddhist year for legacy files without a year column')
args = parser.parse_args()
records = []
seen = set()
conflicts = set()
for filename in args.files:
    workbook = openpyxl.load_workbook(filename, data_only=True, read_only=True)
    match = re.search(r'(?<!\d)(2[4-7]\d{2})(?!\d)', Path(filename).name)
    default_year = args.year or (int(match.group()) if match else None)
    sheet = workbook.worksheets[0]
    rows = iter(sheet.values)
    headers = None
    for header_number, values in enumerate(rows, 1):
        candidate = [str(v).strip() if v is not None else '' for v in values]
        if 'เลขบัตรประชาชน' in candidate:
            headers = candidate
            break
    if headers is None:
        raise ValueError('No header row found')
    required = ['เลขบัตรประชาชน', 'เลขทะเบียนสมาชิก', 'ชื่อนามสกุล', 'เงินปันผล', 'เงินเฉลี่ยคืน', 'เงินของชำร่วย', 'เงินรางวัลสมาชิก', 'รวมรายการรับทั้งหมด', 'สสธท.', 'กสธท.2', 'กสธท.3', 'กสธท.4', 'สส.ชสอ', 'กรมบังคับคดี', 'รวมรายจ่าย', 'ยอดเงินคงเหลือ', 'เงินที่ได้รับ', 'อัตราเงินปันผล', 'อัตราเงินเฉลี่ยคืน']
    if set(required) - set(headers):
        raise ValueError('Missing columns: ' + ', '.join(set(required) - set(headers)))
    for number, values in enumerate(rows, header_number + 1):
        row = dict(zip(headers, values))
        year = int(row.get('ปีบัญชี') or default_year or 0)
        if not 2400 <= year <= 2800 or (args.year and year != args.year):
            raise ValueError(f'Invalid or conflicting year at row {number}; specify --year')
        identity = row.get('เลขบัตรประชาชน')
        if identity is None:
            continue
        identity = str(identity).strip()
        if not re.fullmatch(r'\d{13}', identity) or identity == '0000000000000':
            raise ValueError(f'{year}: invalid identity at row {number}')
        key = (year, identity)
        if key in seen:
            conflicts.add(identity)
        seen.add(key)
        def money(label):
            return str(Decimal(str(row.get(label) or 0)).quantize(Decimal('.01'), rounding=ROUND_HALF_UP))
        income = {label: money(label) for label in ['เงินปันผล', 'เงินเฉลี่ยคืน', 'เงินของชำร่วย', 'เงินรางวัลสมาชิก']}
        deductions = {label: money(label) for label in ['สสธท.', 'กสธท.2', 'กสธท.3', 'กสธท.4', 'สส.ชสอ', 'กรมบังคับคดี']}
        total = money('รวมรายการรับทั้งหมด')
        expense = money('รวมรายจ่าย')
        net = money('ยอดเงินคงเหลือ')
        if abs(sum(map(Decimal, income.values())) - Decimal(total)) > Decimal('.02') or abs(sum(map(Decimal, deductions.values())) - Decimal(expense)) > Decimal('.02') or abs(Decimal(total)-Decimal(expense)-Decimal(net)) > Decimal('.02'):
            raise ValueError(f'{year}: totals do not reconcile at row {number}')
        records.append(dict(year=year, id_card=identity, member_no=str(row['เลขทะเบียนสมาชิก']).strip().zfill(5), name=str(row['ชื่อนามสกุล']).strip(), department=str(row.get('สังกัด') or ''), income=income, deductions=deductions, total_income=total, total_deductions=expense, net=net, received=money('เงินที่ได้รับ'), dividend_rate=money('อัตราเงินปันผล'), refund_rate=money('อัตราเงินเฉลี่ยคืน'), source=Path(filename).name))
    workbook.close()
output = Path(args.output)
output.parent.mkdir(parents=True, exist_ok=True)
quarantine = [r for r in records if r['id_card'] in conflicts]
records = [r for r in records if r['id_card'] not in conflicts]
output.with_name('dividends-review.json').write_text(json.dumps(quarantine, ensure_ascii=False), encoding='utf-8')
output.write_text(json.dumps(records, ensure_ascii=False), encoding='utf-8')
print(json.dumps({'records': len(records), 'quarantined': len(quarantine), 'members': len({r['id_card'] for r in records}), 'years': {y: sum(r['year']==y for r in records) for y in sorted({r['year'] for r in records})}}))
