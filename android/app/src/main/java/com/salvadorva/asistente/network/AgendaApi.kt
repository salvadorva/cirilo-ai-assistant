package com.salvadorva.asistente.network

import com.salvadorva.asistente.network.models.AgendaDeleteResponse
import com.salvadorva.asistente.network.models.AgendaEvent
import com.salvadorva.asistente.network.models.AgendaEventRequest
import com.salvadorva.asistente.network.models.AgendaEventsResponse
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.Header
import retrofit2.http.PUT
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query

interface AgendaApi {

    /** Sin params devuelve los próximos 30 días desde hoy. */
    @GET("api/mobile/agenda/events")
    suspend fun list(
        @Query("from") from: String? = null,
        @Query("to") to: String? = null,
    ): Response<AgendaEventsResponse>

    @GET("api/mobile/agenda/upcoming")
    suspend fun upcoming(): Response<AgendaEventsResponse>

    @GET("api/mobile/agenda/events/{id}")
    suspend fun show(@Path("id") id: Int): Response<AgendaEvent>

    /** idempotencyKey: una por formulario; un doble toque o reintento no duplica el evento (F2-05). */
    @POST("api/mobile/agenda/events")
    suspend fun create(
        @Header("Idempotency-Key") idempotencyKey: String?,
        @Body body: AgendaEventRequest,
    ): Response<AgendaEvent>

    @PUT("api/mobile/agenda/events/{id}")
    suspend fun update(@Path("id") id: Int, @Body body: AgendaEventRequest): Response<AgendaEvent>

    @DELETE("api/mobile/agenda/events/{id}")
    suspend fun delete(@Path("id") id: Int): Response<AgendaDeleteResponse>
}
