import 'package:flutter/material.dart';

class FoundationScreen extends StatelessWidget {
  const FoundationScreen({
    required this.title,
    required this.subtitle,
    this.showScreenerHold = false,
    super.key,
  });

  final String title;
  final String subtitle;
  final bool showScreenerHold;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          Text('TradeX OS', style: Theme.of(context).textTheme.labelLarge),
          const SizedBox(height: 12),
          Text(title, style: Theme.of(context).textTheme.headlineMedium),
          const SizedBox(height: 8),
          Text(subtitle),
          if (showScreenerHold) ...[
            const SizedBox(height: 24),
            const Card(
              child: ListTile(
                leading: Icon(Icons.candlestick_chart),
                title: Text('Screener'),
                subtitle: Text('COMING SOON'),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
