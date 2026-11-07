const fs = require('fs');
const path = require('path');

function scanDir(dir) {
  let files = [];
  if (!fs.existsSync(dir)) return files;
  for (const item of fs.readdirSync(dir)) {
    const full = path.join(dir, item);
    if (fs.statSync(full).isDirectory()) files = files.concat(scanDir(full));
    else if (/\.(jsx?|tsx?)$/.test(item)) files.push(full);
  }
  return files;
}

const files = [
  ...scanDir('src/pages'),
  ...scanDir('src/components/admin'),
  ...scanDir('src/services'),
  ...scanDir('src/context')
];

const endpoints = new Map();

for (const file of files) {
  const content = fs.readFileSync(file, 'utf8');
  // Match /api/... strings
  const regex = /['"`](\/api\/[a-zA-Z0-9_\-\/]+)[?'"`]/g;
  let match;
  while ((match = regex.exec(content)) !== null) {
    const ep = match[1];
    if (!endpoints.has(ep)) endpoints.set(ep, new Set());
    endpoints.get(ep).add(path.relative('.', file));
  }
}

// Read config/routes.php to find defined routes
const routesPhp = fs.readFileSync('config/routes.php', 'utf8');
const routes = [];
const routeRegex = /\$(?:router|r)->(get|post|put|patch|delete)\(['"]([^'"]+)['"]/gi;
let rMatch;
while ((rMatch = routeRegex.exec(routesPhp)) !== null) {
  routes.push({ method: rMatch[1].toUpperCase(), path: rMatch[2] });
}

// Check groups with prefix in routes.php
const groupRegex = /\$(?:router|r)->group\(\['prefix'\s*=>\s*['"]([^'"]+)['"]/gi;
let gMatch;
while ((gMatch = groupRegex.exec(routesPhp)) !== null) {
  routes.push({ method: 'GROUP', path: '/' + gMatch[1].replace(/^\//, '') });
}

console.log('=== FRONTEND ENDPOINTS AUDIT ===');
const missingInBackend = [];
const presentInBackend = [];

for (const [ep, callers] of Array.from(endpoints.entries()).sort()) {
  const normEp = ep.replace(/\/+$/, '');
  // Check exact or parameterized match
  const match = routes.find(r => {
    const rPath = r.path.startsWith('/') ? r.path : '/' + r.path;
    if (rPath === normEp) return true;
    // Parameterized match like /api/admin/members/{id} matching /api/admin/members/
    const rRegex = new RegExp('^' + rPath.replace(/\{[^}]+\}/g, '[^/]+') + '$');
    return rRegex.test(normEp);
  });

  if (match) {
    presentInBackend.push({ ep, match: match.method + ' ' + match.path, callers: Array.from(callers) });
  } else {
    // Check if prefix group matches
    const groupMatch = routes.find(r => r.method === 'GROUP' && normEp.startsWith(r.path));
    if (groupMatch) {
      presentInBackend.push({ ep, match: 'IN_GROUP ' + groupMatch.path, callers: Array.from(callers) });
    } else {
      missingInBackend.push({ ep, callers: Array.from(callers) });
    }
  }
}

console.log(`Total unique frontend /api/ endpoints called: ${endpoints.size}`);
console.log(`Matched in backend routes.php: ${presentInBackend.length}`);
console.log(`POTENTIALLY MISSING in backend routes.php: ${missingInBackend.length}`);

console.log('\n--- POTENTIALLY MISSING ENDPOINTS ---');
for (const item of missingInBackend) {
  console.log(`[MISSING?] ${item.ep}`);
  console.log(`  Called by: ${item.callers.join(', ')}`);
}
