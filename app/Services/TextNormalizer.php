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
            if ($this->isHeaderFooterLine($line)) {
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
        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{2},\s*\d{1,2}:\d{2}\s*[AP]M$/i', $line)) {
            return true;
        }
        return (bool) preg_match('/^\d{1,2}\/\d{1,2}\/\d{2,4},\s*\d{1,2}:\d{2}(?:\s*[AP]M)?\s*CIBIL Report$/i', $line);
    }

    private function isHeaderFooterLine(string $line): bool
    {
        if ($this->isHeaderDateTimeLine($line) || $this->isPageNumberLine($line)) {
            return true;
        }
        $tokens = [
            'CIBIL Report',
            'Score Report',
            'Cibil Dashboard',
            'CIBIL Score & Report',
            'myscore.cibil.com',
            '/CreditView/',
            'COPYRIGHT',
            'TRANSUNION CIBIL',
            'ALL RIGHTS RESERVED',
            'For more information, please visit',
        ];
        foreach ($tokens as $token) {
            if (stripos($line, $token) !== false) {
                return true;
            }
        }
        return false;
    }
}
