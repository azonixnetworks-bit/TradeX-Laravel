<?php

return [
    'storage_disk' => 'private',

    'deposit' => [
        'asset' => 'USDT',
        'network' => 'TRC20',
        'txid_pattern' => '/^[A-Fa-f0-9]{64}$/',
        'tron_address_pattern' => '/^T[1-9A-HJ-NP-Za-km-z]{33}$/',
    ],

    'commercial' => [
        'customer_profit_share_bps' => 8000,
        'company_profit_share_bps' => 2000,
        'early_exit_return_bps' => 8000,
    ],

    'risk' => [
        'version' => 'CRYPTO-RISK-v1',
        'title' => 'Crypto Investment Risk Disclosure',
        'statements' => [
            'Crypto asset prices are volatile and may rise or fall rapidly.',
            'An underlying coin or token may lose substantial value, become illiquid, be delisted, fail, or become worthless.',
            'Investment capital can decrease and partial or total capital loss is possible.',
            'USDT and other stablecoins carry de-peg, issuer, counterparty, restriction, freeze, liquidity and operational risks.',
            'TRON/TRC20 transactions can be affected by network congestion, technical failure, cyberattack, wallet error or custody risk.',
            'Regulatory or legal changes may restrict crypto ownership, transfers, trading or investment activity.',
            'Past performance does not guarantee future results.',
            'TradeX does not promise a fixed or guaranteed annual return under the performance-sharing model.',
            'Early termination does not guarantee repayment of the original USDT amount. Subject to the signed agreement and applicable law, the agreed in-kind return percentage applies to attributable underlying spot-crypto units.',
            'Blockchain/network transfer fees and market-price changes can reduce the value received by the customer.',
        ],
    ],

    'agreement' => [
        'template_version' => 'CRYPTO-PERFORMANCE-SHARE-v1',
        'template_status' => env(
            'TRADEX_INVESTMENT_AGREEMENT_TEMPLATE_STATUS',
            'DRAFT_REQUIRES_LEGAL_REVIEW'
        ),
        'company_legal_name' => env(
            'TRADEX_INVESTMENT_COMPANY_LEGAL_NAME',
            'TradeX'
        ),
        'max_signed_document_kb' => 10240,
        'production_activation_requires_approved_template' => env(
            'TRADEX_INVESTMENT_REQUIRE_APPROVED_TEMPLATE_IN_PRODUCTION',
            env('APP_ENV') === 'production'
        ),
        'approved_template_status' => 'APPROVED',
    ],
];
