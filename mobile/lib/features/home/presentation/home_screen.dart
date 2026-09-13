import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../core/theme/tradex_colors.dart';
import '../../../core/widgets/tradex_bottom_nav.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _selectedTab = 0;

  void _onNavTap(int index) {
    if (index == 0) {
      return;
    }

    setState(() => _selectedTab = index);

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          backgroundColor: TradeXColors.surfaceRaised,
          behavior: SnackBarBehavior.floating,
          content: Text(
            '${_tabLabel(index)} screen will be implemented next.',
            style: const TextStyle(color: TradeXColors.primaryText),
          ),
        ),
      );

    Future<void>.delayed(const Duration(milliseconds: 220), () {
      if (mounted) {
        setState(() => _selectedTab = 0);
      }
    });
  }

  String _tabLabel(int index) {
    const List<String> labels = <String>[
      'Home',
      'Learn',
      'Trade Ideas',
      'Affiliate',
      'Profile',
    ];
    return labels[index];
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(
        children: <Widget>[
          const Positioned.fill(child: _HomeBackground()),
          SafeArea(
            bottom: false,
            child: SingleChildScrollView(
              physics: const BouncingScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 18, 16, 124),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const <Widget>[
                  _GreetingHeader(),
                  SizedBox(height: 18),
                  _HeroBanner(),
                  SizedBox(height: 14),
                  _ContinueLearningSection(),
                  SizedBox(height: 14),
                  _LatestTradeIdeaSection(),
                  SizedBox(height: 18),
                  _MarketCategories(),
                  SizedBox(height: 18),
                  _LatestFromTradeXSection(),
                  SizedBox(height: 18),
                ],
              ),
            ),
          ),
          Positioned(
            left: 0,
            right: 0,
            bottom: 0,
            child: TradeXBottomNav(
              currentIndex: _selectedTab,
              onTap: _onNavTap,
            ),
          ),
        ],
      ),
    );
  }
}

class _HomeBackground extends StatelessWidget {
  const _HomeBackground();

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: TradeXColors.background,
        gradient: RadialGradient(
          center: const Alignment(0.78, -0.82),
          radius: 0.92,
          colors: <Color>[
            TradeXColors.goldDeep.withOpacity(0.18),
            TradeXColors.background.withOpacity(0.94),
            TradeXColors.background,
          ],
          stops: const <double>[0, 0.38, 1],
        ),
      ),
    );
  }
}

class _GreetingHeader extends StatelessWidget {
  const _GreetingHeader();

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: <Widget>[
        const Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                'Good Morning, Alex 👋',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: TradeXColors.primaryText,
                  fontSize: 26,
                  fontWeight: FontWeight.w600,
                  letterSpacing: -0.5,
                ),
              ),
              SizedBox(height: 4),
              Text(
                'Discipline Today, A Brighter Tomorrow.',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: TradeXColors.secondaryText,
                  fontSize: 14.5,
                  fontWeight: FontWeight.w400,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: 12),
        const _NotificationButton(),
        const SizedBox(width: 10),
        const _ProfileAvatar(),
      ],
    );
  }
}

class _NotificationButton extends StatelessWidget {
  const _NotificationButton();

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: 'Notifications, unread items',
      child: SizedBox(
        width: 44,
        height: 44,
        child: Stack(
          alignment: Alignment.center,
          children: <Widget>[
            IconButton(
              padding: EdgeInsets.zero,
              onPressed: () {},
              icon: const Icon(
                Icons.notifications_none_rounded,
                size: 29,
                color: TradeXColors.primaryText,
              ),
            ),
            Positioned(
              top: 5,
              right: 5,
              child: Container(
                width: 10,
                height: 10,
                decoration: BoxDecoration(
                  color: TradeXColors.error,
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: TradeXColors.background,
                    width: 1.5,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ProfileAvatar extends StatelessWidget {
  const _ProfileAvatar();

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: 'Open profile',
      child: Container(
        width: 48,
        height: 48,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: TradeXColors.surface,
          border: Border.all(color: TradeXColors.goldBright, width: 1.4),
          boxShadow: <BoxShadow>[
            BoxShadow(
              color: TradeXColors.gold.withOpacity(0.20),
              blurRadius: 14,
            ),
          ],
        ),
        child: const Text(
          'AL',
          style: TextStyle(
            color: TradeXColors.goldBright,
            fontSize: 17,
            fontWeight: FontWeight.w500,
          ),
        ),
      ),
    );
  }
}

class _HeroBanner extends StatelessWidget {
  const _HeroBanner();

  @override
  Widget build(BuildContext context) {
    return AspectRatio(
      aspectRatio: 2.65,
      child: Container(
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(23),
          border: Border.all(
            color: TradeXColors.goldBright.withOpacity(0.90),
            width: 1.2,
          ),
          boxShadow: <BoxShadow>[
            BoxShadow(
              color: TradeXColors.gold.withOpacity(0.19),
              blurRadius: 18,
              offset: const Offset(0, 8),
            ),
          ],
          gradient: const LinearGradient(
            begin: Alignment.centerLeft,
            end: Alignment.centerRight,
            colors: <Color>[
              Color(0xFF090B09),
              Color(0xFF111006),
              Color(0xFF050706),
            ],
          ),
        ),
        child: Stack(
          children: <Widget>[
            const Positioned.fill(
              child: CustomPaint(painter: _HeroGridPainter()),
            ),
            const Positioned(
              right: -4,
              top: 0,
              bottom: 0,
              width: 205,
              child: CustomPaint(painter: _HeroMarketArtPainter()),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 12, 14),
              child: FractionallySizedBox(
                widthFactor: 0.66,
                alignment: Alignment.centerLeft,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: <Widget>[
                    const Text(
                      "LET'S BUILD A",
                      style: TextStyle(
                        color: TradeXColors.goldBright,
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 4.0,
                      ),
                    ),
                    const SizedBox(height: 3),
                    const FittedBox(
                      fit: BoxFit.scaleDown,
                      alignment: Alignment.centerLeft,
                      child: Text(
                        'BETTER TRADER',
                        style: TextStyle(
                          color: TradeXColors.goldBright,
                          fontSize: 29,
                          fontWeight: FontWeight.w800,
                          height: 1.05,
                          letterSpacing: -0.5,
                        ),
                      ),
                    ),
                    const SizedBox(height: 5),
                    const Text(
                      'LEARN  |  PRACTICE  |  GROW',
                      maxLines: 1,
                      style: TextStyle(
                        color: TradeXColors.primaryText,
                        fontSize: 10.5,
                        fontWeight: FontWeight.w600,
                        letterSpacing: 2.0,
                      ),
                    ),
                    const Spacer(),
                    SizedBox(
                      height: 42,
                      child: FilledButton.icon(
                        onPressed: () {},
                        iconAlignment: IconAlignment.end,
                        style: FilledButton.styleFrom(
                          padding: const EdgeInsets.symmetric(horizontal: 18),
                          backgroundColor: TradeXColors.goldBright,
                          foregroundColor: Colors.black,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
                          elevation: 5,
                          shadowColor: TradeXColors.gold,
                        ),
                        icon: const Icon(Icons.arrow_forward_rounded, size: 20),
                        label: const Text(
                          'Continue Learning',
                          style: TextStyle(
                            fontSize: 13.5,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const Positioned(
              right: 13,
              bottom: 14,
              child: Text(
                'DISCIPLINE\nCREATES\nFREEDOM',
                textAlign: TextAlign.left,
                style: TextStyle(
                  color: TradeXColors.primaryText,
                  fontSize: 8.5,
                  fontWeight: FontWeight.w700,
                  height: 1.15,
                  letterSpacing: 0.7,
                ),
              ),
            ),
            const Positioned(
              bottom: 9,
              left: 0,
              right: 0,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: <Widget>[
                  _HeroDot(active: true),
                  SizedBox(width: 7),
                  _HeroDot(),
                  SizedBox(width: 7),
                  _HeroDot(),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _HeroDot extends StatelessWidget {
  const _HeroDot({this.active = false});

  final bool active;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 8,
      height: 8,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: active
            ? TradeXColors.goldBright
            : TradeXColors.secondaryText.withOpacity(0.45),
      ),
    );
  }
}

class _PremiumSection extends StatelessWidget {
  const _PremiumSection({
    required this.icon,
    required this.title,
    required this.child,
    this.showViewAll = true,
  });

  final IconData icon;
  final String title;
  final Widget child;
  final bool showViewAll;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: <Color>[
            Color(0xFF111513),
            Color(0xFF0A0D0C),
          ],
        ),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: TradeXColors.borderStrong.withOpacity(0.72)),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: Colors.black.withOpacity(0.28),
            blurRadius: 14,
            offset: const Offset(0, 7),
          ),
        ],
      ),
      child: Column(
        children: <Widget>[
          _SectionHeader(icon: icon, title: title, showViewAll: showViewAll),
          const SizedBox(height: 10),
          child,
        ],
      ),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({
    required this.icon,
    required this.title,
    required this.showViewAll,
  });

  final IconData icon;
  final String title;
  final bool showViewAll;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: <Widget>[
        Icon(icon, color: TradeXColors.goldBright, size: 24),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            title,
            style: const TextStyle(
              color: TradeXColors.primaryText,
              fontSize: 19,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
        if (showViewAll)
          TextButton(
            onPressed: () {},
            style: TextButton.styleFrom(
              foregroundColor: TradeXColors.goldBright,
              padding: const EdgeInsets.symmetric(horizontal: 4),
              minimumSize: const Size(44, 38),
            ),
            child: const Row(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Text(
                  'View All',
                  style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600),
                ),
                SizedBox(width: 2),
                Icon(Icons.chevron_right_rounded, size: 19),
              ],
            ),
          ),
      ],
    );
  }
}

class _ContinueLearningSection extends StatelessWidget {
  const _ContinueLearningSection();

  @override
  Widget build(BuildContext context) {
    return const _PremiumSection(
      icon: Icons.menu_book_rounded,
      title: 'Continue Learning',
      child: _LearningCard(),
    );
  }
}

class _LearningCard extends StatelessWidget {
  const _LearningCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: const Color(0xFF080B0A),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: TradeXColors.borderStrong.withOpacity(0.72)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: <Widget>[
          const SizedBox(
            width: 132,
            height: 94,
            child: _CourseThumbnail(),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                const Text(
                  'Module 3: Market Structure',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: TradeXColors.primaryText,
                    fontSize: 15.5,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 4),
                const Text(
                  'Lesson 2: Break of Structure (BOS)',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: TradeXColors.secondaryText,
                    fontSize: 12.5,
                  ),
                ),
                const SizedBox(height: 10),
                const Row(
                  children: <Widget>[
                    Expanded(
                      child: ClipRRect(
                        borderRadius: BorderRadius.all(Radius.circular(99)),
                        child: LinearProgressIndicator(
                          minHeight: 7,
                          value: 0.68,
                          backgroundColor: Color(0xFF252B29),
                          valueColor: AlwaysStoppedAnimation<Color>(
                            TradeXColors.goldBright,
                          ),
                        ),
                      ),
                    ),
                    SizedBox(width: 10),
                    Text(
                      '68%',
                      style: TextStyle(
                        color: TradeXColors.primaryText,
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 9),
                SizedBox(
                  height: 38,
                  child: FilledButton.icon(
                    onPressed: () {},
                    iconAlignment: IconAlignment.end,
                    style: FilledButton.styleFrom(
                      backgroundColor: TradeXColors.goldBright,
                      foregroundColor: Colors.black,
                      padding: const EdgeInsets.symmetric(horizontal: 14),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(9),
                      ),
                    ),
                    icon: const Icon(Icons.arrow_forward_rounded, size: 18),
                    label: const Text(
                      'Continue Learning',
                      style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _CourseThumbnail extends StatelessWidget {
  const _CourseThumbnail();

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(12),
      child: Stack(
        fit: StackFit.expand,
        children: <Widget>[
          const ColoredBox(color: Color(0xFF061019)),
          const CustomPaint(painter: _CourseChartPainter()),
          const Positioned(
            left: 8,
            top: 8,
            child: Text(
              'SMART MONEY\nCONCEPTS',
              style: TextStyle(
                color: Colors.white,
                fontSize: 10.5,
                fontWeight: FontWeight.w800,
                height: 0.92,
              ),
            ),
          ),
          Center(
            child: Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.black.withOpacity(0.62),
                border: Border.all(color: Colors.white, width: 1.4),
              ),
              child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 28),
            ),
          ),
        ],
      ),
    );
  }
}

class _LatestTradeIdeaSection extends StatelessWidget {
  const _LatestTradeIdeaSection();

  @override
  Widget build(BuildContext context) {
    return const _PremiumSection(
      icon: Icons.lightbulb_rounded,
      title: 'Latest Trade Idea',
      child: _TradeIdeaCard(),
    );
  }
}

class _TradeIdeaCard extends StatelessWidget {
  const _TradeIdeaCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 142,
      padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 12),
      decoration: BoxDecoration(
        color: const Color(0xFF080B0A),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: TradeXColors.borderStrong.withOpacity(0.72)),
      ),
      child: Row(
        children: <Widget>[
          Expanded(
            flex: 34,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    const Flexible(
                      child: Text(
                        'XAUUSD',
                        maxLines: 1,
                        overflow: TextOverflow.fade,
                        style: TextStyle(
                          color: TradeXColors.primaryText,
                          fontSize: 19,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    const SizedBox(width: 7),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(
                        color: TradeXColors.goldDeep.withOpacity(0.70),
                        borderRadius: BorderRadius.circular(7),
                      ),
                      child: const Text(
                        'GOLD',
                        style: TextStyle(
                          color: TradeXColors.goldBright,
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 7),
                const FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text(
                    '2342.50',
                    style: TextStyle(
                      color: TradeXColors.success,
                      fontSize: 29,
                      fontWeight: FontWeight.w600,
                      letterSpacing: -0.6,
                    ),
                  ),
                ),
                const SizedBox(height: 3),
                const FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text(
                    '▲ +12.80 (+0.55%)',
                    style: TextStyle(
                      color: TradeXColors.success,
                      fontSize: 12.5,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ),
              ],
            ),
          ),
          Container(
            width: 1,
            margin: const EdgeInsets.symmetric(horizontal: 10),
            color: TradeXColors.border,
          ),
          const Expanded(
            flex: 37,
            child: _TradeMetrics(),
          ),
          Container(
            width: 1,
            margin: const EdgeInsets.symmetric(horizontal: 8),
            color: TradeXColors.border,
          ),
          const Expanded(
            flex: 29,
            child: CustomPaint(
              painter: _MiniCandlestickPainter(),
              child: SizedBox.expand(),
            ),
          ),
        ],
      ),
    );
  }
}

class _TradeMetrics extends StatelessWidget {
  const _TradeMetrics();

  @override
  Widget build(BuildContext context) {
    return const Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: <Widget>[
        _MetricRow(label: 'Direction', value: 'BUY', valueColor: TradeXColors.success),
        SizedBox(height: 5),
        _MetricRow(label: 'Entry', value: '2340 - 2345'),
        SizedBox(height: 5),
        _MetricRow(label: 'TP', value: '2360 / 2375'),
        SizedBox(height: 5),
        _MetricRow(label: 'SL', value: '2320'),
        SizedBox(height: 5),
        _MetricRow(label: 'Timeframe', value: 'H1'),
      ],
    );
  }
}

class _MetricRow extends StatelessWidget {
  const _MetricRow({required this.label, required this.value, this.valueColor});

  final String label;
  final String value;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: <Widget>[
        Expanded(
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: TradeXColors.secondaryText,
              fontSize: 11,
            ),
          ),
        ),
        const SizedBox(width: 5),
        Flexible(
          child: Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.right,
            style: TextStyle(
              color: valueColor ?? TradeXColors.primaryText,
              fontSize: 11.2,
              fontWeight: FontWeight.w500,
            ),
          ),
        ),
      ],
    );
  }
}

class _MarketCategories extends StatelessWidget {
  const _MarketCategories();

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        const Row(
          children: <Widget>[
            Icon(Icons.bar_chart_rounded, color: TradeXColors.goldBright, size: 26),
            SizedBox(width: 9),
            Text(
              'Market Categories',
              style: TextStyle(
                color: TradeXColors.primaryText,
                fontSize: 19,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
        const SizedBox(height: 11),
        Row(
          children: const <Widget>[
            Expanded(
              child: _CategoryCard(
                icon: Icons.currency_exchange_rounded,
                title: 'Forex',
              ),
            ),
            SizedBox(width: 8),
            Expanded(
              child: _CategoryCard(
                icon: Icons.currency_bitcoin_rounded,
                title: 'Crypto',
              ),
            ),
            SizedBox(width: 8),
            Expanded(
              child: _CategoryCard(
                icon: Icons.stacked_line_chart_rounded,
                title: 'Stocks',
              ),
            ),
            SizedBox(width: 8),
            Expanded(
              child: _CategoryCard(
                icon: Icons.show_chart_rounded,
                title: 'Deriv',
                accent: TradeXColors.error,
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _CategoryCard extends StatelessWidget {
  const _CategoryCard({
    required this.icon,
    required this.title,
    this.accent = TradeXColors.goldBright,
  });

  final IconData icon;
  final String title;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 128,
      padding: const EdgeInsets.fromLTRB(9, 12, 7, 9),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: <Color>[
            Color(0xFF121715),
            Color(0xFF0B0E0D),
          ],
        ),
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: TradeXColors.borderStrong.withOpacity(0.78)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Icon(icon, color: accent, size: 31),
          const Spacer(),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              title,
              style: const TextStyle(
                color: TradeXColors.primaryText,
                fontSize: 14.5,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          const SizedBox(height: 3),
          Row(
            children: <Widget>[
              const Expanded(
                child: Text(
                  'Trade Ideas',
                  maxLines: 1,
                  style: TextStyle(
                    color: TradeXColors.secondaryText,
                    fontSize: 10,
                  ),
                ),
              ),
              Container(
                width: 25,
                height: 25,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: TradeXColors.surfaceMuted,
                  border: Border.all(color: TradeXColors.borderStrong),
                ),
                child: const Icon(
                  Icons.chevron_right_rounded,
                  color: TradeXColors.primaryText,
                  size: 18,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _LatestFromTradeXSection extends StatelessWidget {
  const _LatestFromTradeXSection();

  @override
  Widget build(BuildContext context) {
    return const _PremiumSection(
      icon: Icons.article_rounded,
      title: 'Latest From TradeX',
      child: _LatestVideoCard(),
    );
  }
}

class _LatestVideoCard extends StatelessWidget {
  const _LatestVideoCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: const Color(0xFF080B0A),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: TradeXColors.borderStrong.withOpacity(0.72)),
      ),
      child: Row(
        children: <Widget>[
          const SizedBox(
            width: 142,
            height: 88,
            child: _VideoThumbnail(),
          ),
          const SizedBox(width: 13),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  'Market Outlook: Gold & USD',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: TradeXColors.primaryText,
                    fontSize: 15.5,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                SizedBox(height: 5),
                Text(
                  'Key levels and setups to watch this week.',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: TradeXColors.secondaryText,
                    fontSize: 12.5,
                    height: 1.35,
                  ),
                ),
                SizedBox(height: 9),
                Row(
                  children: <Widget>[
                    Icon(
                      Icons.schedule_rounded,
                      color: TradeXColors.secondaryText,
                      size: 18,
                    ),
                    SizedBox(width: 5),
                    Text(
                      '24:35',
                      style: TextStyle(
                        color: TradeXColors.secondaryText,
                        fontSize: 12,
                      ),
                    ),
                    SizedBox(width: 15),
                    Icon(
                      Icons.visibility_outlined,
                      color: TradeXColors.secondaryText,
                      size: 18,
                    ),
                    SizedBox(width: 5),
                    Flexible(
                      child: Text(
                        '12.4K views',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: TradeXColors.secondaryText,
                          fontSize: 12,
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _VideoThumbnail extends StatelessWidget {
  const _VideoThumbnail();

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(11),
      child: Stack(
        fit: StackFit.expand,
        children: <Widget>[
          const ColoredBox(color: Color(0xFF071018)),
          const CustomPaint(painter: _VideoChartPainter()),
          Center(
            child: Container(
              width: 43,
              height: 43,
              decoration: BoxDecoration(
                color: Colors.black.withOpacity(0.66),
                shape: BoxShape.circle,
                border: Border.all(color: Colors.white, width: 1.5),
              ),
              child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 29),
            ),
          ),
        ],
      ),
    );
  }
}

class _HeroGridPainter extends CustomPainter {
  const _HeroGridPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final Paint grid = Paint()
      ..color = TradeXColors.gold.withOpacity(0.08)
      ..strokeWidth = 0.7;

    for (double x = 0; x <= size.width; x += 28) {
      canvas.drawLine(Offset(x, 0), Offset(x, size.height), grid);
    }
    for (double y = 0; y <= size.height; y += 24) {
      canvas.drawLine(Offset(0, y), Offset(size.width, y), grid);
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _HeroMarketArtPainter extends CustomPainter {
  const _HeroMarketArtPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final Paint chartPaint = Paint()
      ..color = TradeXColors.gold.withOpacity(0.52)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.5;

    final Path chart = Path()
      ..moveTo(0, size.height * 0.66)
      ..lineTo(size.width * 0.12, size.height * 0.61)
      ..lineTo(size.width * 0.20, size.height * 0.64)
      ..lineTo(size.width * 0.31, size.height * 0.49)
      ..lineTo(size.width * 0.42, size.height * 0.53)
      ..lineTo(size.width * 0.53, size.height * 0.35)
      ..lineTo(size.width * 0.63, size.height * 0.39)
      ..lineTo(size.width * 0.78, size.height * 0.18)
      ..lineTo(size.width, size.height * 0.12);
    canvas.drawPath(chart, chartPaint);

    final Paint glow = Paint()
      ..shader = RadialGradient(
        colors: <Color>[
          TradeXColors.goldBright.withOpacity(0.50),
          Colors.transparent,
        ],
      ).createShader(
        Rect.fromCircle(
          center: Offset(size.width * 0.56, size.height * 0.53),
          radius: size.width * 0.36,
        ),
      );
    canvas.drawCircle(
      Offset(size.width * 0.56, size.height * 0.53),
      size.width * 0.35,
      glow,
    );

    final Paint bull = Paint()
      ..color = const Color(0xFFB88722)
      ..style = PaintingStyle.fill;
    final Paint bullStroke = Paint()
      ..color = TradeXColors.goldBright.withOpacity(0.92)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 3;

    final Rect head = Rect.fromCenter(
      center: Offset(size.width * 0.57, size.height * 0.58),
      width: size.width * 0.30,
      height: size.height * 0.42,
    );
    canvas.drawOval(head, bull);

    final Path leftHorn = Path()
      ..moveTo(size.width * 0.49, size.height * 0.43)
      ..quadraticBezierTo(
        size.width * 0.35,
        size.height * 0.21,
        size.width * 0.18,
        size.height * 0.26,
      );
    final Path rightHorn = Path()
      ..moveTo(size.width * 0.65, size.height * 0.43)
      ..quadraticBezierTo(
        size.width * 0.79,
        size.height * 0.20,
        size.width * 0.93,
        size.height * 0.18,
      );
    canvas.drawPath(leftHorn, bullStroke);
    canvas.drawPath(rightHorn, bullStroke);

    final Paint bear = Paint()..color = const Color(0xFF182323);
    canvas.drawOval(
      Rect.fromCenter(
        center: Offset(size.width * 0.86, size.height * 0.58),
        width: size.width * 0.29,
        height: size.height * 0.43,
      ),
      bear,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _CourseChartPainter extends CustomPainter {
  const _CourseChartPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final Paint grid = Paint()
      ..color = const Color(0xFF1B5371).withOpacity(0.34)
      ..strokeWidth = 0.6;

    for (double x = 0; x < size.width; x += 18) {
      canvas.drawLine(Offset(x, 0), Offset(x, size.height), grid);
    }
    for (double y = 0; y < size.height; y += 15) {
      canvas.drawLine(Offset(0, y), Offset(size.width, y), grid);
    }

    final Paint line = Paint()
      ..color = const Color(0xFF00B8FF)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2;
    final Path path = Path()
      ..moveTo(0, size.height * 0.80)
      ..lineTo(size.width * 0.12, size.height * 0.65)
      ..lineTo(size.width * 0.23, size.height * 0.71)
      ..lineTo(size.width * 0.34, size.height * 0.47)
      ..lineTo(size.width * 0.45, size.height * 0.53)
      ..lineTo(size.width * 0.55, size.height * 0.31)
      ..lineTo(size.width * 0.67, size.height * 0.40)
      ..lineTo(size.width * 0.78, size.height * 0.23)
      ..lineTo(size.width, size.height * 0.14);
    canvas.drawPath(path, line);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _VideoChartPainter extends CustomPainter {
  const _VideoChartPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final Paint grid = Paint()
      ..color = const Color(0xFF3A6681).withOpacity(0.24)
      ..strokeWidth = 0.7;

    for (double x = 0; x <= size.width; x += 17) {
      canvas.drawLine(Offset(x, 0), Offset(x, size.height), grid);
    }
    for (double y = 0; y <= size.height; y += 14) {
      canvas.drawLine(Offset(0, y), Offset(size.width, y), grid);
    }

    final Paint orange = Paint()
      ..color = const Color(0xFFFF9F43)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.4;
    final Paint cyan = Paint()
      ..color = const Color(0xFF00C8FF)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.4;

    final Path p1 = Path()
      ..moveTo(0, size.height * 0.66)
      ..lineTo(size.width * 0.2, size.height * 0.42)
      ..lineTo(size.width * 0.38, size.height * 0.55)
      ..lineTo(size.width * 0.58, size.height * 0.30)
      ..lineTo(size.width * 0.78, size.height * 0.44)
      ..lineTo(size.width, size.height * 0.18);
    final Path p2 = Path()
      ..moveTo(0, size.height * 0.78)
      ..lineTo(size.width * 0.22, size.height * 0.70)
      ..lineTo(size.width * 0.42, size.height * 0.47)
      ..lineTo(size.width * 0.62, size.height * 0.52)
      ..lineTo(size.width * 0.82, size.height * 0.34)
      ..lineTo(size.width, size.height * 0.39);
    canvas.drawPath(p1, orange);
    canvas.drawPath(p2, cyan);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _MiniCandlestickPainter extends CustomPainter {
  const _MiniCandlestickPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final List<double> values = <double>[
      0.72,
      0.62,
      0.66,
      0.56,
      0.61,
      0.50,
      0.43,
      0.47,
      0.37,
      0.31,
      0.35,
      0.24,
      0.18,
    ];

    final double candleWidth = math.max(3, size.width / (values.length * 2.4));
    final double step = size.width / values.length;

    for (int i = 0; i < values.length; i++) {
      final bool up = i == 0 || values[i] <= values[i - 1];
      final Color color = up ? TradeXColors.success : const Color(0xFFFF4D4D);
      final double x = (i * step) + step * 0.45;
      final double y = size.height * values[i];
      final double bodyHeight = size.height * (0.08 + ((i % 3) * 0.012));

      final Paint wick = Paint()
        ..color = color
        ..strokeWidth = 1.1;
      canvas.drawLine(
        Offset(x, y - bodyHeight * 0.7),
        Offset(x, y + bodyHeight * 1.6),
        wick,
      );

      final Paint body = Paint()..color = color;
      canvas.drawRRect(
        RRect.fromRectAndRadius(
          Rect.fromCenter(
            center: Offset(x, y + bodyHeight * 0.45),
            width: candleWidth,
            height: bodyHeight,
          ),
          const Radius.circular(1.3),
        ),
        body,
      );
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
