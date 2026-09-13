import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../core/theme/tradex_colors.dart';
import '../core/theme/tradex_theme.dart';
import '../features/home/presentation/home_screen.dart';

class TradeXApp extends StatelessWidget {
  const TradeXApp({super.key});

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
      home: const HomeScreen(),
    );
  }
}
