# Android Java Auth Example

This example uses Retrofit 2 and OkHttp to perform the `/api/login` call, persist the Sanctum token, and automatically append the `Authorization: Bearer <token>` header to every authenticated request. Inject the base URL from your build config (e.g., `BuildConfig.API_BASE_URL`) so it matches the backend `APP_URL`.

```java
import java.io.IOException;

import okhttp3.Interceptor;
import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.Response;
import retrofit2.Call;
import retrofit2.Retrofit;
import retrofit2.converter.gson.GsonConverterFactory;
import retrofit2.http.Body;
import retrofit2.http.GET;
import retrofit2.http.Header;
import retrofit2.http.POST;

public class LoginRequest {
    public final String email;
    public final String password;

    public LoginRequest(String email, String password) {
        this.email = email;
        this.password = password;
    }
}

public class LoginResponse {
    public boolean success;
    public String message;
    public LoginPayload data;
}

public class LoginPayload {
    public UserDto user;
    public String token;
}

public class UserResponse {
    public boolean success;
    public UserPayload data;
}

public class UserPayload {
    public UserDto user;
}

public class UserDto {
    public long id;
    public String name;
    public String email;
    public String role;
}

public interface AuthApi {
    @POST("/api/login")
    Call<LoginResponse> login(@Body LoginRequest payload);

    @POST("/api/logout")
    Call<Void> logout(@Header("Authorization") String bearer);

    @GET("/api/me")
    Call<UserResponse> me(@Header("Authorization") String bearer);
}

public interface TokenStore {
    void save(String token);
    String read();
    void clear();
}

public class AuthRepository {
    private final AuthApi api;
    private final TokenStore tokenStore;

    public AuthRepository(AuthApi api, TokenStore tokenStore) {
        this.api = api;
        this.tokenStore = tokenStore;
    }

    public void login(String email, String password) throws IOException {
        Response<LoginResponse> response = api.login(new LoginRequest(email, password)).execute();
        if (!response.isSuccessful() || response.body() == null || !response.body().success) {
            throw new IllegalStateException(response.body() != null ? response.body().message : "Login failed");
        }
        tokenStore.save(response.body().data.token);
    }

    public UserDto currentUser() throws IOException {
        String token = tokenStore.read();
        if (token == null) throw new IllegalStateException("Missing token");
        Response<UserResponse> response = api.me("Bearer " + token).execute();
        if (!response.isSuccessful() || response.body() == null || response.body().data == null) {
            throw new IllegalStateException("Unable to fetch profile");
        }
        return response.body().data.user;
    }

    public void logout() throws IOException {
        String token = tokenStore.read();
        if (token == null) return;
        api.logout("Bearer " + token).execute();
        tokenStore.clear();
    }
}

public class AuthInterceptor implements Interceptor {
    private final TokenStore tokenStore;

    public AuthInterceptor(TokenStore tokenStore) {
        this.tokenStore = tokenStore;
    }

    @Override
    public Response intercept(Chain chain) throws IOException {
        Request original = chain.request();
        Request.Builder builder = original.newBuilder();
        String token = tokenStore.read();
        if (token != null) {
            builder.header("Authorization", "Bearer " + token);
        }
        return chain.proceed(builder.build());
    }
}

public class RetrofitFactory {
    public static Retrofit create(String baseUrl, TokenStore tokenStore) {
        OkHttpClient client = new OkHttpClient.Builder()
                .addInterceptor(new AuthInterceptor(tokenStore))
                .build();

        return new Retrofit.Builder()
                .baseUrl(baseUrl)
                .client(client)
                .addConverterFactory(GsonConverterFactory.create())
                .build();
    }
}
```

> `TokenStore` can wrap `EncryptedSharedPreferences`, `DataStore`, or any other secure persistence layer on Android. Always clear the token locally after calling `/api/logout` so server-side Sanctum tokens are revoked and clients don't retain stale credentials.
