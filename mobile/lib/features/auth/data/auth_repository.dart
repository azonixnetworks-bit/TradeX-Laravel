import '../../../core/auth/auth_session.dart';
import '../../../core/auth/secure_session_store.dart';
import '../../../core/device/device_identity.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/network/tradex_api_client.dart';

class AuthRepository {
  AuthRepository({
    required TradeXApiClient apiClient,
    required SecureSessionStore sessionStore,
    required DeviceIdentity deviceIdentity,
  })  : _apiClient = apiClient,
        _sessionStore = sessionStore,
        _deviceIdentity = deviceIdentity;

  final TradeXApiClient _apiClient;
  final SecureSessionStore _sessionStore;
  final DeviceIdentity _deviceIdentity;

  Future<AuthSession?> restoreSession() async {
    final AuthSession? session = await _sessionStore.readSession();
    if (session == null) {
      return null;
    }

    if (session.refreshTokenExpired) {
      await _sessionStore.clearSession();
      return null;
    }

    if (session.accessTokenExpired) {
      try {
        return await _apiClient.refreshSession();
      } on ApiException catch (error) {
        if (error.isAuthenticationFailure) {
          return null;
        }
        rethrow;
      }
    }

    return session;
  }

  Future<AuthSession> login({
    required String email,
    required String password,
    String? totpCode,
    String? recoveryCode,
  }) async {
    final DeviceMetadata device = await _deviceIdentity.load();
    final Map<String, dynamic> payload = <String, dynamic>{
      'email': email.trim().toLowerCase(),
      'password': password,
      ...device.toApiJson(),
      if (totpCode != null && totpCode.isNotEmpty) 'totp_code': totpCode.trim(),
      if (recoveryCode != null && recoveryCode.isNotEmpty)
        'recovery_code': recoveryCode.trim(),
    };

    final Map<String, dynamic> response = await _apiClient.publicPost(
      '/api/v1/auth/login',
      payload,
    );
    final Map<String, dynamic> data =
        Map<String, dynamic>.from(response['data'] as Map);

    final DateTime now = DateTime.now();
    final AuthSession session = AuthSession(
      accessToken: data['access_token'] as String,
      refreshToken: data['refresh_token'] as String,
      accessExpiresAt: now.add(
        Duration(seconds: (data['expires_in'] as num).toInt()),
      ),
      refreshExpiresAt: now.add(
        Duration(seconds: (data['refresh_expires_in'] as num).toInt()),
      ),
      sessionUuid: data['session_uuid'] as String,
      user: Map<String, dynamic>.from(data['user'] as Map),
    );

    await _sessionStore.writeSession(session);
    return session;
  }

  Future<void> logout() async {
    try {
      await _apiClient.postJson(
        '/api/v1/auth/logout',
        const <String, dynamic>{},
      );
    } finally {
      await _sessionStore.clearSession();
    }
  }

  Future<void> clearLocalSession() => _sessionStore.clearSession();
}
