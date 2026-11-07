const fs = require('fs');
const path = require('path');
const lucide = require('lucide-react');

const allLucideIcons = new Set(Object.keys(lucide));
// Remove generic word 'Icon' which is commonly used as a component prop (e.g. icon: Icon)
allLucideIcons.delete('Icon');

function getFiles(dir) {
  let results = [];
  const list = fs.readdirSync(dir);
  for (const file of list) {
    const fullPath = path.join(dir, file);
    const stat = fs.statSync(fullPath);
    if (stat && stat.isDirectory()) {
      results = results.concat(getFiles(fullPath));
    } else if (file.endsWith('.jsx') || file.endsWith('.js')) {
      results.push(fullPath);
    }
  }
  return results;
}

const files = getFiles('src');
const missingIcons = [];

for (const file of files) {
  const content = fs.readFileSync(file, 'utf8');

  // Find all JSX tags <TagName
  const matches = [...content.matchAll(/<([A-Z][a-zA-Z0-9]+)/g)];
  for (const m of matches) {
    const tag = m[1];
    if (allLucideIcons.has(tag)) {
      // Check if tag is imported from lucide-react in this file
      const lucideImportMatches = [...content.matchAll(/import\s*\{([\s\S]*?)\}\s*from\s*['"]lucide-react['"]/g)];
      let importedFromLucide = false;
      for (const lim of lucideImportMatches) {
        const importedList = lim[1].split(',').map(s => s.trim().split(/\s+as\s+/)[0].trim());
        if (importedList.includes(tag)) {
          importedFromLucide = true;
          break;
        }
      }

      // Check if tag is locally declared
      const isLocallyDeclared = new RegExp(`\\b(function|const|let|var|class)\\s+${tag}\\b`).test(content);

      // Check if imported from another module
      const isImportedElsewhere = new RegExp(`\\bimport\\s+(?:\\{?[^}]*\\b${tag}\\b[^}]*\\}?|${tag})\\s+from`).test(content);

      if (!importedFromLucide && !isLocallyDeclared && !isImportedElsewhere) {
        missingIcons.push({ file, tag });
      }
    }
  }
}

if (missingIcons.length > 0) {
  console.log('FOUND MISSING ICONS:');
  console.log(JSON.stringify(missingIcons, null, 2));
  process.exit(1);
} else {
  console.log('PASS: All JSX Lucide icons across all 112 src/ files are properly imported and defined!');
  process.exit(0);
}
