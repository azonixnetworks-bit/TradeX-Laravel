<?php

namespace App\Services\Trading;

use App\Models\FundedAdvisorReport;

class FundedAdvisorPdfService
{
    public function render(FundedAdvisorReport $report): string
    {
        $input = (array) $report->inputs_json;
        $output = (array) $report->output_json;

        $pageOne = $this->pageOne($report, $input, $output);
        [$pageTwo, $linkRect] = $this->pageTwo($report, $input, $output);

        return $this->buildPdf(
            $pageOne,
            $pageTwo,
            $report->affiliate_url_snapshot,
            $linkRect
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     */
    private function pageOne(
        FundedAdvisorReport $report,
        array $input,
        array $output
    ): string {
        $ops = $this->header('FUNDED ACCOUNT ADVISOR', 'Personal risk-planning report');
        $y = 725;

        $this->sectionTitle($ops, $y, 'CUSTOMER & ACCOUNT');
        $this->line($ops, $y, 'Customer', $report->customer_name);
        $this->line($ops, $y, 'Prop Firm', $report->prop_firm_name ?: 'Manual / not specified');
        $this->line($ops, $y, 'Phase', (string) ($input['phase'] ?? '—'));
        $this->line($ops, $y, 'Account Size', $this->money($input['account_size'] ?? 0));
        $this->line($ops, $y, 'Current Balance', $this->money($input['current_balance'] ?? 0));
        $this->line($ops, $y, 'Current Equity', $this->money($input['current_equity'] ?? 0));

        $y -= 8;
        $this->sectionTitle($ops, $y, 'TODAY\'S TRADEX PLAN');
        $status = str_replace('_', ' ', (string) ($output['status'] ?? 'UNKNOWN'));
        $this->badge($ops, $y, $status);
        $this->line($ops, $y, 'Recommended Risk / Trade', $this->money($output['recommended_risk_amount'] ?? 0).' ('.number_format((float) ($output['recommended_risk_percent'] ?? 0), 2).'% )');
        $this->line($ops, $y, 'Recommended Entries', (string) ($output['recommended_entries'] ?? 0));
        $this->line($ops, $y, 'Planned Daily Exposure', $this->money($output['planned_daily_exposure'] ?? 0));
        $this->line($ops, $y, 'Minimum Target R:R', '1:'.number_format((float) ($output['minimum_rr'] ?? 0), 2));
        $this->line($ops, $y, 'Target / Winning Trade', $this->money($output['target_profit_per_win'] ?? 0));
        $this->line($ops, $y, 'Daily Stop Reference', $this->money($output['daily_stop_reference'] ?? 0).' ('.$this->plain((string) ($output['daily_loss_basis'] ?? '')).')');

        $y -= 8;
        $this->sectionTitle($ops, $y, 'RULE CAPACITY');
        $this->line($ops, $y, 'Daily Loss Limit', $this->money($output['daily_limit_amount'] ?? 0));
        $this->line($ops, $y, 'Daily Loss Used', $this->money($output['daily_loss_used'] ?? 0).' / '.number_format((float) ($output['daily_loss_used_percent'] ?? 0), 1).'%');
        $this->line($ops, $y, 'Daily Loss Remaining', $this->money($output['daily_loss_remaining'] ?? 0));
        $this->line($ops, $y, 'Drawdown Threshold', $this->money($output['drawdown_threshold'] ?? 0));
        $this->line($ops, $y, 'Overall DD Remaining', $this->money($output['overall_drawdown_remaining'] ?? 0));
        $this->line($ops, $y, 'Loss Capacity @ Base Risk', (string) ($output['losses_before_rule_capacity'] ?? 0).' full-risk losses');

        $y -= 8;
        $this->sectionTitle($ops, $y, 'CHALLENGE PROGRESS');
        $this->line($ops, $y, 'Profit Target', $this->money($output['profit_target_amount'] ?? 0));
        $this->line($ops, $y, 'Remaining Target', $this->money($output['remaining_profit_target'] ?? 0));
        $this->line($ops, $y, 'Target Progress', number_format((float) ($output['target_progress_percent'] ?? 0), 1).'%');
        $wins = $output['estimated_wins_to_target'] ?? null;
        $this->line($ops, $y, 'Estimated Wins @ Planned R:R', $wins === null ? 'Not available' : (string) $wins);
        $this->line($ops, $y, 'Minimum Trading Days Left', (string) ($output['remaining_minimum_trading_days'] ?? 0));

        $this->footer($ops, $report, 26);

        return implode("\n", $ops);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     * @return array{0:string,1:?array<int, float>}
     */
    private function pageTwo(
        FundedAdvisorReport $report,
        array $input,
        array $output
    ): array {
        $ops = $this->header('ENTRY PLAN & PARTNER', 'Execution discipline and referral information');
        $y = 725;

        $this->sectionTitle($ops, $y, 'ENTRY-BY-ENTRY RISK PLAN');
        $entryPlan = (array) ($output['entry_plan'] ?? []);

        if ($entryPlan === []) {
            $this->paragraph($ops, $y, 'No new entry is recommended under the current risk capacity.', 82);
        } else {
            foreach ($entryPlan as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $label = sprintf(
                    'Entry %d  -  Risk %s  -  %.0f%% of base risk',
                    (int) ($entry['entry'] ?? 0),
                    $this->money($entry['risk_amount'] ?? 0),
                    ((float) ($entry['risk_factor'] ?? 0)) * 100
                );
                $this->text($ops, 48, $y, $label, 11, true, '0.86 0.67 0.24');
                $y -= 16;
                $this->paragraph($ops, $y, (string) ($entry['instruction'] ?? ''), 78);
                $y -= 4;
            }
        }

        $warnings = (array) ($output['warnings'] ?? []);
        if ($warnings !== []) {
            $y -= 4;
            $this->sectionTitle($ops, $y, 'RISK WARNINGS');
            foreach ($warnings as $warning) {
                $this->paragraph($ops, $y, '- '.(string) $warning, 82);
            }
        }

        $linkRect = null;
        $y -= 8;
        $this->sectionTitle($ops, $y, 'TRADEX FUNDED ACCOUNT PARTNER');

        if (filled($report->partner_name_snapshot)) {
            $this->line($ops, $y, 'Partner', (string) $report->partner_name_snapshot);
            if (filled($report->partner_offer_snapshot)) {
                $this->line($ops, $y, 'Partner Offer', (string) $report->partner_offer_snapshot);
            }
            if (filled($report->promo_code_snapshot)) {
                $this->line($ops, $y, 'Promo Code', (string) $report->promo_code_snapshot);
            }

            if (filled($report->affiliate_url_snapshot)) {
                $this->text($ops, 48, $y, 'Open Account / Partner Link:', 10, true, '0.86 0.67 0.24');
                $y -= 15;
                $urlY = $y;
                $this->paragraph($ops, $y, (string) $report->affiliate_url_snapshot, 88, '0.15 0.35 0.70');
                $linkRect = [45.0, max(20.0, $urlY - 5.0), 550.0, min(820.0, $urlY + 13.0)];
            }

            $disclosure = $report->affiliate_disclosure_snapshot
                ?: (string) config('funded_account_advisor.affiliate_disclosure');
            $y -= 6;
            $this->paragraph($ops, $y, $disclosure, 84);
        } else {
            $this->paragraph($ops, $y, 'No funded-account partner was attached to this report. The risk plan remains independent of partner selection.', 84);
        }

        $y -= 10;
        $this->sectionTitle($ops, $y, 'IMPORTANT');
        $this->paragraph(
            $ops,
            $y,
            (string) ($output['disclaimer'] ?? config('funded_account_advisor.disclaimer')),
            84
        );

        $this->footer($ops, $report, 26);

        return [implode("\n", $ops), $linkRect];
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
    private function sectionTitle(array &$ops, float &$y, string $title): void
    {
        $y -= 4;
        $ops[] = '0.94 0.95 0.96 rg';
        $ops[] = sprintf('42 %.2F 511 25 re f', $y - 6);
        $this->text($ops, 49, $y + 1, $title, 10, true, '0.12 0.15 0.19');
        $y -= 28;
    }

    /** @param array<int, string> $ops */
    private function line(array &$ops, float &$y, string $label, string $value): void
    {
        $this->text($ops, 48, $y, $label, 9, false, '0.34 0.38 0.43');
        $this->text($ops, 245, $y, $value, 10, true, '0.07 0.09 0.12');
        $y -= 18;
    }

    /** @param array<int, string> $ops */
    private function badge(array &$ops, float &$y, string $status): void
    {
        $ops[] = '0.98 0.95 0.86 rg';
        $ops[] = sprintf('245 %.2F 170 23 re f', $y - 8);
        $this->text($ops, 255, $y, 'STATUS: '.$status, 10, true, '0.47 0.34 0.04');
        $y -= 32;
    }

    /** @param array<int, string> $ops */
    private function paragraph(
        array &$ops,
        float &$y,
        string $text,
        int $width = 82,
        string $color = '0.20 0.23 0.27'
    ): void {
        foreach ($this->wrap($text, $width) as $line) {
            $this->text($ops, 48, $y, $line, 9, false, $color);
            $y -= 13;
        }
    }

    /** @param array<int, string> $ops */
    private function text(
        array &$ops,
        float $x,
        float $y,
        string $text,
        int $size,
        bool $bold,
        string $color
    ): void {
        $ops[] = $this->textCommand($x, $y, $text, $size, $bold, $color);
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

    /** @param array<int, string> $ops */
    private function footer(array &$ops, FundedAdvisorReport $report, float $y): void
    {
        $ops[] = '0.86 0.67 0.24 rg';
        $ops[] = sprintf('42 %.2F 511 1 re f', $y + 14);
        $this->text(
            $ops,
            48,
            $y,
            'Generated by TradeX on '.optional($report->generated_at)->format('Y-m-d H:i').' | Report '.$report->uuid,
            7,
            false,
            '0.42 0.45 0.49'
        );
    }

    /** @return array<int, string> */
    private function wrap(string $text, int $width): array
    {
        $plain = $this->plain($text);
        $wrapped = wordwrap($plain, max(20, $width), "\n", true);

        return explode("\n", $wrapped);
    }

    private function money(mixed $value): string
    {
        return '$'.number_format((float) $value, 2, '.', ',');
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

    /**
     * @param  array<int, float>|null  $linkRect
     */
    private function buildPdf(
        string $pageOneStream,
        string $pageTwoStream,
        ?string $linkUrl,
        ?array $linkRect
    ): string {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>';
        $objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[6] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        $objects[7] = '<< /Length '.strlen($pageOneStream)." >>\nstream\n".$pageOneStream."\nendstream";
        $objects[8] = '<< /Length '.strlen($pageTwoStream)." >>\nstream\n".$pageTwoStream."\nendstream";

        $resources = '<< /Font << /F1 5 0 R /F2 6 0 R >> >>';
        $objects[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources '.$resources.' /Contents 7 0 R >>';

        $annotation = '';
        if (filled($linkUrl) && is_array($linkRect) && count($linkRect) === 4) {
            $objects[9] = sprintf(
                '<< /Type /Annot /Subtype /Link /Rect [%.2F %.2F %.2F %.2F] /Border [0 0 0] /A << /S /URI /URI (%s) >> >>',
                $linkRect[0],
                $linkRect[1],
                $linkRect[2],
                $linkRect[3],
                $this->pdfEscape((string) $linkUrl)
            );
            $annotation = ' /Annots [9 0 R]';
        }

        $objects[4] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources '.$resources.' /Contents 8 0 R'.$annotation.' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%TradeX\n";
        $offsets = [0 => 0];
        $maxObject = max(array_keys($objects));

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$body."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".($maxObject + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($number = 1; $number <= $maxObject; $number++) {
            if (isset($offsets[$number])) {
                $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
            } else {
                $pdf .= "0000000000 00000 f \n";
            }
        }

        $pdf .= "trailer\n<< /Size ".($maxObject + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xrefOffset."\n%%EOF";

        return $pdf;
    }
}
