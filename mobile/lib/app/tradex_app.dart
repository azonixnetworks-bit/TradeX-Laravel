import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../core/auth/secure_session_store.dart';
import '../core/device/device_identity.dart';
import '../core/network/tradex_api_client.dart';
import '../core/theme/tradex_colors.dart';
import '../core/theme/tradex_theme.dart';
import '../features/auth/data/auth_repository.dart';
import '../features/auth/presentation/session_gate.dart';

class TradeXApp extends StatefulWidget {
  const TradeXApp({super.key});

  @override
  State<TradeXApp> createState() => _TradeXAppState();
}

class _TradeXAppState extends State<TradeXApp> {
  late final SecureSessionStore _sessionStore;
  late final DeviceIdentity _deviceIdentity;
  late final TradeXApiClient _apiClient;
  late final AuthRepository _authRepository;

  @override
  void initState() {
    super.initState();
    _sessionStore = SecureSessionStore();
    _deviceIdentity = DeviceIdentity(_sessionStore);
    _apiClient = TradeXApiClient(
      sessionStore: _sessionStore,
      deviceIdentity: _deviceIdentity,
    );
    _authRepository = AuthRepository(
      apiClient: _apiClient,
      sessionStore: _sessionStore,
      deviceIdentity: _deviceIdentity,
    );
  }

  @override
  void dispose() {
    _apiClient.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    SystemChrome.setSystemUIOverlayStyle(
      const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.light,
        statusBarBrightness: Brightness.dark,
        systemNavigationBarColor: TradeXColors.background,
        systemNavigationBarIconBrightness: Brightness.light,
      ),
    );

    return MaterialApp(
      title: 'TradeX',
      debugShowCheckedModeBanner: false,
      theme: TradeXTheme.dark,
      home: SessionGate(authRepository: _authRepository),
    );
  }
}
