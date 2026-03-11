@echo off
set "JAVA_HOME=C:\Program Files\Android\Android Studio\jbr"
set "ANDROID_HOME=C:\Users\1\AppData\Local\Android\Sdk"
set "PATH=%JAVA_HOME%\bin;%PATH%"
set "GRADLE_HOME=C:\Users\1\.gradle\wrapper\dists\gradle-8.9-bin\90cnw93cvbtalezasaz0blq0a\gradle-8.9"
set "PATH=%GRADLE_HOME%\bin;%PATH%"

echo Environment:
echo JAVA_HOME=%JAVA_HOME%
echo ANDROID_HOME=%ANDROID_HOME%
echo GRADLE_HOME=%GRADLE_HOME%

echo.
echo Starting Build...
call gradle assembleDebug --stacktrace --info > build.log 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo Build Failed!
    exit /b %ERRORLEVEL%
)

echo Build Success!
echo APK location: app\build\outputs\apk\debug\app-debug.apk
