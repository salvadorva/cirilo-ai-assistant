package com.salvadorva.asistente.network

import com.salvadorva.asistente.network.models.TaskCreateRequest
import com.salvadorva.asistente.network.models.TaskResponse
import com.salvadorva.asistente.network.models.TaskUpdateRequest
import com.salvadorva.asistente.network.models.TodayResponse
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.PATCH
import retrofit2.http.POST
import retrofit2.http.Path

/** F6: vista «Hoy» y pendientes. */
interface TodayApi {
    @GET("api/mobile/today")
    suspend fun today(): Response<TodayResponse>

    @POST("api/mobile/tasks")
    suspend fun createTask(@Body body: TaskCreateRequest): Response<TaskResponse>

    @PATCH("api/mobile/tasks/{id}")
    suspend fun updateTask(@Path("id") id: Int, @Body body: TaskUpdateRequest): Response<TaskResponse>
}
