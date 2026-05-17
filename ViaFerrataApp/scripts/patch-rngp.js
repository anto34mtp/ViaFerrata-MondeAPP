#!/usr/bin/env node
// Patches @react-native/gradle-plugin/build.gradle.kts to remove the
// testRuntimeOnly block that uses serviceOf — an API removed in Gradle 8.8+.
// RN 0.73.x ships RNGP that internally needs AGP 8.12 (requires Gradle 8.13+),
// but also references serviceOf which was dropped in 8.8. This patch removes
// the offending block so the plugin compiles with Gradle 8.13.

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
const original = content;

// Remove the ModuleRegistry import line
content = content.replace(
  /^import org\.gradle\.api\.internal\.classpath\.ModuleRegistry\r?\n/m,
  ''
);

// Remove the entire testRuntimeOnly(...) block that contains serviceOf
// The block spans from "  testRuntimeOnly(" to the closing "))" line
content = content.replace(
  /\n\s*testRuntimeOnly\(\s*\n\s*files\(\s*\n\s*serviceOf<ModuleRegistry>\(\)[\s\S]*?\.first\(\)\)\)/,
  ''
);

if (content !== original) {
  fs.writeFileSync(target, content, 'utf8');
  console.log('[patch-rngp] Patched successfully:', target);
} else {
  console.log('[patch-rngp] Already patched or pattern not found (OK).');
}
