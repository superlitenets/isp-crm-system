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
gradle wrapper --gradle-version 8.2

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

- Animated splash screen with logo, app name, and tagline
- Bottom navigation bar (Home, Tickets, Customers, Network, Reports)
- Full WebView with JavaScript, DOM storage, and cookies
- Pull-to-refresh to reload page
- Camera integration for direct photo capture uploads
- Multi-file upload support (photos, documents)
- File download with toast notification and quick-access to Downloads
- GPS/location permission support
- Offline error screen with auto-reconnect when internet returns
- Network state monitoring with live connection/disconnection alerts
- Double-tap back to exit (prevents accidental exits)
- Back button navigates browser history
- External links (tel:, mailto:, whatsapp:, sms:) open native apps
- Smooth loading overlay with fade animation
- Progress bar during page loading
- Session and cookie persistence across app restarts
- HTTPS-only security enforcement
- Snackbar notifications instead of intrusive alerts

## Customization

- **Change URL**: Edit `crm_url` in `res/values/strings.xml`
- **Change colors**: Edit `res/values/colors.xml`
- **Change icon**: Replace files in `res/mipmap-*/ic_launcher.png`
- **Change app name**: Edit `app_name` in `res/values/strings.xml`
- **Change tagline**: Edit `splash_tagline` in `res/values/strings.xml`
- **Change nav items**: Edit `res/menu/bottom_nav_menu.xml` and update URLs in `MainActivity.java`
