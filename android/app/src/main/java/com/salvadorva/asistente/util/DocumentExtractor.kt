package com.salvadorva.asistente.util

import android.content.Context
import android.net.Uri
import android.provider.OpenableColumns
import com.tom_roush.pdfbox.pdmodel.PDDocument
import com.tom_roush.pdfbox.text.PDFTextStripper

object DocumentExtractor {

    const val MAX_PAGES = 20
    const val MAX_CHARS = 60_000

    sealed class Result {
        data class Success(
            val filename: String,
            val content: String,
            val pageCount: Int,
            val truncated: Boolean,
        ) : Result()

        data class Error(val message: String) : Result()
    }

    fun extract(context: Context, uri: Uri): Result {
        val resolver = context.contentResolver
        val mimeType = resolver.getType(uri) ?: ""
        val filename = queryFilename(context, uri) ?: "documento"

        return when {
            mimeType == "application/pdf" || filename.endsWith(".pdf", ignoreCase = true) ->
                extractPdf(context, uri, filename)
            mimeType.startsWith("text/") ||
                filename.endsWith(".txt", ignoreCase = true) ||
                filename.endsWith(".md", ignoreCase = true) ->
                extractText(context, uri, filename)
            else ->
                Result.Error("Tipo no soportado: $mimeType. Solo .txt, .md o .pdf.")
        }
    }

    private fun extractText(context: Context, uri: Uri, filename: String): Result {
        return try {
            val content = context.contentResolver.openInputStream(uri)?.use { stream ->
                stream.bufferedReader().readText()
            } ?: return Result.Error("No se pudo leer el archivo")

            val (final, truncated) = truncate(content)
            Result.Success(filename, final, pageCount = 1, truncated = truncated)
        } catch (e: Exception) {
            Result.Error("Error leyendo texto: ${e.message ?: "desconocido"}")
        }
    }

    private fun extractPdf(context: Context, uri: Uri, filename: String): Result {
        return try {
            context.contentResolver.openInputStream(uri)?.use { stream ->
                PDDocument.load(stream).use { document ->
                    val pageCount = document.numberOfPages
                    if (pageCount > MAX_PAGES) {
                        return Result.Error(
                            "PDF excede $MAX_PAGES páginas (tiene $pageCount). " +
                                "Recortalo o pasame solo las páginas que importan."
                        )
                    }
                    val stripper = PDFTextStripper().apply {
                        startPage = 1
                        endPage = pageCount
                    }
                    val text = stripper.getText(document).trim()
                    if (text.isEmpty()) {
                        return Result.Error(
                            "El PDF no tiene texto extraíble (¿imagen escaneada?). " +
                                "Intenta con un PDF basado en texto."
                        )
                    }
                    val (final, truncated) = truncate(text)
                    Result.Success(filename, final, pageCount, truncated)
                }
            } ?: Result.Error("No se pudo abrir el PDF")
        } catch (e: Exception) {
            Result.Error("Error procesando PDF: ${e.message ?: "desconocido"}")
        }
    }

    private fun truncate(text: String): Pair<String, Boolean> {
        return if (text.length > MAX_CHARS) {
            text.take(MAX_CHARS) + "\n\n[… contenido truncado a $MAX_CHARS caracteres]" to true
        } else {
            text to false
        }
    }

    private fun queryFilename(context: Context, uri: Uri): String? {
        var name: String? = null
        context.contentResolver.query(uri, null, null, null, null)?.use { cursor ->
            if (cursor.moveToFirst()) {
                val idx = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME)
                if (idx >= 0) name = cursor.getString(idx)
            }
        }
        return name
    }
}
