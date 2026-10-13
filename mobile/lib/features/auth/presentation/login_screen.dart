import 'package:flutter/material.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/theme/tradex_colors.dart';
import '../data/auth_repository.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({
    super.key,
    required this.authRepository,
    required this.onAuthenticated,
  });

  final AuthRepository authRepository;
  final VoidCallback onAuthenticated;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();
  final TextEditingController _mfaController = TextEditingController();

  bool _obscurePassword = true;
  bool _useRecoveryCode = false;
  bool _submitting = false;
  String? _errorMessage;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _mfaController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    setState(() {
      _submitting = true;
      _errorMessage = null;
    });

    try {
      await widget.authRepository.login(
        email: _emailController.text,
        password: _passwordController.text,
        totpCode: _useRecoveryCode ? null : _mfaController.text,
        recoveryCode: _useRecoveryCode ? _mfaController.text : null,
      );
      if (mounted) {
        widget.onAuthenticated();
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _errorMessage = error.message);
      }
    } on Object {
      if (mounted) {
        setState(() {
          _errorMessage = 'Unable to sign in right now. Please try again.';
        });
      }
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(
        children: <Widget>[
          const Positioned.fill(child: _LoginBackground()),
          SafeArea(
            child: Center(
              child: SingleChildScrollView(
                padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 28),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 460),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: <Widget>[
                      const _BrandHeader(),
                      const SizedBox(height: 30),
                      Container(
                        padding: const EdgeInsets.fromLTRB(20, 22, 20, 22),
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                            colors: <Color>[
                              Color(0xFF121714),
                              Color(0xFF090C0B),
                            ],
                          ),
                          borderRadius: BorderRadius.circular(24),
                          border: Border.all(
                            color: TradeXColors.borderStrong.withValues(alpha: 0.82),
                          ),
                          boxShadow: <BoxShadow>[
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.46),
                              blurRadius: 28,
                              offset: const Offset(0, 16),
                            ),
                          ],
                        ),
                        child: Form(
                          key: _formKey,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: <Widget>[
                              const Text(
                                'Secure Sign In',
                                style: TextStyle(
                                  color: TradeXColors.primaryText,
                                  fontSize: 24,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 7),
                              const Text(
                                'Use your TradeX account and authenticator code to continue.',
                                style: TextStyle(
                                  color: TradeXColors.secondaryText,
                                  fontSize: 14,
                                  height: 1.4,
                                ),
                              ),
                              const SizedBox(height: 22),
                              _TradeXField(
                                controller: _emailController,
                                label: 'Email',
                                keyboardType: TextInputType.emailAddress,
                                textInputAction: TextInputAction.next,
                                prefixIcon: Icons.mail_outline_rounded,
                                validator: (String? value) {
                                  final String text = (value ?? '').trim();
                                  if (text.isEmpty || !text.contains('@')) {
                                    return 'Enter your TradeX email address.';
                                  }
                                  return null;
                                },
                              ),
                              const SizedBox(height: 14),
                              _TradeXField(
                                controller: _passwordController,
                                label: 'Password',
                                obscureText: _obscurePassword,
                                textInputAction: TextInputAction.next,
                                prefixIcon: Icons.lock_outline_rounded,
                                suffix: IconButton(
                                  onPressed: () => setState(
                                    () => _obscurePassword = !_obscurePassword,
                                  ),
                                  icon: Icon(
                                    _obscurePassword
                                        ? Icons.visibility_off_outlined
                                        : Icons.visibility_outlined,
                                    color: TradeXColors.secondaryText,
                                  ),
                                ),
                                validator: (String? value) {
                                  if ((value ?? '').isEmpty) {
                                    return 'Enter your password.';
                                  }
                                  return null;
                                },
                              ),
                              const SizedBox(height: 14),
                              _TradeXField(
                                controller: _mfaController,
                                label: _useRecoveryCode
                                    ? 'Recovery code'
                                    : '6-digit authenticator code',
                                keyboardType: _useRecoveryCode
                                    ? TextInputType.text
                                    : TextInputType.number,
                                textInputAction: TextInputAction.done,
                                prefixIcon: Icons.shield_outlined,
                                onSubmitted: (_) => _submitting ? null : _submit(),
                                validator: (String? value) {
                                  final String text = (value ?? '').trim();
                                  if (_useRecoveryCode) {
                                    if (text.isEmpty) {
                                      return 'Enter a recovery code.';
                                    }
                                  } else if (!RegExp(r'^\d{6}$').hasMatch(text)) {
                                    return 'Enter the 6-digit code from your authenticator.';
                                  }
                                  return null;
                                },
                              ),
                              const SizedBox(height: 10),
                              Align(
                                alignment: Alignment.centerLeft,
                                child: TextButton(
                                  onPressed: _submitting
                                      ? null
                                      : () {
                                          setState(() {
                                            _useRecoveryCode = !_useRecoveryCode;
                                            _mfaController.clear();
                                          });
                                        },
                                  style: TextButton.styleFrom(
                                    foregroundColor: TradeXColors.goldBright,
                                    padding: const EdgeInsets.symmetric(horizontal: 0),
                                  ),
                                  child: Text(
                                    _useRecoveryCode
                                        ? 'Use authenticator code instead'
                                        : 'Use a recovery code instead',
                                  ),
                                ),
                              ),
                              if (_errorMessage != null) ...<Widget>[
                                const SizedBox(height: 8),
                                Container(
                                  padding: const EdgeInsets.all(12),
                                  decoration: BoxDecoration(
                                    color: TradeXColors.error.withValues(alpha: 0.10),
                                    borderRadius: BorderRadius.circular(12),
                                    border: Border.all(
                                      color: TradeXColors.error.withValues(alpha: 0.45),
                                    ),
                                  ),
                                  child: Text(
                                    _errorMessage!,
                                    style: const TextStyle(
                                      color: Color(0xFFFFB4B4),
                                      fontSize: 13,
                                      height: 1.35,
                                    ),
                                  ),
                                ),
                              ],
                              const SizedBox(height: 18),
                              SizedBox(
                                height: 52,
                                child: FilledButton(
                                  onPressed: _submitting ? null : _submit,
                                  style: FilledButton.styleFrom(
                                    backgroundColor: TradeXColors.goldBright,
                                    foregroundColor: Colors.black,
                                    disabledBackgroundColor:
                                        TradeXColors.goldSoft.withValues(alpha: 0.45),
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(14),
                                    ),
                                  ),
                                  child: _submitting
                                      ? const SizedBox(
                                          width: 22,
                                          height: 22,
                                          child: CircularProgressIndicator(
                                            strokeWidth: 2.4,
                                            color: Colors.black,
                                          ),
                                        )
                                      : const Text(
                                          'Sign In Securely',
                                          style: TextStyle(
                                            fontSize: 15,
                                            fontWeight: FontWeight.w700,
                                          ),
                                        ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 18),
                      const Text(
                        'Protected by TradeX device binding, rotating refresh tokens and multi-factor authentication.',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: TradeXColors.tertiaryText,
                          fontSize: 12,
                          height: 1.4,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _LoginBackground extends StatelessWidget {
  const _LoginBackground();

  @override
  Widget build(BuildContext context) {
    return const DecoratedBox(
      decoration: BoxDecoration(
        color: TradeXColors.background,
        gradient: RadialGradient(
          center: Alignment(0.75, -0.8),
          radius: 1.0,
          colors: <Color>[
            Color(0x332D2205),
            TradeXColors.background,
          ],
        ),
      ),
    );
  }
}

class _BrandHeader extends StatelessWidget {
  const _BrandHeader();

  @override
  Widget build(BuildContext context) {
    return const Column(
      children: <Widget>[
        Text(
          'TRADEX',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: TradeXColors.goldBright,
            fontSize: 34,
            fontWeight: FontWeight.w800,
            letterSpacing: 5,
          ),
        ),
        SizedBox(height: 6),
        Text(
          'OS FOR EVERY TRADER',
          style: TextStyle(
            color: TradeXColors.secondaryText,
            fontSize: 11,
            fontWeight: FontWeight.w600,
            letterSpacing: 2.2,
          ),
        ),
      ],
    );
  }
}

class _TradeXField extends StatelessWidget {
  const _TradeXField({
    required this.controller,
    required this.label,
    required this.prefixIcon,
    required this.validator,
    this.keyboardType,
    this.textInputAction,
    this.obscureText = false,
    this.suffix,
    this.onSubmitted,
  });

  final TextEditingController controller;
  final String label;
  final IconData prefixIcon;
  final FormFieldValidator<String> validator;
  final TextInputType? keyboardType;
  final TextInputAction? textInputAction;
  final bool obscureText;
  final Widget? suffix;
  final ValueChanged<String>? onSubmitted;

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      keyboardType: keyboardType,
      textInputAction: textInputAction,
      obscureText: obscureText,
      autocorrect: false,
      enableSuggestions: !obscureText,
      style: const TextStyle(color: TradeXColors.primaryText),
      validator: validator,
      onFieldSubmitted: onSubmitted,
      decoration: InputDecoration(
        labelText: label,
        labelStyle: const TextStyle(color: TradeXColors.secondaryText),
        prefixIcon: Icon(prefixIcon, color: TradeXColors.goldBright, size: 21),
        suffixIcon: suffix,
        filled: true,
        fillColor: TradeXColors.backgroundSoft,
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: TradeXColors.borderStrong),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: TradeXColors.goldBright, width: 1.2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: TradeXColors.error),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: TradeXColors.error, width: 1.2),
        ),
      ),
    );
  }
}
