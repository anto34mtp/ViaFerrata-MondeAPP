#!/usr/bin/env node
// Replaces @react-native/gradle-plugin/build.gradle.kts with a fixed version.
// Removes:
//   1. serviceOf<ModuleRegistry>() block (removed in Gradle 8.8+)
//   2. allWarningsAsErrors = true (causes build failures with newer Kotlin/Gradle)

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

const fixedContent = `/*
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

import org.gradle.api.tasks.testing.logging.TestExceptionFormat
import org.jetbrains.kotlin.gradle.tasks.KotlinCompile

plugins {
  alias(libs.plugins.kotlin.jvm)
  id("java-gradle-plugin")
}

repositories {
  google()
  mavenCentral()
}

gradlePlugin {
  plugins {
    create("react") {
      id = "com.facebook.react"
      implementationClass = "com.facebook.react.ReactPlugin"
    }
    create("reactrootproject") {
      id = "com.facebook.react.rootproject"
      implementationClass = "com.facebook.react.ReactRootProjectPlugin"
    }
  }
}

group = "com.facebook.react"

dependencies {
  implementation(gradleApi())

  // The KGP/AGP version is defined by React Native Gradle plugin.
  // Therefore we specify an implementation dep rather than a compileOnly.
  implementation(libs.kotlin.gradle.plugin)
  implementation(libs.android.gradle.plugin)

  implementation(libs.gson)
  implementation(libs.guava)
  implementation(libs.javapoet)

  testImplementation(libs.junit)
}

// We intentionally don't build for Java 17 as users will see a cryptic bytecode version
// error first. Instead we produce a Java 11-compatible Gradle Plugin, so that AGP can print their
// nice message showing that JDK 11 (or 17) is required first
java { targetCompatibility = JavaVersion.VERSION_11 }

kotlin { jvmToolchain(17) }

tasks.withType<KotlinCompile>().configureEach {
  kotlinOptions {
    apiVersion = "1.5"
    // See comment above on JDK 11 support
    jvmTarget = "11"
  }
}

tasks.withType<Test>().configureEach {
  testLogging {
    exceptionFormat = TestExceptionFormat.FULL
    showExceptions = true
    showCauses = true
    showStackTraces = true
  }
}
`;

fs.writeFileSync(target, fixedContent, 'utf8');
console.log('[patch-rngp] Patched successfully:', target);
