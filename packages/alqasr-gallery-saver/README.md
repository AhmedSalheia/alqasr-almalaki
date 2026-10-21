# alqasr/mobile-gallery-saver

A very small NativePHP Mobile plugin used by the Alqasr Almalaky Calendar app to save the
event card PNG directly into the phone gallery (`Pictures/Alqasr Almalaky`).

Android only (MediaStore, Android 10+, no storage permission required).

```php
use Alqasr\GallerySaver\Gallery;

Gallery::saveImage(storage_path('app/cards/card.png'), 'card.png');
```
