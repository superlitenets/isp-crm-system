# SuperLite CRM - Android APK Build Instructions

## Quick Build (Android Studio - Recommended)

1. **Download Android Studio** from https://developer.android.com/studio
2. **Open the project**: File > Open > select the `android-app` folder
3. **Wait for sync**: Android Studio will download Gradle, SDK, and dependencies automatically
4. **Build APK**: Build > Build Bundle(s) / APK(s) > Build APK(s)
5. **Find APK**: The APK will be in `app/build/outputs/apk/debug/app-debug.apk`

## Command Line Build (Linux/Mac)

```bash
# Prerequisites: JDK 17+ and Android SDK installed
# Set ANDROID_HOME environment variable
export ANDROID_HOME=~/Android/Sdk   # adjust to your SDK location

# Navigate to the android-app folder
cd android-app

# Generate Gradle wrapper (first time only - requires Gradle installed)
# Option A: If you have Gradle installed
gradle wrapper --gradle-version 8.2

# Option B: Download wrapper JAR manually
mkdir -p gradle/wrapper
curl -L https://github.com/nicmcd/gradle/raw/master/gradle/wrapper/gradle-wrapper.jar \
  -o gradle/wrapper/gradle-wrapper.jar

# Accept Android SDK licenses
yes | $ANDROID_HOME/cmdline-tools/latest/bin/sdkmanager --licenses

# Build debug APK
./gradlew assembleDebug

# Find your APK
ls -la app/build/outputs/apk/debug/app-debug.apk
```

## Release APK (For Distribution)

To create a signed release APK for distribution:

```bash
# Generate a signing key (one time)
keytool -genkey -v -keystore superlite-release.keystore \
  -alias superlite -keyalg RSA -keysize 2048 -validity 10000

# Add to app/build.gradle under android { }:
signingConfigs {
    release {
        storeFile file('../superlite-release.keystore')
        storePassword 'your-password'
        keyAlias 'superlite'
        keyPassword 'your-password'
    }
}
buildTypes {
    release {
        signingConfig signingConfigs.release
        minifyEnabled true
        proguardFiles getDefaultProguardFile('proguard-android-optimize.txt'), 'proguard-rules.pro'
    }
}

# Build release APK
./gradlew assembleRelease
```

## App Details

- **Package**: ke.co.superlite.crm
- **App Name**: SuperLite CRM
- **CRM URL**: https://crm.superlite.co.ke
- **Min Android**: 7.0 (API 24)
- **Target Android**: 14 (API 34)

## Features

- Full WebView with JavaScript, DOM storage, and cookies
- Splash screen with app branding
- Pull-to-refresh to reload page
- File upload support (photos, documents)
- File download with notification
- GPS/location permission support
- Offline error screen with retry button
- Back button navigation (goes back in browser history)
- External links (tel:, mailto:, whatsapp:) open native apps
- Progress bar during page loading
- Session persistence across app restarts

## Customization

- **Change URL**: Edit `crm_url` in `res/values/strings.xml`
- **Change colors**: Edit `res/values/colors.xml`
- **Change icon**: Replace files in `res/mipmap-*/ic_launcher.png`
- **Change app name**: Edit `app_name` in `res/values/strings.xml`
