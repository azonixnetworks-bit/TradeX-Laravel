import 'package:flutter/material.dart';

import 'tradex_colors.dart';

abstract final class TradeXTheme {
  static ThemeData get dark {
    final ColorScheme colorScheme = const ColorScheme.dark(
      primary: TradeXColors.gold,
      secondary: TradeXColors.goldBright,
      surface: TradeXColors.surface,
      error: TradeXColors.error,
      onPrimary: Colors.black,
      onSecondary: Colors.black,
      onSurface: TradeXColors.primaryText,
      onError: Colors.white,
    );

    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: TradeXColors.background,
      splashColor: TradeXColors.gold.withValues(alpha: 0.08),
      highlightColor: TradeXColors.gold.withValues(alpha: 0.04),
      dividerColor: TradeXColors.border,
      textTheme: const TextTheme(
        headlineLarge: TextStyle(
          color: TradeXColors.primaryText,
          fontSize: 30,
          fontWeight: FontWeight.w700,
          height: 1.08,
          letterSpacing: -0.6,
        ),
        headlineMedium: TextStyle(
          color: TradeXColors.primaryText,
          fontSize: 24,
          fontWeight: FontWeight.w700,
          height: 1.15,
          letterSpacing: -0.35,
        ),
        titleLarge: TextStyle(
          color: TradeXColors.primaryText,
          fontSize: 20,
          fontWeight: FontWeight.w600,
          height: 1.2,
        ),
        titleMedium: TextStyle(
          color: TradeXColors.primaryText,
          fontSize: 17,
          fontWeight: FontWeight.w600,
          height: 1.25,
        ),
        bodyLarge: TextStyle(
          color: TradeXColors.primaryText,
          fontSize: 16,
          fontWeight: FontWeight.w400,
          height: 1.4,
        ),
        bodyMedium: TextStyle(
          color: TradeXColors.secondaryText,
          fontSize: 14,
          fontWeight: FontWeight.w400,
          height: 1.4,
        ),
        labelLarge: TextStyle(
          color: TradeXColors.primaryText,
          fontSize: 14,
          fontWeight: FontWeight.w600,
          height: 1.2,
        ),
        labelMedium: TextStyle(
          color: TradeXColors.secondaryText,
          fontSize: 12,
          fontWeight: FontWeight.w500,
          height: 1.2,
        ),
      ),
    );
  }
}
