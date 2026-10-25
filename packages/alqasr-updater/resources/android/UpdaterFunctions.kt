package com.alqasr.updater

import android.content.Context
import android.content.Intent
import android.net.ConnectivityManager
import android.net.Uri
import android.os.Build
import android.provider.Settings
import android.util.Log
import androidx.core.content.FileProvider
import com.nativephp.mobile.bridge.BridgeFunction
import java.io.File
import java.io.FileOutputStream
import java.net.HttpURLConnection
import java.net.URL

/** Shared state of the (single) update download. */
object UpdaterState {
    @Volatile var state: String = "idle"     // idle | downloading | done | error
    @Volatile var bytes: Long = 0L
    @Volatile var total: Long = -1L
    @Volatile var error: String = ""
    @Volatile var version: String = ""
}

/**
 * Namespace: "Updater.*"
 *
 * The APK is downloaded on a background thread into the app's cache folder (shared through
 * the app's FileProvider), checked to be a build of this same app, and then handed to
 * Android's package installer. Android always asks the user to confirm the install.
 */
object UpdaterFunctions {
    private const val TAG = "UpdaterFunctions"

    fun apkFile(context: Context): File {
        val dir = File(context.cacheDir, "updates")
        dir.mkdirs()
        return File(dir, "update.apk")
    }

    private fun isMetered(context: Context): Boolean {
        val cm = context.getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager
        return cm.isActiveNetworkMetered
    }

    /** Parameters: url (string), version (string), unmeteredOnly (bool, skip on mobile data) */
    class Download(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val url = parameters["url"] as? String ?: return mapOf("started" to false, "error" to "url is required")
            val version = parameters["version"] as? String ?: ""
            val unmeteredOnly = parameters["unmeteredOnly"] as? Boolean ?: false
            val file = apkFile(context)

            if (UpdaterState.state == "downloading") {
                return mapOf("started" to true, "already" to true)
            }
            if (UpdaterState.state == "done" && UpdaterState.version == version && file.exists()) {
                return mapOf("started" to true, "already" to true)
            }
            if (unmeteredOnly && isMetered(context)) {
                return mapOf("started" to false, "reason" to "metered")
            }

            UpdaterState.state = "downloading"
            UpdaterState.bytes = 0L
            UpdaterState.total = -1L
            UpdaterState.error = ""
            UpdaterState.version = version

            val app = context.applicationContext
            Thread { download(app, url, file) }.apply {
                isDaemon = true
                name = "alqasr-update-download"
                start()
            }
            return mapOf("started" to true)
        }
    }

    private fun download(context: Context, url: String, file: File) {
        val part = File(file.parentFile, "update.apk.part")
        try {
            var target = URL(url)
            var connection: HttpURLConnection? = null
            for (attempt in 0 until 6) {
                val conn = target.openConnection() as HttpURLConnection
                conn.instanceFollowRedirects = true
                conn.connectTimeout = 15000
                conn.readTimeout = 30000
                conn.setRequestProperty("Accept", "application/octet-stream")
                val code = conn.responseCode
                if (code in 300..399) {
                    val location = conn.getHeaderField("Location")
                    conn.disconnect()
                    if (location == null) throw Exception("redirect without location")
                    target = URL(target, location)
                    continue
                }
                if (code !in 200..299) {
                    conn.disconnect()
                    throw Exception("HTTP $code")
                }
                connection = conn
                break
            }
            val conn = connection ?: throw Exception("too many redirects")

            UpdaterState.total = conn.contentLengthLong
            conn.inputStream.use { input ->
                FileOutputStream(part).use { output ->
                    val buffer = ByteArray(64 * 1024)
                    while (true) {
                        val read = input.read(buffer)
                        if (read < 0) break
                        output.write(buffer, 0, read)
                        UpdaterState.bytes += read
                    }
                }
            }
            conn.disconnect()

            @Suppress("DEPRECATION")
            val info = context.packageManager.getPackageArchiveInfo(part.absolutePath, 0)
            if (info == null || info.packageName != context.packageName) {
                throw Exception("the downloaded file is not an update of this app")
            }

            if (file.exists()) file.delete()
            if (!part.renameTo(file)) throw Exception("could not save the update")

            UpdaterState.state = "done"
            Log.d(TAG, "Update downloaded: ${file.length()} bytes")
        } catch (e: Exception) {
            part.delete()
            UpdaterState.error = e.message ?: "download failed"
            UpdaterState.state = "error"
            Log.e(TAG, "Update download failed: ${e.message}", e)
        }
    }

    class Status(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val total = UpdaterState.total
            val progress = if (total > 0) ((UpdaterState.bytes * 100) / total).toInt() else 0
            val canInstall = Build.VERSION.SDK_INT < Build.VERSION_CODES.O ||
                context.packageManager.canRequestPackageInstalls()
            return mapOf(
                "state" to UpdaterState.state,
                "bytes" to UpdaterState.bytes,
                "total" to total,
                "progress" to progress,
                "error" to UpdaterState.error,
                "version" to UpdaterState.version,
                "canInstall" to canInstall,
                "fileReady" to apkFile(context).exists(),
            )
        }
    }

    class Install(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val file = apkFile(context)
            if (!file.exists()) {
                return mapOf("success" to false, "error" to "no update downloaded")
            }

            return try {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O && !context.packageManager.canRequestPackageInstalls()) {
                    // First time: Android asks the user to allow installs from this app.
                    val settings = Intent(Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES, Uri.parse("package:${context.packageName}"))
                    settings.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                    context.startActivity(settings)
                    return mapOf("success" to false, "needsPermission" to true)
                }

                val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
                val intent = Intent(Intent.ACTION_VIEW).apply {
                    setDataAndType(uri, "application/vnd.android.package-archive")
                    addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                    addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                }
                context.startActivity(intent)
                mapOf("success" to true)
            } catch (e: Exception) {
                Log.e(TAG, "Could not open installer: ${e.message}", e)
                mapOf("success" to false, "error" to (e.message ?: "could not open installer"))
            }
        }
    }
}
