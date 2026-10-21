# Runs the Android Gradle build directly and saves the full output to gradle-build.log
# (NativePHP's own log only says "Build failed"). Run from the project root after
# `php artisan native:run android` has prepared the nativephp\android folder.
Push-Location "$PSScriptRoot\..\nativephp\android"
try {
    .\gradlew.bat assembleDebug --console=plain --stacktrace *> "$PSScriptRoot\..\gradle-build.log"
    Write-Host "Exit code: $LASTEXITCODE  ->  gradle-build.log"
} finally {
    Pop-Location
}
