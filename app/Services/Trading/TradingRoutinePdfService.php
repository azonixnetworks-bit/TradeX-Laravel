<?php

namespace App\Services\Trading;

use App\Models\TradingRoutineDailyPlan;

class TradingRoutinePdfService
{
    public function render(TradingRoutineDailyPlan $plan): string
    {
        $data = (array) $plan->plan_json;

        return $this->buildPdf(
            $this->pageOne($plan, $data),
            $this->pageTwo($plan, $data)
        );
    }

    /** @param array<string, mixed> $data */
    private function pageOne(TradingRoutineDailyPlan $plan, array $data): string
    {
        $ops = $this->header('DAILY TRADING ROUTINE', 'Capital protection and execution discipline');
        $y = 718.0;

        $this->section($ops, $y, 'TODAY\'S PLAN');
        $this->line($ops, $y, 'Customer', $plan->customer_name_snapshot);
        $this->line($ops, $y, 'Plan Date', $plan->plan_date?->format('Y-m-d') ?? '—');
        $this->line($ops, $y, 'Timezone', $plan->timezone_snapshot);
        $this->line($ops, $y, 'Markets', implode(', ', (array) ($data['markets'] ?? [])) ?: 'Not set');
        $this->line($ops, $y, 'Sessions', implode(', ', (array) ($data['sessions'] ?? [])) ?: 'Not set');

        $y -= 8;
        $this->section($ops, $y, 'RISK LIMITS');
        $this->line($ops, $y, 'Planning Balance', $this->money($data['planning_balance'] ?? 0));
        $this->line($ops, $y, 'Risk / Trade', $this->money($data['risk_per_trade_amount'] ?? 0).' ('.number_format((float) ($data['risk_per_trade_percent'] ?? 0), 2).'%)');
        $this->line($ops, $y, 'Daily Risk Cap', $this->money($data['daily_risk_cap_amount'] ?? 0).' ('.number_format((float) ($data['daily_risk_cap_percent'] ?? 0), 2).'%)');
        $this->line($ops, $y, 'Maximum Trades', (string) ($data['max_trades'] ?? 0));
        $this->line($ops, $y, 'Stop After Consecutive Losses', (string) ($data['max_consecutive_losses'] ?? 0));
        $this->line($ops, $y, 'Minimum R:R', '1:'.number_format((float) ($data['minimum_rr'] ?? 0), 2));
        $this->line($ops, $y, 'Target / Planned Winner', $this->money($data['target_per_winning_trade'] ?? 0));

        $y -= 8;
        $this->section($ops, $y, 'EXECUTION ROUTINE');
        foreach ((array) ($data['routine_steps'] ?? []) as $index => $step) {
            $this->paragraph($ops, $y, ((int) $index + 1).'. '.(string) $step, 86);
            $y -= 3;
        }

        $warnings = (array) ($data['warnings'] ?? []);
        if ($warnings !== []) {
            $y -= 3;
            $this->section($ops, $y, 'PLAN ADJUSTMENT');
            foreach ($warnings as $warning) {
                $this->paragraph($ops, $y, '- '.(string) $warning, 86, '0.56 0.34 0.06');
            }
        }

        $this->footer($ops, $plan);

        return implode("\n", $ops);
    }

    /** @param array<string, mixed> $data */
    private function pageTwo(TradingRoutineDailyPlan $plan, array $data): string
    {
        $ops = $this->header('CHECKLIST & DISCIPLINE', 'Complete before risking capital');
        $y = 718.0;

        $this->section($ops, $y, 'PRE-MARKET CHECKLIST');
        foreach (array_slice((array) ($data['pre_market_checklist'] ?? []), 0, 10) as $item) {
            $this->paragraph($ops, $y, '[ ] '.(string) $item, 86);
            $y -= 3;
        }

        $y -= 7;
        $this->section($ops, $y, 'NO-TRADE / STOP RULES');
        foreach (array_slice((array) ($data['no_trade_rules'] ?? []), 0, 10) as $item) {
            $this->paragraph($ops, $y, '- '.(string) $item, 86);
            $y -= 3;
        }

        $y -= 7;
        $this->section($ops, $y, 'TODAY\'S FOCUS');
        $this->paragraph($ops, $y, (string) ($data['focus_statement'] ?? ''), 86, '0.47 0.34 0.04');

        $y -= 12;
        $this->section($ops, $y, 'IMPORTANT');
        $this->paragraph(
            $ops,
            $y,
            (string) ($data['disclaimer'] ?? config('trading_routine.disclaimer')),
            86
        );

        $this->footer($ops, $plan);

        return implode("\n", $ops);
    }

    /** @return array<int, string> */
    private function header(string $title, string $subtitle): array
    {
        return [
            '0.02 0.05 0.09 rg',
            '0 755 595 87 re f',
            '0.86 0.67 0.24 rg',
            '0 750 595 5 re f',
            $this->textCommand(45, 805, 'TRADEX', 22, true, '1 1 1'),
            $this->textCommand(45, 782, $title, 14, true, '0.86 0.67 0.24'),
            $this->textCommand(45, 765, $subtitle, 9, false, '0.82 0.85 0.89'),
        ];
    }

    /** @param array<int, string> $ops */
    private function section(array &$ops, float &$y, string $title): void
    {
        $ops[] = '0.94 0.95 0.96 rg';
        $ops[] = sprintf('42 %.2F 511 25 re f', $y - 6);
        $ops[] = $this->textCommand(49, $y + 1, $title, 10, true, '0.12 0.15 0.19');
        $y -= 30;
    }

    /** @param array<int, string> $ops */
    private function line(array &$ops, float &$y, string $label, string $value): void
    {
        $ops[] = $this->textCommand(48, $y, $label, 9, false, '0.34 0.38 0.43');
        $ops[] = $this->textCommand(235, $y, $value, 10, true, '0.07 0.09 0.12');
        $y -= 18;
    }

    /** @param array<int, string> $ops */
    private function paragraph(
        array &$ops,
        float &$y,
        string $text,
        int $width,
        string $color = '0.20 0.23 0.27'
    ): void {
        foreach ($this->wrap($text, $width) as $line) {
            $ops[] = $this->textCommand(48, $y, $line, 9, false, $color);
            $y -= 13;
        }
    }

    /** @param array<int, string> $ops */
    private function footer(array &$ops, TradingRoutineDailyPlan $plan): void
    {
        $ops[] = '0.86 0.67 0.24 rg';
        $ops[] = '42 40 511 1 re f';
        $ops[] = $this->textCommand(
            48,
            25,
            'Generated by TradeX | Plan '.$plan->uuid,
            7,
            false,
            '0.42 0.45 0.49'
        );
    }

    /** @return array<int, string> */
    private function wrap(string $text, int $width): array
    {
        return explode("\n", wordwrap($this->plain($text), max(20, $width), "\n", true));
    }

    private function money(mixed $value): string
    {
        return '$'.number_format((float) $value, 2, '.', ',');
    }

    private function textCommand(
        float $x,
        float $y,
        string $text,
        int $size,
        bool $bold,
        string $color
    ): string {
        return sprintf(
            'BT %s rg /%s %d Tf %.2F %.2F Td (%s) Tj ET',
            $color,
            $bold ? 'F2' : 'F1',
            $size,
            $x,
            $y,
            $this->pdfEscape($text)
        );
    }

    private function plain(string $value): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        $value = $converted !== false ? $converted : $value;

        return preg_replace('/[^\x20-\x7E\x80-\xFF]/', '?', $value) ?? '';
    }

    private function pdfEscape(string $value): string
    {
        return str_replace(
            ['\\', '(', ')', "\r", "\n"],
            ['\\\\', '\\(', '\\)', ' ', ' '],
            $this->plain($value)
        );
    }

    private function buildPdf(string $pageOne, string $pageTwo): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 7 0 R >>',
            4 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 8 0 R >>',
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            6 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
            7 => '<< /Length '.strlen($pageOne)." >>\nstream\n{$pageOne}\nendstream",
            8 => '<< /Length '.strlen($pageTwo)." >>\nstream\n{$pageTwo}\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 9\n0000000000 65535 f \n";
        for ($number = 1; $number <= 8; $number++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }
        $pdf .= "trailer\n<< /Size 9 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }
}
