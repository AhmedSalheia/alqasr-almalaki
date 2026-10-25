# Live logs from the app on the connected phone.
#   -Laravel  : the app's laravel.log ([card], [client], sync ...). Waits for the file to appear.
#   -Native   : Android side (gallery / share / reminders plugins and the web view console)
param([switch]$Native)

$appId = "com.alqasr.almalaky.calendar"

if ($Native) {
    adb logcat -v time -s GalleryFunctions ShareFunctions.File ShareFunctions.Url ReminderFunctions ReminderWorker chromium
} else {
    adb shell run-as $appId tail -F -n 200 app_storage/persisted_data/storage/logs/laravel.log
}
