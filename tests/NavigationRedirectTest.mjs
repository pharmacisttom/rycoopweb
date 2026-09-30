globalThis.window = { location: { origin: 'https://rayongcoop.tomvisolution.tech' } };

const { normalizeSameOriginRedirect } = await import('../src/utils/navigation.js');
const fallback = '/admin/dashboard';
const cases = [
  ['/admin/dashboard', '/admin/dashboard'],
  ['https://rayongcoop.tomvisolution.tech/admin/dashboard', '/admin/dashboard'],
  ['https://evil.example.com/foo', fallback],
  ['//evil.example.com/foo', fallback],
  ['javascript:alert(1)', fallback],
  ['admin/dashboard', fallback],
  [null, fallback],
  ['', fallback],
];

let failed = 0;
for (const [input, expected] of cases) {
  const actual = normalizeSameOriginRedirect(input, fallback);
  if (actual !== expected) {
    failed += 1;
    console.error(`FAIL: ${String(input)} => ${actual}; expected ${expected}`);
  }
}

if (failed) process.exit(1);
console.log(`Navigation redirect tests passed (${cases.length} cases).`);
