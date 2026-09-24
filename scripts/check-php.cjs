const fs = require('node:fs');
const path = require('node:path');
const Engine = require('php-parser');
const parser = new Engine({
  parser: { extractDoc: true, phpVersion: '8.3' },
  ast: { withPositions: true },
});
const files = [];
for (const directory of ['app', 'bootstrap', 'config', 'routes', 'database', 'public', 'tests']) {
  const walk = (dir) => {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
      const p = path.join(dir, entry.name);
      if (entry.isDirectory()) walk(p);
      else if (p.endsWith('.php')) files.push(p);
    }
  };
  walk(directory);
}
let failed = 0;
for (const file of files) {
  try {
    parser.parseCode(fs.readFileSync(file, 'utf8'), file);
  } catch (error) {
    failed++;
    console.error(file + ': ' + error.message);
  }
}
console.log(
  `${files.length} PHP files parsed; ${failed} syntax errors. This is syntax validation, not PHP/Laravel execution.`,
);
process.exitCode = failed ? 1 : 0;
