import 'package:flutter/material.dart';

import 'navigation/tradex_app_shell.dart';

class TradeXApp extends StatelessWidget {
  const TradeXApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'TradeX OS',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF111111)),
        scaffoldBackgroundColor: const Color(0xFFF8F8F7),
      ),
      home: const TradeXAppShell(),
    );
  }
}
