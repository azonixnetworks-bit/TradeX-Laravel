import 'package:flutter/material.dart';

import '../theme/tradex_colors.dart';

class TradeXBottomNav extends StatelessWidget {
  const TradeXBottomNav({
    super.key,
    required this.currentIndex,
    required this.onTap,
  });

  final int currentIndex;
  final ValueChanged<int> onTap;

  static const List<_NavItemData> _items = <_NavItemData>[
    _NavItemData(label: 'Home', icon: Icons.home_rounded),
    _NavItemData(label: 'Learn', icon: Icons.menu_book_rounded),
    _NavItemData(label: 'Trade Ideas', icon: Icons.lightbulb_outline_rounded),
    _NavItemData(label: 'Affiliate', icon: Icons.groups_2_outlined),
    _NavItemData(label: 'Profile', icon: Icons.person_outline_rounded),
  ];

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      minimum: const EdgeInsets.fromLTRB(16, 0, 16, 12),
      child: Container(
        height: 84,
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: <Color>[
              Color(0xFF111513),
              Color(0xFF090C0B),
            ],
          ),
          borderRadius: BorderRadius.circular(24),
          border: Border.all(
            color: TradeXColors.borderStrong.withValues(alpha: 0.75),
          ),
          boxShadow: <BoxShadow>[
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.55),
              blurRadius: 24,
              offset: const Offset(0, 12),
            ),
          ],
        ),
        child: Row(
          children: List<Widget>.generate(
            _items.length,
            (int index) {
              final bool active = index == currentIndex;
              final _NavItemData item = _items[index];

              return Expanded(
                child: Semantics(
                  button: true,
                  selected: active,
                  label: '${item.label} tab',
                  child: InkWell(
                    borderRadius: BorderRadius.circular(20),
                    onTap: () => onTap(index),
                    child: Stack(
                      alignment: Alignment.center,
                      children: <Widget>[
                        if (active)
                          Positioned.fill(
                            child: DecoratedBox(
                              decoration: BoxDecoration(
                                borderRadius: BorderRadius.circular(20),
                                gradient: RadialGradient(
                                  radius: 0.9,
                                  colors: <Color>[
                                    TradeXColors.gold.withValues(alpha: 0.20),
                                    Colors.transparent,
                                  ],
                                ),
                              ),
                            ),
                          ),
                        Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: <Widget>[
                            Icon(
                              item.icon,
                              size: 28,
                              color: active
                                  ? TradeXColors.goldBright
                                  : TradeXColors.secondaryText,
                              shadows: active
                                  ? <Shadow>[
                                      Shadow(
                                        color: TradeXColors.gold.withValues(alpha: 0.55),
                                        blurRadius: 10,
                                      ),
                                    ]
                                  : null,
                            ),
                            const SizedBox(height: 5),
                            FittedBox(
                              fit: BoxFit.scaleDown,
                              child: Text(
                                item.label,
                                maxLines: 1,
                                style: TextStyle(
                                  color: active
                                      ? TradeXColors.goldBright
                                      : TradeXColors.secondaryText,
                                  fontSize: 11.5,
                                  fontWeight:
                                      active ? FontWeight.w600 : FontWeight.w400,
                                ),
                              ),
                            ),
                            const SizedBox(height: 7),
                            AnimatedContainer(
                              duration: const Duration(milliseconds: 180),
                              width: active ? 28 : 0,
                              height: 3,
                              decoration: BoxDecoration(
                                color: TradeXColors.goldBright,
                                borderRadius: BorderRadius.circular(99),
                                boxShadow: active
                                    ? <BoxShadow>[
                                        BoxShadow(
                                          color: TradeXColors.gold.withValues(alpha: 0.65),
                                          blurRadius: 8,
                                        ),
                                      ]
                                    : null,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}

class _NavItemData {
  const _NavItemData({required this.label, required this.icon});

  final String label;
  final IconData icon;
}
