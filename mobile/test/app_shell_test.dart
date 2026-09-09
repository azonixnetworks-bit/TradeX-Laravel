import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tradex_mobile/app/tradex_app.dart';

void main() {
  testWidgets('bottom navigation preserves the frozen exact order', (tester) async {
    await tester.pumpWidget(const TradeXApp());

    final labels = ['Home', 'Learn', 'Trade Ideas', 'Affiliate', 'Profile'];
    for (final label in labels) {
      expect(find.widgetWithText(NavigationDestination, label), findsOneWidget);
    }

    final destinations = tester.widgetList<NavigationDestination>(
      find.byType(NavigationDestination),
    );
    expect(destinations.map((item) => item.label).toList(), labels);
  });

  testWidgets('customer screener is held as coming soon', (tester) async {
    await tester.pumpWidget(const TradeXApp());

    expect(find.text('Screener'), findsOneWidget);
    expect(find.text('COMING SOON'), findsOneWidget);
  });

  testWidgets('tabs switch without changing the frozen shell', (tester) async {
    await tester.pumpWidget(const TradeXApp());

    await tester.tap(find.widgetWithText(NavigationDestination, 'Learn'));
    await tester.pumpAndSettle();
    expect(find.text('Education integration will use the frozen Laravel v1 API.'), findsOneWidget);

    await tester.tap(find.widgetWithText(NavigationDestination, 'Profile'));
    await tester.pumpAndSettle();
    expect(find.text('Profile and device security integration follows in later phases.'), findsOneWidget);
  });
}
