plugins {
    `kotlin-dsl`
}
repositories {
    google()
    mavenCentral()
}
dependencies {
    // implementation (not compileOnly) so AGP classes are available at runtime
    // in buildSrc's isolated classloader (Gradle 8.x strict isolation)
    implementation("com.android.tools.build:gradle:8.3.0")
}
