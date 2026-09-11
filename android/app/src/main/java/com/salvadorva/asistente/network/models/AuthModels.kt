package com.salvadorva.asistente.network.models

data class LoginRequest(
    val email: String,
    val password: String,
    val device_name: String
)

data class LoginResponse(
    val token: String,
    val user: UserInfo
)

data class MeResponse(
    val user: UserInfo
)

data class UserInfo(
    val id: Int,
    val name: String,
    val email: String,
    val role: String?,
    val ai_provider: String?,
    val avatar: String?,
    val bio: String?,
    val has_nextcloud: Boolean = false
)
