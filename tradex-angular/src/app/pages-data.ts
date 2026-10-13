export interface PageData {title:string;eyebrow:string;lead:string;blocks:{title:string;text:string}[];}
export const PAGES: Record<string,PageData> = {
  "about": {
    "title": "A more deliberate way to work with markets.",
    "eyebrow": "About TradeX OS",
    "lead": "TradeX OS is built around a simple belief: useful trading tools should reduce noise, support learning and make risk a visible part of the process.",
    "blocks": [
      {
        "title": "Our purpose",
        "text": "Billionaire Trading Institute brings education, market intelligence and practical planning tools into a connected workspace so people can build a process they understand."
      },
      {
        "title": "Our approach",
        "text": "We value clarity over complexity, process over prediction and risk awareness before any reward-focused thinking."
      },
      {
        "title": "What we are not",
        "text": "TradeX OS does not provide personal investment advice, execute trades for you or promise financial outcomes."
      }
    ]
  },
  "platform": {
    "title": "A connected platform for a stronger routine.",
    "eyebrow": "The TradeX platform",
    "lead": "Every TradeX OS feature is designed to support a single practical loop: learn, observe, plan and review.",
    "blocks": [
      {
        "title": "Education workspace",
        "text": "Structured course content helps you progress through concepts in a clear sequence and return to the lessons that matter."
      },
      {
        "title": "Market intelligence",
        "text": "Focused screeners and market views help you explore instruments without a default indicator overload."
      },
      {
        "title": "Planning tools",
        "text": "Use risk and routine tools to think through a setup before taking action and retain context for future review."
      }
    ]
  },
  "education": {
    "title": "All-market education in one place.",
    "eyebrow": "TradeX education",
    "lead": "Build practical knowledge across Forex, Crypto, Stocks and Deriv through structured learning paths that connect education with a repeatable routine.",
    "blocks": [
      {
        "title": "Structured paths",
        "text": "Move through clearly arranged material instead of trying to piece together a process from disconnected sources."
      },
      {
        "title": "Practical context",
        "text": "Learning is paired with the market, planning and reflection views that help put concepts into context."
      },
      {
        "title": "Your pace",
        "text": "Return to material when you need it and build understanding over time."
      }
    ]
  },
  "market-intelligence": {
    "title": "See the market without the noise.",
    "eyebrow": "Market intelligence",
    "lead": "TradeX OS market views are designed to help you focus your research and organise your attention.",
    "blocks": [
      {
        "title": "Focused screeners",
        "text": "Explore supported markets through clear, responsive screeners and organised instrument views."
      },
      {
        "title": "Context first",
        "text": "Keep research, watchlists and the questions you are asking close to the decision process."
      },
      {
        "title": "No performance promises",
        "text": "Market information is educational and analytical in nature. It is not a recommendation, signal or guarantee."
      }
    ]
  },
  "forex": {
    "title": "Global currency markets, viewed with context.",
    "eyebrow": "Forex market",
    "lead": "Explore a TradingView-powered EURUSD chart and use the authenticated TradeX workspace for deeper screening and planning.",
    "blocks": [
      {"title":"Institutional perspective","text":"Follow major currency pairs within a research process built around context, preparation and disciplined review."},
      {"title":"Live chart workspace","text":"The public chart is provided by TradingView. Availability and quote timing depend on the selected venue and instrument."},
      {"title":"Risk-aware planning","text":"Currency markets can move rapidly. Define risk and invalidation before acting on any market observation."}
    ]
  },
  "crypto": {
    "title": "Digital asset markets without the noise.",
    "eyebrow": "Crypto market",
    "lead": "Review a real TradingView BTCUSD chart, then continue research and watchlist work inside TradeX.",
    "blocks": [
      {"title":"Always-on markets","text":"Crypto markets trade continuously and can be highly volatile. Keep research focused and risk visible."},
      {"title":"Real public chart","text":"The chart below uses TradingView and does not contain fabricated prices or simulated performance."},
      {"title":"Research, not a signal","text":"Market information is educational and analytical. It is not a recommendation to buy, sell or hold an asset."}
    ]
  },
  "deriv": {
    "title": "A structured view of synthetic markets.",
    "eyebrow": "Deriv market",
    "lead": "Use the authenticated TradeX workspace to access the supported Deriv screener and market-data service.",
    "blocks": [
      {"title":"Purpose-built data access","text":"Supported Deriv pricing is delivered inside the authenticated platform rather than represented by an unrelated public symbol."},
      {"title":"Clear availability states","text":"If provider data is unavailable, TradeX reports that state instead of creating placeholder prices."},
      {"title":"Plan for volatility","text":"Synthetic instruments carry material risk. Use clear limits and understand the product before taking action."}
    ]
  },
  "stocks": {
    "title": "Global equities in a focused research view.",
    "eyebrow": "Stock market",
    "lead": "Use a TradingView-powered S&P 500 chart for broad context and continue supported equity research inside TradeX.",
    "blocks": [
      {"title":"Market context","text":"Begin with broad market structure before narrowing attention to a sector, region or individual company."},
      {"title":"Source transparency","text":"Public chart data is supplied by TradingView. Instrument coverage and quote delay depend on the exchange."},
      {"title":"Independent analysis","text":"Company and market information should form part of your own research, not be treated as personal advice."}
    ]
  },
  "trade-ideas": {
    "title": "Turn observations into documented ideas.",
    "eyebrow": "Trade ideas",
    "lead": "A good idea is a structured hypothesis—not a promise. Capture context, risk and invalidation before considering execution.",
    "blocks": [
      {"title":"Start with context","text":"Record the market condition and reasoning behind an observation before focusing on a potential outcome."},
      {"title":"Define invalidation","text":"Decide what would prove the idea wrong and make the potential downside visible before taking action."},
      {"title":"Review the process","text":"Revisit the reasoning after the event. A profitable result does not automatically mean the process was sound."}
    ]
  },
  "copy-trading": {
    "title": "Understand the strategy before following it.",
    "eyebrow": "Copy trading",
    "lead": "Copy trading can involve substantial risk. Evaluate the approach, controls and limitations rather than relying on headline performance.",
    "blocks": [
      {"title":"Due diligence first","text":"Understand the instruments, leverage, drawdown profile and decision process before choosing to follow a strategy."},
      {"title":"Control your exposure","text":"Copied activity can still create losses. Use limits appropriate to your own circumstances and risk tolerance."},
      {"title":"No guaranteed outcomes","text":"Past results and displayed statistics cannot guarantee future performance. You remain responsible for your account decisions."}
    ]
  },
  "affiliate": {
    "title": "Share TradeX with clarity and responsibility.",
    "eyebrow": "Affiliate program",
    "lead": "The TradeX affiliate program is intended for accurate, transparent introductions to the platform—never exaggerated outcome claims.",
    "blocks": [
      {"title":"Accurate communication","text":"Affiliates must describe TradeX fairly and must not present education, information or tools as guaranteed financial outcomes."},
      {"title":"Transparent relationships","text":"Where required, clearly disclose the commercial relationship when sharing an affiliate link or promotional material."},
      {"title":"Program access","text":"Current eligibility, terms and available tools are provided through the authenticated TradeX platform."}
    ]
  },
  "tools": {
    "title": "Tools that make risk visible before action.",
    "eyebrow": "Planning tools",
    "lead": "The best time to think about a decision is before you make it. TradeX OS tools are built to support that discipline.",
    "blocks": [
      {
        "title": "Risk planning",
        "text": "Use practical calculators and workflows to define the constraints of a setup before you act."
      },
      {
        "title": "Routine building",
        "text": "Turn learning and research into a sequence that is easier to repeat day after day."
      },
      {
        "title": "Review context",
        "text": "Look beyond a single result and reflect on the reasoning and process behind it."
      }
    ]
  },
  "terms": {
    "title": "Terms of use",
    "eyebrow": "Public policy centre",
    "lead": "These public terms explain the baseline rules for using the TradeX OS website and platform.",
    "blocks": [
      {
        "title": "Educational purpose",
        "text": "TradeX OS, operated by Billionaire Trading Institute (PV00296953), provides educational content, market information and workflow tools. Nothing on this website is personal investment, legal or tax advice."
      },
      {
        "title": "Responsible use",
        "text": "You must use the platform lawfully, keep account access secure and not attempt to disrupt, misuse or reverse engineer the service."
      },
      {
        "title": "No outcome guarantee",
        "text": "Markets involve risk. Past activity, educational examples or tools do not guarantee a result or future performance."
      }
    ]
  },
  "privacy": {
    "title": "Privacy notice",
    "eyebrow": "Public policy centre",
    "lead": "We aim to use only the information needed to operate, secure and improve TradeX OS.",
    "blocks": [
      {
        "title": "Information we process",
        "text": "This can include account, authentication, platform activity and support information necessary to provide and protect the service."
      },
      {
        "title": "Why we use it",
        "text": "Information is used for account access, product operations, fraud prevention, support and legal obligations where applicable."
      },
      {
        "title": "Your choices",
        "text": "Account holders can use the authenticated platform and support channels to manage relevant account information."
      }
    ]
  },
  "risk-disclosure": {
    "title": "Risk disclosure",
    "eyebrow": "Public policy centre",
    "lead": "Before using market information or planning tools, it is important to understand the limits of what a platform can provide.",
    "blocks": [
      {
        "title": "Market risk",
        "text": "Financial markets can move rapidly. A loss may occur and may be substantial depending on the instrument, strategy and risk controls used."
      },
      {
        "title": "Independent decisions",
        "text": "You remain responsible for your own decisions. TradeX OS does not place trades or make decisions on your behalf."
      },
      {
        "title": "Seek appropriate advice",
        "text": "Consider independent professional advice where appropriate for your circumstances before risking capital."
      }
    ]
  }
};
