import 'package:flutter/material.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/theme/tradex_colors.dart';
import '../../home/presentation/home_screen.dart';
import '../data/auth_repository.dart';
import 'login_screen.dart';

class SessionGate extends StatefulWidget {
  const SessionGate({
    super.key,
    required this.authRepository,
  });

  final AuthRepository authRepository;

  @override
  State<SessionGate> createState() => _SessionGateState();
}

class _SessionGateState extends State<SessionGate> {
  bool _loading = true;
  bool _authenticated = false;
  String? _bootstrapError;

  @override
  void initState() {
    super.initState();
    _restore();
  }

  Future<void> _restore() async {
    setState(() {
      _loading = true;
      _bootstrapError = null;
    });

    try {
      final session = await widget.authRepository.restoreSession();
      if (!mounted) {
        return;
      }
      setState(() {
        _authenticated = session != null;
        _loading = false;
      });
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _bootstrapError = error.message;
        _loading = false;
      });
    } on Object {
      if (!mounted) {
        return;
      }
      setState(() {
        _bootstrapError = 'Unable to restore your TradeX session.';
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const _SessionLoadingScreen();
    }

    if (_bootstrapError != null) {
      return _SessionRestoreError(
        message: _bootstrapError!,
        onRetry: _restore,
        onSignInAgain: () async {
          await widget.authRepository.clearLocalSession();
          if (mounted) {
            setState(() {
              _bootstrapError = null;
              _authenticated = false;
            });
          }
        },
      );
    }

    if (!_authenticated) {
      return LoginScreen(
        authRepository: widget.authRepository,
        onAuthenticated: () {
          if (mounted) {
            setState(() => _authenticated = true);
          }
        },
      );
    }

    return const HomeScreen();
  }
}

class _SessionLoadingScreen extends StatelessWidget {
  const _SessionLoadingScreen();

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Text(
              'TRADEX',
              style: TextStyle(
                color: TradeXColors.goldBright,
                fontSize: 30,
                fontWeight: FontWeight.w800,
                letterSpacing: 5,
              ),
            ),
            SizedBox(height: 20),
            SizedBox(
              width: 26,
              height: 26,
              child: CircularProgressIndicator(
                strokeWidth: 2.4,
                color: TradeXColors.goldBright,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SessionRestoreError extends StatelessWidget {
  const _SessionRestoreError({
    required this.message,
    required this.onRetry,
    required this.onSignInAgain,
  });

  final String message;
  final VoidCallback onRetry;
  final VoidCallback onSignInAgain;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Container(
                padding: const EdgeInsets.all(22),
                decoration: BoxDecoration(
                  color: TradeXColors.surface,
                  borderRadius: BorderRadius.circular(22),
                  border: Border.all(color: TradeXColors.borderStrong),
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    const Icon(
                      Icons.cloud_off_rounded,
                      size: 42,
                      color: TradeXColors.goldBright,
                    ),
                    const SizedBox(height: 14),
                    const Text(
                      'Session check unavailable',
                      style: TextStyle(
                        color: TradeXColors.primaryText,
                        fontSize: 20,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      message,
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: TradeXColors.secondaryText,
                        height: 1.4,
                      ),
                    ),
                    const SizedBox(height: 20),
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton(
                        onPressed: onRetry,
                        style: FilledButton.styleFrom(
                          backgroundColor: TradeXColors.goldBright,
                          foregroundColor: Colors.black,
                        ),
                        child: const Text('Retry'),
                      ),
                    ),
                    TextButton(
                      onPressed: onSignInAgain,
                      style: TextButton.styleFrom(
                        foregroundColor: TradeXColors.secondaryText,
                      ),
                      child: const Text('Sign in again'),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
