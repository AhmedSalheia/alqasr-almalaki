# alqasr/mobile-updater

Background APK download + Android installer hand-off for the Alqasr Almalaky Calendar.

```php
use Alqasr\Updater\Updater;

Updater::download($apkUrl, '1.0.3', unmeteredOnly: true);  // starts a background download
Updater::status();   // ['state' => 'downloading', 'progress' => 42, ...]
Updater::install();  // opens Android's "Do you want to update this app?" screen
```

The APK must be signed with the same key as the installed app, otherwise Android refuses it.
