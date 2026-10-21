package com.alqasr.gallerysaver

import android.content.ContentValues
import android.content.Context
import android.os.Environment
import android.provider.MediaStore
import android.util.Log
import com.nativephp.mobile.bridge.BridgeFunction
import java.io.File

/**
 * Namespace: "Gallery.*"
 */
object GalleryFunctions {

    /**
     * Copy an image into the shared photo gallery using MediaStore (Android 10+, no permission needed).
     * Parameters:
     *   - path:  string  absolute path of the image file to copy (required)
     *   - name:  string  file name to show in the gallery (optional)
     *   - album: string  sub folder inside Pictures/ (optional)
     */
    class SaveImage(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val path = parameters["path"] as? String ?: ""
            if (path.isEmpty()) {
                return mapOf("success" to false, "error" to "path is required")
            }

            val source = File(path)
            if (!source.exists()) {
                return mapOf("success" to false, "error" to "file not found")
            }

            val name = (parameters["name"] as? String)?.takeIf { it.isNotBlank() } ?: source.name
            val album = (parameters["album"] as? String)?.takeIf { it.isNotBlank() } ?: "Alqasr Almalaky"
            val mime = if (name.lowercase().endsWith(".jpg") || name.lowercase().endsWith(".jpeg")) "image/jpeg" else "image/png"

            return try {
                val resolver = context.contentResolver
                val values = ContentValues().apply {
                    put(MediaStore.Images.Media.DISPLAY_NAME, name)
                    put(MediaStore.Images.Media.MIME_TYPE, mime)
                    put(MediaStore.Images.Media.RELATIVE_PATH, Environment.DIRECTORY_PICTURES + File.separator + album)
                    put(MediaStore.Images.Media.IS_PENDING, 1)
                }

                val uri = resolver.insert(MediaStore.Images.Media.EXTERNAL_CONTENT_URI, values)
                    ?: return mapOf("success" to false, "error" to "could not create gallery entry")

                resolver.openOutputStream(uri)?.use { output ->
                    source.inputStream().use { input -> input.copyTo(output) }
                }

                values.clear()
                values.put(MediaStore.Images.Media.IS_PENDING, 0)
                resolver.update(uri, values, null, null)

                Log.d("GalleryFunctions", "Saved $name to Pictures/$album")
                mapOf("success" to true, "uri" to uri.toString())
            } catch (e: Exception) {
                Log.e("GalleryFunctions", "Failed to save image: ${e.message}", e)
                mapOf("success" to false, "error" to (e.message ?: "unknown error"))
            }
        }
    }
}
