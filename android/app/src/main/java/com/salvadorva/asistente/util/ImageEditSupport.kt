package com.salvadorva.asistente.util

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Matrix
import android.media.ExifInterface
import android.net.Uri
import java.io.ByteArrayOutputStream

/** IE1: helpers de «Editar imagen». */
object ImageEditSupport {
    const val MAX_SIDE = 2048
    const val MIN_INSTRUCTION = 3

    /**
     * Prepara la foto para subirla: respeta la orientación EXIF, limita el lado mayor a 2048 px y la
     * comprime en JPEG (el servidor acepta hasta 5 MB y además la reescribe sin metadatos).
     */
    fun prepare(context: Context, uri: Uri): ByteArray? {
        val resolver = context.contentResolver
        val bounds = BitmapFactory.Options().apply { inJustDecodeBounds = true }
        resolver.openInputStream(uri)?.use { BitmapFactory.decodeStream(it, null, bounds) } ?: return null
        if (bounds.outWidth <= 0 || bounds.outHeight <= 0) return null

        var sample = 1
        while (maxOf(bounds.outWidth, bounds.outHeight) / (sample * 2) >= MAX_SIDE) sample *= 2
        val decoded = resolver.openInputStream(uri)?.use {
            BitmapFactory.decodeStream(it, null, BitmapFactory.Options().apply { inSampleSize = sample })
        } ?: return null

        val rotation = resolver.openInputStream(uri)?.use {
            when (ExifInterface(it).getAttributeInt(ExifInterface.TAG_ORIENTATION, ExifInterface.ORIENTATION_NORMAL)) {
                ExifInterface.ORIENTATION_ROTATE_90 -> 90f
                ExifInterface.ORIENTATION_ROTATE_180 -> 180f
                ExifInterface.ORIENTATION_ROTATE_270 -> 270f
                else -> 0f
            }
        } ?: 0f

        val scale = minOf(1f, MAX_SIDE.toFloat() / maxOf(decoded.width, decoded.height))
        val matrix = Matrix().apply { postScale(scale, scale); postRotate(rotation) }
        val ready = Bitmap.createBitmap(decoded, 0, 0, decoded.width, decoded.height, matrix, true)
        return ByteArrayOutputStream().use { out ->
            ready.compress(Bitmap.CompressFormat.JPEG, 90, out)
            out.toByteArray()
        }
    }

    /** Mensaje para el usuario según el código de error del servidor. */
    fun errorMessage(httpCode: Int, code: String?, message: String?): String = when (code) {
        "content_rejected" -> "No puedo hacer esa edición con esa imagen o instrucción. Prueba con otra."
        "image_quota_exceeded" -> message ?: "Alcanzaste el límite diario de imágenes."
        "provider_timeout" -> "La edición tardó demasiado. No se reintentó para no cobrarla dos veces."
        "image_edit_disabled" -> "La edición de imágenes aún no está disponible."
        "idempotency_conflict", "in_progress" -> "Esa edición ya se está procesando."
        else -> message ?: "No se pudo editar la imagen ($httpCode)."
    }
}
