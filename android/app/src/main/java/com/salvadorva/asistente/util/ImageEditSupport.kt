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
        // Con inJustDecodeBounds, decodeStream devuelve null siempre: solo llena las dimensiones.
        val stream = resolver.openInputStream(uri) ?: return null
        stream.use { BitmapFactory.decodeStream(it, null, bounds) }
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

    /** Códigos tras los cuales ya no tiene sentido seguir ajustando esa imagen. */
    val REFINE_TERMINAL_CODES = setOf("edit_limit_reached", "already_refined", "image_expired", "not_refinable")

    // Solo cuando todo el mensaje es el cierre: «ok, ahora hazlo azul» sigue siendo un ajuste.
    private val CLOSE_PHRASE = "(listo|gracias|muchas gracias|perfecto|genial|excelente|ok|okay|vale|me gusta|me encanta|" +
        "ya esta|asi esta|asi esta bien|asi quedo|asi quedo bien|dejalo asi|asi dejalo|esta bien|quedo bien|quedo perfecto|quedo genial|" +
        "ya quedo|no gracias|no, gracias|no, asi esta bien|no, ya esta)"
    private val CLOSE_REFINE = Regex("^$CLOSE_PHRASE([ ,]+($CLOSE_PHRASE|cirilo))*$")

    /** «Quedó bien», «gracias», «listo»…: el usuario cierra los ajustes en lugar de pedir otro. */
    fun closesRefine(text: String): Boolean {
        val normalized = java.text.Normalizer.normalize(text.trim().lowercase(), java.text.Normalizer.Form.NFD)
            .replace(Regex("\\p{M}+"), "")
            .replace(Regex("[¡!¿?.\\s]+$|^[¡!¿?.\\s]+"), "")
            .replace(Regex("\\s+"), " ")
        return CLOSE_REFINE.matches(normalized)
    }

    /** Mensaje para el usuario según el código de error del servidor. */
    fun errorMessage(httpCode: Int, code: String?, message: String?): String = when (code) {
        "content_rejected" -> "No puedo hacer esa edición con esa imagen o instrucción. Prueba con otra."
        "image_quota_exceeded" -> message ?: "Alcanzaste el límite diario de imágenes."
        "provider_timeout" -> "La edición tardó demasiado. No se reintentó para no cobrarla dos veces."
        "image_edit_disabled" -> "La edición de imágenes aún no está disponible."
        "idempotency_conflict", "in_progress" -> "Esa edición ya se está procesando."
        "edit_limit_reached" -> "Esa imagen ya tuvo todos sus ajustes. Adjunta una foto para empezar otra edición."
        "already_refined" -> "Esa imagen ya se ajustó; sigue desde la más reciente."
        "image_expired" -> "Esa imagen ya venció. Adjunta la foto de nuevo para editarla."
        else -> message ?: "No se pudo editar la imagen ($httpCode)."
    }
}
