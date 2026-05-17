#!/usr/bin/env node
// Patches @react-native/gradle-plugin/build.gradle.kts to remove serviceOf calls
// that were removed in Gradle 8.8+ but are still referenced in RN 0.73.x RNGP.
// This allows building with Gradle 8.13 (required by AGP 8.12 used internally by RNGP).

const fs = require('fs');
const path = require('path');

const target = path.join(
  __dirname,
  '..',
  'node_modules',
  '@react-native',
  'gradle-plugin',
  'build.gradle.kts'
);

if (!fs.existsSync(target)) {
  console.log('[patch-rngp] File not found, skipping:', target);
  process.exit(0);
}

let content = fs.readFileSync(target, 'utf8');

const originalContent = content;

// Remove the serviceOf import line
content = content.replace(
  /^import\s+org\.gradle\.configurationcache\.extensions\.serviceOf\s*\n/m,
  ''
);

// Remove any line that calls serviceOf(...)
content = content.replace(/^[^\n]*serviceOf\([^)]*\)[^\n]*\n/gm, '');

if (content !== originalContent) {
  fs.writeFileSync(target, content, 'utf8');
  console.log('[patch-rngp] Patched successfully:', target);
} else {
  console.log('[patch-rngp] No changes needed (already patched or pattern not found)');
}
