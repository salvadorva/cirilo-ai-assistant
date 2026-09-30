package com.salvadorva.asistente.reminders

import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.HTTP
import retrofit2.http.Header
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query

/** Endpoints Sanctum de la sección 1 y 3–5 del contrato. La app nunca crea recordatorios. */
interface ContextualReminderApi {

    @POST("api/mobile/device-token")
    suspend fun registerDevice(@Body body: DeviceRegistrationRequest): Response<DeviceRegistrationResponse>

    /**
     * §8.1 (v1.1): reclamo explícito de una instalación en conflicto. Mismo cuerpo que el
     * registro; `installation_id` + token FCM vigente son la prueba de posesión.
     */
    @POST("api/mobile/device-token/claim")
    suspend fun claimInstallation(@Body body: DeviceRegistrationRequest): Response<DeviceClaimResponse>

    @HTTP(method = "DELETE", path = "api/mobile/device-token", hasBody = true)
    suspend fun removeDevice(@Body body: DeviceTokenRemoveRequest): Response<Unit>

    @GET("api/mobile/contextual-reminders")
    suspend fun list(
        @Query("state") state: String = ReminderDetail.STATE_PENDING,
        @Query("per_page") perPage: Int = 50,
    ): Response<ReminderListResponse>

    @GET("api/mobile/contextual-reminders/{id}")
    suspend fun detail(@Path("id") id: String): Response<ReminderDetail>

    /** `action` = complete | cancel | snooze. */
    @POST("api/mobile/contextual-reminders/{id}/{action}")
    suspend fun act(
        @Path("id") id: String,
        @Path("action") action: String,
        @Header("Idempotency-Key") idempotencyKey: String,
        @Body body: ReminderActionRequest,
    ): Response<ReminderDetail>

    @POST("api/mobile/contextual-reminders/{id}/receipts")
    suspend fun receipt(@Path("id") id: String, @Body body: ReceiptRequest): Response<ReceiptResponse>
}
