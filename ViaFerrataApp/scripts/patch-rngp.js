#!/usr/bin/env node
// Patches @react-native/gradle-plugin/build.gradle.kts to remove the
// testRuntimeOnly block that uses serviceOf — an API removed in Gradle 8.8+.
// Handles both LF (Linux/Mac) and CRLF (Windows) line endings.
// Also handles files already partially patched (serviceOf line gone but rest remains).

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

// 1. Remove the ModuleRegistry import line (handles LF and CRLF)
content = content.replace(
  /^import org\.gradle\.api\.internal\.classpath\.ModuleRegistry\r?\n/m,
  ''
);

// 2. Remove the entire testRuntimeOnly(files(...)) block.
//    Matches from "testRuntimeOnly(" to the closing ".first()))"
//    [\s\S]*? matches any character including \r and \n (non-greedy)
//    This handles both fresh files (serviceOf present) and partially patched files.
content = content.replace(
  /\r?\n[ \t]*testRuntimeOnly\([\s\S]*?\.first\(\)\)\)/,
  ''
);

// 3. Safety net: if only the orphaned method chain remains (serviceOf already removed
//    by a previous run but .getModule/.classpath/.asFiles/.first lines still there)
content = content.replace(
  /\r?\n[ \t]*\.getModule\([^\r\n]*\)\r?\n[ \t]*\.classpath\r?\n[ \t]*\.asFiles\r?\n[ \t]*\.first\(\)\)\)/,
  ''
);

if (content !== original) {
  // Preserve original line endings
  fs.writeFileSync(target, content, 'utf8');
  console.log('[patch-rngp] Patched successfully:', target);
} else {
  console.log('[patch-rngp] Already patched (OK).');
}
