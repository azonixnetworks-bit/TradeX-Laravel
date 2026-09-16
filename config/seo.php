<?php

return [
    'brand' => [
        'name' => 'TradeX',
        'tagline' => 'The OS for Every Trader',
        'description' => 'TradeX is a trader workflow and market intelligence platform for planning, risk discipline, economic news awareness, education, and market analysis.',
        'locale' => 'en_US',
        'language' => 'en',
    ],

    'verification' => [
        'google' => env('SEO_GOOGLE_SITE_VERIFICATION'),
        'bing' => env('SEO_BING_SITE_VERIFICATION'),
    ],

    'crawlers' => [
        'allow_ai_search' => (bool) env('SEO_ALLOW_AI_SEARCH', true),
        'allow_model_training' => (bool) env('SEO_ALLOW_MODEL_TRAINING', false),
    ],

    'pages' => [
        'home' => [
            'path' => '/',
            'title' => 'TradeX Trading Platform | Market Intelligence & Trader Tools',
            'description' => 'TradeX brings trading tools, economic news intelligence, risk planning, education, and daily trader workflows into one focused platform.',
            'eyebrow' => 'The OS for Every Trader',
            'h1' => 'Plan better. Read the market clearly. Trade with discipline.',
            'intro' => 'TradeX combines practical trader workflows with market intelligence so traders can prepare, manage risk, follow important economic events, and build a repeatable routine from one place.',
            'sections' => [
                [
                    'heading' => 'Market intelligence without the noise',
                    'body' => 'Organize high-impact and medium-impact economic events, affected markets, directional bias, confidence, volatility context, and analyst reasoning in a structured workflow.',
                    'items' => ['Economic calendar', 'High-impact and medium-impact news', 'Affected-pair mapping', 'Bullish, bearish, and neutral market bias'],
                ],
                [
                    'heading' => 'Risk and routine tools built for real trading days',
                    'body' => 'Use dedicated planning tools to turn risk limits and daily preparation into consistent habits before a trade is considered.',
                    'items' => ['Risk-reward calculator', 'Daily trading routine planner', 'Funded-account planning', 'Trade idea workflow'],
                ],
                [
                    'heading' => 'One platform for learning and decision support',
                    'body' => 'TradeX connects education, analysis, notifications, and account workflows without presenting trading outcomes as guaranteed.',
                    'items' => ['Trading education', 'Market screeners', 'Trade ideas', 'Notifications and account tools'],
                ],
            ],
            'faq' => [
                ['question' => 'What is TradeX?', 'answer' => 'TradeX is a trader workflow and market intelligence platform designed to bring planning, education, risk tools, economic news awareness, and analysis into one organized experience.'],
                ['question' => 'Does TradeX guarantee trading profits?', 'answer' => 'No. TradeX provides education, planning, analysis, and decision-support tools. Markets involve risk and no outcome is guaranteed.'],
                ['question' => 'Which markets can TradeX workflows support?', 'answer' => 'TradeX is designed around workflows used across forex, crypto, stocks, commodities, and other supported market instruments. Availability can vary by feature and data provider.'],
                ['question' => 'Does TradeX include economic news intelligence?', 'answer' => 'TradeX includes an economic calendar workflow with impact classification, affected-pair mapping, directional bias, confidence, volatility context, and analyst reasoning where configured.'],
            ],
        ],
        'features' => [
            'path' => '/features',
            'title' => 'TradeX Features | Trading Tools, News Intelligence & Education',
            'description' => 'Explore TradeX features for economic news intelligence, risk planning, trading routines, education, screeners, trade ideas, and account workflows.',
            'eyebrow' => 'TradeX Features',
            'h1' => 'A connected toolkit for the complete trader workflow.',
            'intro' => 'TradeX is structured around the tasks traders repeat every day: prepare, understand events, define risk, study the market, review ideas, and stay consistent.',
            'sections' => [
                ['heading' => 'Economic News Intelligence', 'body' => 'Track economic events by impact and connect each event to affected markets, directional bias, confidence, volatility expectations, and written analysis.', 'items' => ['High-impact news', 'Medium-impact news', 'Live update workflow', 'Economic calendar']],
                ['heading' => 'Trading Risk & Planning', 'body' => 'Make position-planning and routine decisions from explicit risk inputs rather than impulse.', 'items' => ['Risk-reward calculator', 'Routine planner', 'Funded-account advisor', 'Structured trade ideas']],
                ['heading' => 'Education & Market Monitoring', 'body' => 'Keep learning and market monitoring close to the same platform used for preparation.', 'items' => ['Trading education', 'Forex and crypto screening workflows', 'Market clock', 'Notifications']],
            ],
            'faq' => [
                ['question' => 'Is TradeX a broker?', 'answer' => 'TradeX is presented as a trading workflow, education, and market-intelligence platform. Individual execution or payment integrations are separate features and do not change the risk of trading.'],
                ['question' => 'Can TradeX replace a trading plan?', 'answer' => 'TradeX can help structure a plan, but users remain responsible for their strategy, risk limits, and trading decisions.'],
            ],
        ],
        'trading-tools' => [
            'path' => '/trading-tools',
            'title' => 'Trading Tools | Risk Reward, Routine Planner & Funded Account Advisor | TradeX',
            'description' => 'TradeX trading tools help traders plan risk, structure daily routines, review funded-account constraints, and organize trade preparation.',
            'eyebrow' => 'Trading Tools',
            'h1' => 'Turn trading preparation into a repeatable system.',
            'intro' => 'TradeX planning tools are designed to make risk and routine visible before market pressure starts influencing decisions.',
            'sections' => [
                ['heading' => 'Risk-Reward Calculator', 'body' => 'Model trade risk and reward using explicit inputs before acting. The calculator supports planning; it does not place or modify trades.', 'items' => ['Risk-first calculations', 'Market-specific reference profiles', 'Clear reward-to-risk output']],
                ['heading' => 'Daily Trading Routine Planner', 'body' => 'Create a repeatable preparation routine around timezone-aware planning, review, and personal process discipline.', 'items' => ['Daily preparation', 'Routine consistency', 'Timezone-aware workflow']],
                ['heading' => 'Funded Account Advisor', 'body' => 'Organize risk planning around funded-account constraints without hardcoding changing third-party challenge rules as permanent facts.', 'items' => ['Risk-plan snapshots', 'Firm-neutral planning', 'Downloadable planning reports']],
            ],
            'faq' => [
                ['question' => 'Do TradeX trading tools execute trades?', 'answer' => 'The planning tools described on this page are decision-support utilities and do not themselves place, modify, or transmit live trades.'],
                ['question' => 'Can I use the tools for different markets?', 'answer' => 'The tools are designed for multiple supported market types, with exact inputs and reference profiles depending on the selected instrument.'],
            ],
        ],
        'economic-calendar' => [
            'path' => '/economic-calendar',
            'title' => 'Economic Calendar & High-Impact Forex News | TradeX',
            'description' => 'Use TradeX economic news intelligence to organize high-impact events, affected currency pairs, market bias, confidence, volatility, and analysis.',
            'eyebrow' => 'Economic News Intelligence',
            'h1' => 'Know which economic events matter before volatility arrives.',
            'intro' => 'TradeX turns economic calendar events into a structured intelligence view so traders can see impact, affected markets, directional context, confidence, volatility, and reasoning together.',
            'sections' => [
                ['heading' => 'High-impact and medium-impact events', 'body' => 'Separate major market-moving events from medium-impact releases so attention can be prioritized around the trading session.', 'items' => ['Impact classification', 'Event schedule', 'Currency and market context']],
                ['heading' => 'Affected-pair mapping', 'body' => 'Connect an economic event to the instruments most likely to be relevant, reducing the need to manually interpret every calendar item in isolation.', 'items' => ['Affected instruments', 'Pair-level context', 'Event-to-market mapping']],
                ['heading' => 'Bias, confidence, volatility, and reasoning', 'body' => 'Directional labels are paired with confidence and written reasoning so a bullish, bearish, or neutral view is not presented without context.', 'items' => ['Bullish / bearish / neutral', 'Confidence score', 'Volatility context', 'Reason and analysis']],
            ],
            'faq' => [
                ['question' => 'Does an economic-event bias guarantee price direction?', 'answer' => 'No. Economic releases can produce unexpected reactions. Bias and confidence are analytical context, not a guarantee of market direction or profit.'],
                ['question' => 'Why map events to currency pairs?', 'answer' => 'Affected-pair mapping helps traders focus on instruments with a clearer relationship to the event instead of treating every market as equally exposed.'],
            ],
        ],
        'market-news' => [
            'path' => '/market-news',
            'title' => 'Forex Market News Intelligence & Pair Direction | TradeX',
            'description' => 'TradeX market news intelligence organizes event impact, affected pairs, directional bias, confidence, volatility, and analyst reasoning for traders.',
            'eyebrow' => 'Market News',
            'h1' => 'Market direction needs context, not a one-word signal.',
            'intro' => 'TradeX structures news intelligence around the event, the affected instrument, the current directional view, confidence, volatility expectations, and the reasoning behind the analysis.',
            'sections' => [
                ['heading' => 'Direction with explanation', 'body' => 'A bullish, bearish, or neutral label is useful only when traders can inspect the reasoning and uncertainty behind it.', 'items' => ['Directional bias', 'Confidence score', 'Analyst reasoning']],
                ['heading' => 'Volatility-aware planning', 'body' => 'Expected volatility is surfaced alongside the event so traders can adjust attention and risk planning around changing market conditions.', 'items' => ['Volatility context', 'Event severity', 'Pair exposure']],
                ['heading' => 'Admin-reviewed intelligence', 'body' => 'TradeX supports controlled manual review and override workflows so automated or provider-fed information can be corrected when authorized operators have better verified context.', 'items' => ['Manual admin override', 'Audited changes', 'Source management']],
            ],
            'faq' => [
                ['question' => 'Is TradeX market direction financial advice?', 'answer' => 'No. Directional analysis is decision-support information. Users should independently assess market conditions and risk before making trading decisions.'],
                ['question' => 'Can market bias change after new information?', 'answer' => 'Yes. Market context can change quickly, particularly around economic releases and unexpected news.'],
            ],
        ],
        'education' => [
            'path' => '/trading-education',
            'title' => 'Trading Education & Learning Tools | TradeX',
            'description' => 'TradeX connects structured trading education with practical planning, market analysis, and repeatable trader workflows.',
            'eyebrow' => 'Trading Education',
            'h1' => 'Learn concepts in the same ecosystem where you apply the process.',
            'intro' => 'TradeX education is designed to connect learning with preparation, risk awareness, and repeatable trading routines rather than separating theory from daily process.',
            'sections' => [
                ['heading' => 'Structured learning', 'body' => 'Organized learning materials support progression from foundational concepts toward practical trading workflows.', 'items' => ['Video learning', 'Assignments', 'Progress-oriented content']],
                ['heading' => 'Learning connected to tools', 'body' => 'Risk, market timing, economic events, and routines can be reinforced through the tools traders use during preparation.', 'items' => ['Risk practice', 'Economic-event awareness', 'Routine discipline']],
                ['heading' => 'Education without outcome promises', 'body' => 'Educational material can improve understanding and process, but it cannot remove market risk or guarantee profitable outcomes.', 'items' => ['Risk-aware messaging', 'Process over promises', 'Independent decision-making']],
            ],
            'faq' => [
                ['question' => 'Does completing trading education guarantee profitability?', 'answer' => 'No. Education can improve knowledge and process, but trading remains risky and results depend on many factors outside any course or platform.'],
            ],
        ],
        'about' => [
            'path' => '/about',
            'title' => 'About TradeX | The OS for Every Trader',
            'description' => 'Learn what TradeX is building: one connected platform for trader education, planning, risk discipline, market intelligence, and daily workflows.',
            'eyebrow' => 'About TradeX',
            'h1' => 'Building one operating system for the trader workflow.',
            'intro' => 'TradeX is built around a simple idea: traders should not need a disconnected collection of tools to prepare, learn, manage risk, understand events, and maintain discipline.',
            'sections' => [
                ['heading' => 'Our product direction', 'body' => 'Bring education, planning, market intelligence, risk utilities, and trader operations into a consistent experience that can be shared across web and mobile APIs.', 'items' => ['Unified workflows', 'Mobile-ready APIs', 'Consistent market context']],
                ['heading' => 'Responsible product principles', 'body' => 'TradeX separates analysis from guarantees, keeps important administrative actions permission-gated, and treats security and auditability as product requirements.', 'items' => ['No guaranteed outcomes', 'Role-based administration', 'Auditable operations']],
                ['heading' => 'The OS for Every Trader', 'body' => 'The TradeX vision is to make the recurring work around trading easier to organize without replacing the trader’s own judgment and responsibility.', 'items' => ['Plan', 'Learn', 'Analyze', 'Review']],
            ],
            'faq' => [],
        ],
    ],
];
