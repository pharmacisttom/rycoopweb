import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const app = readFileSync(new URL('../src/App.jsx', import.meta.url), 'utf8');
const main = readFileSync(new URL('../src/main.jsx', import.meta.url), 'utf8');
const calculator = readFileSync(new URL('../src/pages/CalculatorPage.jsx', import.meta.url), 'utf8');
const widget = readFileSync(new URL('../src/components/calculators/LoanCalculator.jsx', import.meta.url), 'utf8');
const home = readFileSync(new URL('../src/pages/HomePage.jsx', import.meta.url), 'utf8');
for (const route of ['/calculator','/loan-calculator','/loans/calculator']) {
  assert.ok(app.includes(`['${route}', CalculatorPage]`), 'Missing route '+route);
}
assert.match(main, /BrowserRouter basename="\/app"/);
assert.match(calculator, /<LoanCalculator\s*\/>/);
assert.match(widget, /if \(!activeProduct\)/);
assert.match(home, /to="\/calculator"/);
console.log('LOAN_CALCULATOR_ROUTING_RC05=PASS');
