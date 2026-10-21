# alqasr/mobile-reminders

Local reminder notifications for the Alqasr Almalaky Calendar (Android, WorkManager).
Reminders survive app restarts and reboots; the app re-sends the full schedule after every sync.

```php
use Alqasr\Reminders\Reminders;

Reminders::requestPermission();                       // Android 13+ asks once
Reminders::schedule('event-uuid', 'Title', 'Body', $unixTimestamp);
Reminders::cancel('event-uuid');
```
