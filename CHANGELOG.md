# Changelog

All notable changes to this project are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/) and the project uses [Semantic Versioning](https://semver.org/).

## [1.1.0] - 2026-10-25

### Added
- In-app updates from GitHub Releases: the app checks for a newer release every 6 hours (and from Settings → "Check for updates"), downloads the APK in the background on Wi-Fi (or on request on mobile data), and opens Android's installer with one tap. New local plugin `packages/alqasr-updater`.
- Venue DJ by default: new reservations use the venue DJ (أحمد النجار, 0592758021, set in `config/alqasr.php` → `venue_dj`); choosing "External DJ" shows the DJ name and phone fields, both required.

### Changed
- Arabic month names follow `lang/ar/calendar.php`; the " / 10" month number shows only in the calendar page header, not on the event card, dates or reminders.

## [1.0.2] - 2026-10-22

### Fixed
- The 1.0.1 build was published without the app fixes below (only the CI changes made it in); 1.0.2 ships them.
- Network failures of any kind (TLS errors, resets, captive Wi-Fi) are treated as "offline", so they never sign anyone out.

## [1.0.1] - 2026-10-21

### Fixed
- Event card "Download" and "Share" now work on Android: only the fonts the card uses are embedded (the WebView could not draw the full set), images are inlined before drawing, the PNG upload sends the CSRF token in the body, and the gallery plugin detects the phone correctly.
- The Users and Devices screens no longer freeze the app while offline; Firebase calls fail fast for 20 seconds after a connection error and background sync skips the network when the phone reports it is offline.
- Signing credentials (`ANDROID_KEYSTORE_*`, `ANDROID_KEY_*`) are no longer bundled into the app's `.env`.

### Added
- Clear error messages on the card toast, `[card]` / `[client]` entries in the app log, and `scripts/android-logs.ps1` to follow the phone's logs.
- CI: the release job uses the runner's Android SDK and has permission to publish GitHub Releases.

## [1.0.0] - 2026-10-21

### Added
- Reservations calendar with month and day views, swipe navigation and a "Today" shortcut.
- Add / edit / delete reservations: groom name and phone, date and time, DJ name and phone, total cost, paid amount, remaining, notes.
- Reservation page with call / WhatsApp buttons and a designed event card (Ivory or Royal style) saved as PNG to the gallery or shared.
- Arabic (RTL) and English, light / dark / system themes, Al Qasr Al Malaki gold and navy design.
- Firebase sync (Authentication + Firestore): works offline from the local copy and syncs every 30 seconds.
- Admin-managed users with device binding: the first device is tied automatically, new devices need admin approval.
- Monthly financial totals visible to admins only.
- Reminder notifications 3 days before each reservation, for the person who added it and for every admin.
- GitHub Actions: tests on every push; signed APK + AAB attached to a GitHub Release for every `v*` tag.
