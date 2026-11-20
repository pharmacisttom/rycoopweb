import { readFile } from 'node:fs/promises';
import { transform } from 'esbuild';

const jsx = [
  'src/pages/PublicContentAdminPage.jsx',
  'src/pages/BoardPage.jsx',
  'src/pages/WelfarePage.jsx',
  'src/pages/AdminWelfarePage.jsx'
];
for (const path of jsx) {
  const source = await readFile(path, 'utf8');
  await transform(source, { loader: 'jsx', sourcefile: path, jsx: 'automatic', format: 'esm' });
  console.log('JSX_PARSE_PASS ' + path);
}
console.log('RC06_JSX_SYNTAX=PASS');
