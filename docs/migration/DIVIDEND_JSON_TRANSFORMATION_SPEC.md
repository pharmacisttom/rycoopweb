# RYCOOPWEB 3.0 — Dividend JSON Transformation Specification & Reconciliation

**Target Table:** `member_dividends.details_json`
**Covered Years:** 2567 & 2568

---

## 1. JSON Schema Specification

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "MemberDividendDetails",
  "type": "object",
  "required": [
    "year",
    "id_card",
    "member_no",
    "name",
    "department",
    "income",
    "deductions",
    "total_income",
    "total_deductions",
    "net",
    "received",
    "dividend_rate",
    "refund_rate"
  ],
  "properties": {
    "year": { "type": "integer", "minimum": 2500, "maximum": 2600 },
    "id_card": { "type": "string", "pattern": "^[0-9]{13}$" },
    "member_no": { "type": "string", "pattern": "^[0-9]{5,20}$" },
    "name": { "type": "string", "maxLength": 100 },
    "department": { "type": "string", "maxLength": 150 },
    "income": {
      "type": "object",
      "properties": {
        "เงินปันผล": { "type": "string", "pattern": "^-?[0-9]+\\.[0-9]{2}$" },
        "เงินเฉลี่ยคืน": { "type": "string", "pattern": "^-?[0-9]+\\.[0-9]{2}$" },
        "เงินของชำร่วย": { "type": "string", "pattern": "^-?[0-9]+\\.[0-9]{2}$" },
        "เงินรางวัลสมาชิก": { "type": "string", "pattern": "^-?[0-9]+\\.[0-9]{2}$" }
      },
      "required": ["เงินปันผล", "เงินเฉลี่ยคืน", "เงินของชำร่วย", "เงินรางวัลสมาชิก"]
    },
    "deductions": {
      "type": "object",
      "properties": {
        "สสธท.": { "type": "string", "pattern": "^-?[0-9]+\\.[0-9]{2}$" },
        "กสธท.2": { "type": "string", "pattern": "^-?[0-9]+\\.[0-9]{2}$" },
        "กสธท.3": { "type": "string", "pattern": "^-?[0-9]+\\.[0-9]{2}$" },
        "กสธท.4": { "type": "string", "pattern": "^-?[0-9]+\\.[0-9]{2}$" },
        "สส.ชสอ": { "type": "string", "pattern": "^-?[0-9]+\\.[0-9]{2}$" },
        "กรมบังคับคดี": { "type": "string", "pattern": "^-?[0-9]+\\.[0-9]{2}$" }
      },
      "required": ["สสธท.", "กสธท.2", "กสธท.3", "กสธท.4", "สส.ชสอ", "กรมบังคับคดี"]
    },
    "total_income": { "type": "string", "pattern": "^-?[0-9]+\\.[0-9]{2}$" },
    "total_deductions": { "type": "string", "pattern": "^-?[0-9]+\\.[0-9]{2}$" },
    "net": { "type": "string", "pattern": "^-?[0-9]+\\.[0-9]{2}$" },
    "received": { "type": "string", "pattern": "^-?[0-9]+\\.[0-9]{2}$" },
    "dividend_rate": { "type": "string", "pattern": "^[0-9]+\\.[0-9]{2}$" },
    "refund_rate": { "type": "string", "pattern": "^[0-9]+\\.[0-9]{2}$" },
    "source": { "type": "string" }
  }
}
```

---

## 2. Reconciled Financial Figures (Exact Satang Precision)

### Year 2567 (จำนวน 2,429 ราย)
- **เงินปันผลรวม:** 78,356,989.62 บาท
- **เงินเฉลี่ยคืนรวม:** 7,017,501.18 บาท
- **รวมรายการรับทั้งหมด (`total_income`):** 86,785,490.80 บาท
- **รวมรายจ่ายทั้งหมด (`total_deductions`):** 15,766,193.35 บาท
- **ยอดเงินคงเหลือสุทธิ (`net`):** 71,019,297.45 บาท
- **เงินที่ได้รับจริง (`received`):** 72,093,125.97 บาท
- **อัตราเงินปันผล:** 5.30 %
- **อัตราเงินเฉลี่ยคืน:** 8.00 %

### Year 2568 (จำนวน 2,426 ราย)
- **เงินปันผลรวม:** 82,204,037.08 บาท
- **เงินเฉลี่ยคืนรวม:** 6,883,432.49 บาท
- **รวมรายการรับทั้งหมด (`total_income`):** 90,495,969.57 บาท
- **รวมรายจ่ายทั้งหมด (`total_deductions`):** 17,860,236.17 บาท
- **ยอดเงินคงเหลือสุทธิ (`net`):** 72,635,733.40 บาท
- **เงินที่ได้รับจริง (`received`):** 74,083,019.77 บาท
- **อัตราเงินปันผล:** 5.25 %
- **อัตราเงินเฉลี่ยคืน:** 8.00 %

### Combined 2-Year Totals (4,855 records)
- **เงินปันผลสะสม 2 ปี:** **160,561,026.70 บาท**
- **เงินเฉลี่ยคืนสะสม 2 ปี:** **13,900,933.67 บาท**
- **รายรับรวมสะสม 2 ปี:** **177,281,460.37 บาท**
- **รายหักรวมสะสม 2 ปี:** **33,626,429.52 บาท**
- **ยอดเงินคงเหลือสุทธิสะสม 2 ปี:** **143,655,030.85 บาท**
- **เงินที่ได้รับจริงสะสม 2 ปี:** **146,176,145.74 บาท**
