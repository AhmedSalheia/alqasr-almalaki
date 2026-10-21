# Changelog

All notable changes to this project are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/) and the project uses [Semantic Versioning](https://semver.org/).

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
