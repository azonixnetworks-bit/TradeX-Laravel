import 'package:flutter/material.dart';

import '../screens/foundation_screen.dart';

class TradeXAppShell extends StatefulWidget {
  const TradeXAppShell({super.key});

  @override
  State<TradeXAppShell> createState() => _TradeXAppShellState();
}

class _TradeXAppShellState extends State<TradeXAppShell> {
  int _currentIndex = 0;

  static const _screens = <Widget>[
    FoundationScreen(
      title: 'Home',
      subtitle: 'TradeX mobile foundation is ready.',
      showScreenerHold: true,
    ),
    FoundationScreen(
      title: 'Learn',
      subtitle: 'Education integration will use the frozen Laravel v1 API.',
    ),
    FoundationScreen(
      title: 'Trade Ideas',
      subtitle: 'Trade Ideas integration will preserve premium API gating.',
    ),
    FoundationScreen(
      title: 'Affiliate',
      subtitle: 'Mobile API contract pending. No endpoint is invented here.',
    ),
    FoundationScreen(
      title: 'Profile',
      subtitle: 'Profile and device security integration follows in later phases.',
    ),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(index: _currentIndex, children: _screens),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _currentIndex,
        onDestinationSelected: (index) => setState(() => _currentIndex = index),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home), label: 'Home'),
          NavigationDestination(icon: Icon(Icons.school_outlined), selectedIcon: Icon(Icons.school), label: 'Learn'),
          NavigationDestination(icon: Icon(Icons.insights_outlined), selectedIcon: Icon(Icons.insights), label: 'Trade Ideas'),
          NavigationDestination(icon: Icon(Icons.group_outlined), selectedIcon: Icon(Icons.group), label: 'Affiliate'),
          NavigationDestination(icon: Icon(Icons.person_outline), selectedIcon: Icon(Icons.person), label: 'Profile'),
        ],
      ),
    );
  }
}
