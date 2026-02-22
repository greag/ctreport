<?php

namespace App\Services;

class TextNormalizer
{
    public function normalize(string $text): string
    {
        $text = str_replace('--- PAGE BREAK ---', "\n--- PAGE BREAK ---\n", $text);
        $lines = preg_split('/\R/', $text) ?: [];
        $out = [];

        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line));
            if ($line === '') {
                continue;
            }
            if ($this->isHeaderDateTimeLine($line)) {
                continue;
            }
            if ($this->isPageNumberLine($line)) {
                continue;
            }
            if (str_contains($line, 'myscore.cibil.com')) {
                continue;
            }
            if (str_contains($line, 'Score Report')) {
                continue;
            }
            if (str_contains($line, 'Cibil Dashboard')) {
                continue;
            }
            $out[] = $this->injectSectionMarkers($line);
        }

        return implode("\n", $out);
    }

    private function injectSectionMarkers(string $line): string
    {
        $markers = [
            'IDENTIFICATION DETAILS',
            'ADDRESS DETAILS',
            'CONTACT DETAILS',
            'EMAIL DETAILS',
            'EMPLOYMENT DETAILS',
            'ALL ACCOUNTS',
            'OPEN ACCOUNTS',
            'CLOSED ACCOUNTS',
            'ACCOUNT DETAILS',
            'PAYMENT STATUS',
            'PAYMENT HISTORY',
            'ENQUIRY DETAILS',
        ];

        foreach ($markers as $marker) {
            $pattern = '/\b' . preg_quote($marker, '/') . '\b/i';
            if (preg_match($pattern, $line)) {
                $line = preg_replace($pattern, "\n{$marker}\n", $line);
            }
        }

        return $line;
    }

    private function isPageNumberLine(string $line): bool
    {
        return (bool) preg_match('/^\d{1,2}\/\d{2}$/', trim($line));
    }

    private function isHeaderDateTimeLine(string $line): bool
    {
        return (bool) preg_match('/^\d{1,2}\/\d{1,2}\/\d{2},\s*\d{1,2}:\d{2}\s*[AP]M$/i', $line);
    }
}
