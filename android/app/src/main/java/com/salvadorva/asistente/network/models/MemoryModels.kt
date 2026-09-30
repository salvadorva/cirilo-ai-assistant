package com.salvadorva.asistente.network.models

/** F4-06: GET /api/mobile/memory. Hechos agrupados por categoría. */
data class MemoryResponse(
    val categories: Map<String, String>? = null,
    val facts: Map<String, List<MemoryFact>>? = null,
    val total: Int? = null,
    val extraction_enabled: Boolean? = null,
)

/** source_type = extracted (aprendido) | user_explicit (lo dijiste o lo editaste tú). */
data class MemoryFact(
    val id: Int? = null,
    val key: String? = null,
    val value: String? = null,
    val confidence: Double? = null,
    val source_type: String? = null,
)

data class MemoryValueRequest(val value: String)

data class MemorySettingsRequest(val extraction_enabled: Boolean)
