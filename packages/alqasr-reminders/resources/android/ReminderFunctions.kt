package com.alqasr.reminders

import android.Manifest
import android.app.Activity
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.util.Log
import androidx.core.app.ActivityCompat
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import androidx.work.ExistingWorkPolicy
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.Worker
import androidx.work.WorkerParameters
import androidx.work.workDataOf
import com.nativephp.mobile.bridge.BridgeFunction
import java.util.concurrent.TimeUnit

/**
 * Namespace: "Reminders.*"
 *
 * Reminders are WorkManager one-time jobs, so they survive app restarts and phone reboots
 * without any extra receivers. The PHP side re-sends the full schedule after every sync.
 */
object ReminderFunctions {

    const val CHANNEL_ID = "alqasr_reminders"
    private const val TAG = "alqasr-reminder"
    private const val PERMISSION_REQUEST_CODE = 7301

    fun workName(id: String) = "alqasr-reminder-$id"

    fun ensureChannel(context: Context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        if (manager.getNotificationChannel(CHANNEL_ID) == null) {
            val channel = NotificationChannel(CHANNEL_ID, "Reservation reminders", NotificationManager.IMPORTANCE_HIGH)
            channel.description = "Reminders before upcoming reservations"
            manager.createNotificationChannel(channel)
        }
    }

    fun hasPermission(context: Context): Boolean =
        Build.VERSION.SDK_INT < 33 ||
            ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED

    /**
     * Parameters: id (string), title (string), body (string), at (epoch milliseconds)
     */
    class Schedule(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val id = parameters["id"] as? String ?: return mapOf("success" to false, "error" to "id is required")
            val title = parameters["title"] as? String ?: ""
            val body = parameters["body"] as? String ?: ""
            val at = (parameters["at"] as? Number)?.toLong()
                ?: (parameters["at"] as? String)?.toLongOrNull()
                ?: return mapOf("success" to false, "error" to "at is required")

            val delay = at - System.currentTimeMillis()
            if (delay <= 0) {
                return mapOf("success" to false, "error" to "time is in the past")
            }

            return try {
                ensureChannel(context)
                val request = OneTimeWorkRequestBuilder<ReminderWorker>()
                    .setInitialDelay(delay, TimeUnit.MILLISECONDS)
                    .setInputData(workDataOf("id" to id, "title" to title, "body" to body))
                    .addTag(TAG)
                    .build()
                WorkManager.getInstance(context.applicationContext)
                    .enqueueUniqueWork(workName(id), ExistingWorkPolicy.REPLACE, request)
                mapOf("success" to true)
            } catch (e: Exception) {
                Log.e("ReminderFunctions", "schedule failed: ${e.message}", e)
                mapOf("success" to false, "error" to (e.message ?: "unknown error"))
            }
        }
    }

    /** Parameters: id (string) */
    class Cancel(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val id = parameters["id"] as? String ?: return mapOf("success" to false, "error" to "id is required")
            WorkManager.getInstance(context.applicationContext).cancelUniqueWork(workName(id))
            return mapOf("success" to true)
        }
    }

    class CancelAll(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            WorkManager.getInstance(context.applicationContext).cancelAllWorkByTag(TAG)
            return mapOf("success" to true)
        }
    }

    class RequestPermission(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            ensureChannel(context)
            if (hasPermission(context)) {
                return mapOf("granted" to true)
            }
            val activity = context as? Activity ?: return mapOf("granted" to false, "requested" to false)
            activity.runOnUiThread {
                ActivityCompat.requestPermissions(activity, arrayOf(Manifest.permission.POST_NOTIFICATIONS), PERMISSION_REQUEST_CODE)
            }
            return mapOf("granted" to false, "requested" to true)
        }
    }
}

/**
 * Shows the reminder notification when its time comes.
 */
class ReminderWorker(context: Context, params: WorkerParameters) : Worker(context, params) {
    override fun doWork(): Result {
        val id = inputData.getString("id") ?: return Result.success()
        val title = inputData.getString("title") ?: ""
        val body = inputData.getString("body") ?: ""
        val context = applicationContext

        ReminderFunctions.ensureChannel(context)
        if (!ReminderFunctions.hasPermission(context)) {
            Log.w("ReminderWorker", "Notification permission not granted; skipping $id")
            return Result.success()
        }

        val launch = context.packageManager.getLaunchIntentForPackage(context.packageName)?.apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
        }
        val pending = launch?.let {
            PendingIntent.getActivity(context, id.hashCode(), it, PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT)
        }

        val notification = NotificationCompat.Builder(context, ReminderFunctions.CHANNEL_ID)
            .setSmallIcon(android.R.drawable.ic_popup_reminder)
            .setColor(0xFFE09A15.toInt())
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(NotificationCompat.CATEGORY_REMINDER)
            .setAutoCancel(true)
            .apply { if (pending != null) setContentIntent(pending) }
            .build()

        try {
            NotificationManagerCompat.from(context).notify(id.hashCode(), notification)
        } catch (e: SecurityException) {
            Log.w("ReminderWorker", "Cannot post notification: ${e.message}")
        }
        return Result.success()
    }
}
