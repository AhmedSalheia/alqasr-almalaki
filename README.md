# Alqasr Almalaky Calendar · تقويم القصر الملكي

Reservations calendar for the **Al Qasr Al Malaki** venue administration.
Laravel 12 + Livewire 3 + Tailwind CSS 4, packaged as an Android app with **NativePHP Mobile v4**,
synced through **Firebase** (Authentication + Cloud Firestore).

- Arabic (RTL) and English · light, dark and system themes · venue gold/navy palette
- Month view and day view, add / edit / delete reservations
  (groom name, date & time, DJ, phone, total cost, paid amount, remaining, notes)
- Event page with a designed **event card → PNG** (Ivory or Royal style), saved to the phone gallery or shared
- Works **offline** from the local copy; changes sync automatically every 30 s (configurable) when online
- Admin creates users; every account is **tied to its devices** — a new device needs admin approval

---

## 1. Requirements (your computer)

- PHP 8.3+ with `sqlite3`, `zip`, `curl`, `openssl` extensions, Composer 2
- Node.js 20+
- Android Studio (SDK + an emulator, or a phone with USB debugging), JDK 17
- NativePHP Mobile docs: https://nativephp.com/docs/mobile/4

## 2. Install

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite && php artisan migrate
npm install
```

## 3. Firebase setup (once)

1. https://console.firebase.google.com → **Add project**.
2. **Build → Authentication → Get started → Sign-in method → Email/Password → Enable.**
3. **Build → Firestore Database → Create database** (production mode, a region near you, e.g. `europe-west`).
4. **Firestore → Rules**: paste the content of [`firestore.rules`](firestore.rules) → **Publish**.
5. **Project settings → General**: copy the *Project ID* and the *Web API Key* into `.env`:
   ```env
   FIREBASE_API_KEY=AIza...
   FIREBASE_PROJECT_ID=alqasr-almalaky
   ```
   (If "Web API Key" is empty, add a Web app under *Your apps* — it is created then.)
6. **Create the first admin** (only this one is created by hand):
   - Authentication → Users → **Add user** (email + password). Copy the new user's **UID**.
   - Firestore → **Start collection** `users` → Document ID = *that UID* → fields:

     | field | type | value |
     |---|---|---|
     | `name` | string | Your name |
     | `email` | string | the email |
     | `role` | string | `admin` |
     | `active` | boolean | `true` |
     | `devices` | array | *(empty)* |

Everyone else is created from inside the app: **Users → Add user** (a username such as `sara` also works).

### Firestore data
```
users/{uid}            name, email, role (admin|staff), active, devices[{id,name,addedAt}]
deviceRequests/{uid_deviceId}   uid, name, deviceId, deviceName, status (pending|approved|denied)
events/{uuid}          groomName, date, startTime, endTime, djName, phone, totalCost, paidAmount,
                       notes, deleted, createdBy, createdByName, updatedByName, createdAt, updatedAt
```

## 4. Try it in the browser first

```bash
npm run build
php artisan serve          # http://127.0.0.1:8000
```
No Firebase yet? `php artisan alqasr:demo` signs in a local demo admin with sample reservations
(`php artisan alqasr:demo --clear` removes it). Don't run it before building the phone app.

Tests: `php artisan test` (Firebase is faked).

## 5. Run on Android

```bash
php artisan alqasr:demo --clear       # make sure no demo data is bundled
php artisan native:install android    # first time only (creates ./nativephp)
npm run build:android                 # builds the CSS/JS for the Android web view
php artisan native:run android        # pick the emulator / connected phone
```
Release build for sharing or Google Play:
```bash
php artisan native:credentials android     # creates a signing keystore (keep it safe!)
php artisan native:package android --build-type=release
```
Bump the version with `php artisan native:release patch` before each new release.

App icon (`public/icon.png`) and splash screens (`public/splash.png`, `public/splash-dark.png`)
are picked up automatically by NativePHP.

## 6. How it works

| Topic | Implementation |
|---|---|
| UI | Livewire 3 full-page components + Tailwind CSS 4 in NativePHP's web view (`NATIVEPHP_BOOT_MODE=web`). Fonts are bundled (Tajawal, El Messiri, Cormorant Garamond) so the app looks the same offline. |
| Local data | On-device SQLite (`events`, `app_state`). The UI only ever reads the local copy → instant and offline-proof. |
| Sync | `App\Services\SyncService`: pushes rows marked `pending` with one batched Firestore commit (server timestamp), then pulls everything changed since the last cursor. Deletes are soft so they reach every device. A local edit that has not been pushed yet always wins. |
| Timer | `<livewire:sync-status>` polls every `ALQASR_SYNC_INTERVAL` seconds and queues `App\Jobs\SyncEvents` on NativePHP's background queue worker (falls back to inline). Saving a reservation triggers a sync immediately. |
| Auth | Firebase Auth REST (email/password). Tokens are refreshed automatically and kept on the device so the app opens offline. |
| Devices | Device id from NativePHP (`Device::getId()`, stable for the app on Android). First device is tied automatically (`ALQASR_AUTO_BIND_FIRST_DEVICE`), others create a `deviceRequests` doc the admin approves in **Devices**. Each sync re-checks the account: disabled users / untied devices are signed out and the local copy is wiped. |
| Event card | `resources/views/components/event-card.blade.php`, rendered with `html-to-image` at 1080×1350 px (4:5, ideal for WhatsApp/Instagram). On the phone the PNG is saved to **Pictures/Alqasr Almalaky** by the small local plugin in `packages/alqasr-gallery-saver` (Android MediaStore, no permission needed), or shared through `nativephp/mobile-share`. |
| PNG vs JPG | PNG is the right choice: the card is text, thin gold lines and flat colours — PNG keeps them razor sharp, JPG would add blur/artefacts around the Arabic letters. |

### Notes
- Firestore rules let only active users read/write reservations and only admins manage users.
  The device check itself is enforced by the app (Firebase rules cannot see the phone's id).
- Android 13+ is required (`min_sdk 33`, NativePHP default). Tailwind CSS 4 needs the Android System WebView ≥ 111, which every updated Android 13+ device has.
- NativePHP v4 also offers fully native "SuperNative/EDGE" screens. This app uses the web view on purpose: it gives full control of the luxury design, perfect Arabic/RTL typography and the pixel-exact PNG card. Screens can be migrated later one by one if desired.

## 7. Configuration (`.env`)

| Key | Default | |
|---|---|---|
| `ALQASR_SYNC_INTERVAL` | `30` | seconds between automatic syncs |
| `ALQASR_AUTO_BIND_FIRST_DEVICE` | `true` | tie a user's first device automatically |
| `ALQASR_USERNAME_DOMAIN` | `alqasr-almalaky.app` | appended to usernames without `@` |
| `ALQASR_CURRENCY_SYMBOL` | `₪` | |
| `ALQASR_WEEK_STARTS_ON` | `6` | 0 = Sunday … 6 = Saturday |
| `APP_LOCALE` | `ar` | default language (each device can switch in Settings) |

## Brand assets
`resources/brand/` holds the upscaled logo (≈2000 px): white background, transparent, a dark-mode
variant (navy parts turned ivory) and the emblem alone.
