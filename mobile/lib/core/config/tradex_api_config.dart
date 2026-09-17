abstract final class TradeXApiConfig {
  static const String _rawBaseUrl = String.fromEnvironment(
    'TRADEX_API_BASE_URL',
    defaultValue: 'http://127.0.0.1:8000',
  );

  static String get baseUrl {
    final String value = _rawBaseUrl.trim();
    if (value.endsWith('/')) {
      return value.substring(0, value.length - 1);
    }
    return value;
  }

  static const Duration requestTimeout = Duration(seconds: 20);
}
