# Android Kotlin Auth Example

This example uses Retrofit and OkHttp to perform the `/api/login` call, persist the Sanctum token, and automatically apply the `Authorization: Bearer <token>` header to every authenticated request. Inject the base URL from Android build config (e.g., `BuildConfig.API_BASE_URL`) so it matches the backend `APP_URL`.

```kotlin
data class LoginRequest(val email: String, val password: String)
data class LoginResponse(val success: Boolean, val message: String, val data: LoginPayload)
data class LoginPayload(val user: UserDto, val token: String)

data class UserResponse(val success: Boolean, val data: UserPayload)
data class UserPayload(val user: UserDto)

data class UserDto(val id: Long, val name: String, val email: String, val role: String)

interface AuthApi {
    @POST("/api/login")
    suspend fun login(@Body payload: LoginRequest): LoginResponse

    @POST("/api/logout")
    suspend fun logout(@Header("Authorization") bearer: String)

    @GET("/api/me")
    suspend fun me(@Header("Authorization") bearer: String): UserResponse
}

interface TokenStore {
    fun save(token: String)
    fun read(): String?
    fun clear()
}

class AuthRepository(
    private val api: AuthApi,
    private val tokenStore: TokenStore
) {
    suspend fun login(email: String, password: String) {
        val response = api.login(LoginRequest(email, password))
        if (!response.success) error(response.message)
        tokenStore.save(response.data.token)
    }

    suspend fun currentUser(): UserDto {
        val token = tokenStore.read() ?: error("Missing token")
        return api.me("Bearer $token").data.user
    }

    suspend fun logout() {
        val token = tokenStore.read() ?: return
        api.logout("Bearer $token")
        tokenStore.clear()
    }
}

fun buildHttpClient(tokenStore: TokenStore) = OkHttpClient.Builder()
    .addInterceptor { chain ->
        val builder = chain.request().newBuilder()
        tokenStore.read()?.let { builder.header("Authorization", "Bearer $it") }
        chain.proceed(builder.build())
    }
    .build()
```

> `TokenStore` can wrap `EncryptedSharedPreferences`, `DataStore`, or another secure persistence layer on Android. Make sure to clear the token on logout so Sanctum sessions are revoked server-side and client-side.
