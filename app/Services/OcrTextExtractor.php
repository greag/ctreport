<?php

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class OcrTextExtractor
{
    public function extractText(string $pdfPath, ?string $password = null): array
    {
        $tempDir = storage_path('app/tmp/' . Str::uuid());
        if (!is_dir($tempDir) && !mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
            throw new RuntimeException('Could not create temporary output directory.');
        }

        $engine = strtolower((string) getenv('PDF_TEXT_ENGINE'));
        if ($engine === 'pdfplumber') {
            $text = $this->extractPdfTextWithPdfPlumber($pdfPath);
        } elseif ($engine === 'wsl-bbox') {
            try {
                $text = $this->extractPdfTextWithWslBbox($pdfPath, $password);
            } catch (RuntimeException $e) {
                if ($this->allowWslBboxFallback()) {
                    $this->logBboxFallback($e->getMessage());
                    $text = $this->extractPdfTextWithPdfPlumber($pdfPath);
                    $engine = 'pdfplumber-fallback';
                } else {
                    throw $e;
                }
            }
        } else {
            $text = $this->extractPdfTextWithBbox($pdfPath, $password, $tempDir);
        }
        $this->cleanup($tempDir);

        $this->dumpDebugText('raw', (string) $text, $pdfPath, $engine);

        $text = $this->formatTextForOutput((string) $text);
        $text = trim($text);
        if ($text === '') {
            throw new RuntimeException('This PDF appears to be scanned. OCR is disabled in this deployment.');
        }

        $this->dumpDebugText('formatted', (string) $text, $pdfPath, $engine);

        $pages = preg_split('/\f/', $text) ?: [$text];
        $pages = array_values(array_filter(array_map('trim', $pages), static fn ($page) => $page !== ''));

        $fullText = '';
        foreach ($pages as $page) {
            $fullText .= $page . "\n--- PAGE BREAK ---\n";
        }

        return [
            'fullText' => $fullText,
            'headerText' => $pages[0] ?? '',
        ];
    }

    private function extractPdfTextWithBbox(string $pdfPath, ?string $password, string $tempDir): string
    {
        $pdftotext = $this->resolvePdfToTextPath();
        $bboxFile = $tempDir . DIRECTORY_SEPARATOR . 'document.html';
        $command = [
            $pdftotext,
            '-bbox',
            '-enc',
            'UTF-8',
        ];

        if ($password) {
            $command[] = '-upw';
            $command[] = $password;
        }

        $command[] = $pdfPath;
        $command[] = $bboxFile;

        $process = new Process($command);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            $output = strtolower($process->getErrorOutput() . $process->getOutput());
            if (str_contains($output, 'usage: pdftotext') || str_contains($output, 'unknown option')) {
                return $this->extractPdfTextLayout($pdfPath, $password, $tempDir);
            }
            if (str_contains($output, 'password')) {
                throw new RuntimeException('This PDF is password-protected. Please enter the password and try again.');
            }
            throw new RuntimeException('Failed to extract text from the PDF.');
        }

        if (!is_file($bboxFile)) {
            return '';
        }

        $html = file_get_contents($bboxFile);
        if ($html === false) {
            return '';
        }

        $pages = $this->parseBboxHtml($html);
        $fullText = '';
        foreach ($pages as $pageText) {
            $fullText .= $pageText . "\n--- PAGE BREAK ---\n";
        }

        return $fullText;
    }

    private function extractPdfTextLayout(string $pdfPath, ?string $password, string $tempDir): string
    {
        $pdftotext = $this->resolvePdfToTextPath();
        $textFile = $tempDir . DIRECTORY_SEPARATOR . 'document.txt';
        $command = [
            $pdftotext,
            '-layout',
            '-enc',
            'UTF-8',
        ];

        if ($password) {
            $command[] = '-upw';
            $command[] = $password;
        }

        $command[] = $pdfPath;
        $command[] = $textFile;

        $process = new Process($command);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            $output = strtolower($process->getErrorOutput() . $process->getOutput());
            if (str_contains($output, 'password')) {
                throw new RuntimeException('This PDF is password-protected. Please enter the password and try again.');
            }
            throw new RuntimeException('Failed to extract text from the PDF.');
        }

        return is_file($textFile) ? (string) file_get_contents($textFile) : '';
    }

    private function extractPdfTextWithPdfPlumber(string $pdfPath): string
    {
        $python = getenv('PDFPLUMBER_PYTHON');
        if (!$python) {
            $python = 'python';
        }

        $script = base_path('scripts/pdfplumber_extract.py');
        $process = new Process([$python, $script, $pdfPath]);
        $process->setTimeout(180);
        $process->run();

        if (!$process->isSuccessful()) {
            $output = trim($process->getErrorOutput() . $process->getOutput());
            throw new RuntimeException($output !== '' ? $output : 'Failed to extract text via pdfplumber.');
        }

        return (string) $process->getOutput();
    }

    private function extractPdfTextWithWslBbox(string $pdfPath, ?string $password): string
    {
        $wslPath = $this->toWslPath($pdfPath);
        $parts = [
            'pdftotext',
            '-bbox',
            '-enc',
            'UTF-8',
        ];
        if ($password) {
            $parts[] = '-upw';
            $parts[] = $password;
        }
        $parts[] = $wslPath;
        $parts[] = '-';

        $cmd = $this->buildWslCommand($parts);
        $this->logWslCommand($cmd);
        $wslExe = $this->resolveWslExe();
        $process = new Process([$wslExe, '-u', 'root', '--', 'bash', '-lc', $cmd]);
        $process->setTimeout(180);
        $process->run();

        $rawOutput = (string) $process->getOutput();
        if (!$process->isSuccessful()) {
            $stderr = (string) $process->getErrorOutput();
            $exitCode = $process->getExitCode();
            $output = strtolower($stderr . $rawOutput);
            if (str_contains($output, 'password')) {
                throw new RuntimeException('This PDF is password-protected. Please enter the password and try again.');
            }
            if (str_contains($rawOutput, '<page') || str_contains($rawOutput, '<word')) {
                $this->logWslCommand('WSL bbox returned non-zero exit but HTML was produced; attempting to parse.');
            } else {
                $this->logWslBboxError($exitCode, $stderr, $rawOutput);
                throw new RuntimeException('Failed to extract text from the PDF via WSL bbox.');
            }
        }

        $html = $rawOutput;
        if (trim($html) === '') {
            return '';
        }

        $pages = $this->parseBboxHtml($html);
        $fullText = '';
        foreach ($pages as $pageText) {
            $fullText .= $pageText . "\n--- PAGE BREAK ---\n";
        }

        return $fullText;
    }

    private function resolvePdfToTextPath(): string
    {
        $custom = getenv('PDFTOTEXT_PATH');
        if ($custom && file_exists($custom)) {
            return $custom;
        }

        return 'pdftotext';
    }

    private function resolveTesseractPath(): string
    {
        $custom = getenv('TESSERACT_PATH');
        if ($custom && file_exists($custom)) {
            return $custom;
        }

        $default = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
        if (file_exists($default)) {
            return $default;
        }

        return 'tesseract';
    }

    private function cleanup(string $tempDir): void
    {
        $files = glob($tempDir . DIRECTORY_SEPARATOR . '*') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($tempDir);
    }

    private function toWslPath(string $path): string
    {
        if (preg_match('/^([A-Za-z]):\\\\/', $path, $m)) {
            $drive = strtolower($m[1]);
            $rest = substr($path, 3);
            $rest = str_replace('\\', '/', $rest);
            return "/mnt/{$drive}/{$rest}";
        }
        return str_replace('\\', '/', $path);
    }

    private function buildWslCommand(array $args): string
    {
        $escaped = array_map(function (string $arg): string {
            return "'" . str_replace("'", "'\"'\"'", $arg) . "'";
        }, $args);
        return implode(' ', $escaped);
    }

    private function resolveWslExe(): string
    {
        $custom = getenv('WSL_EXE_PATH');
        if ($custom && file_exists($custom)) {
            return $custom;
        }
        $default = 'C:\\Windows\\System32\\wsl.exe';
        if (file_exists($default)) {
            return $default;
        }
        return 'wsl';
    }

    private function logWslCommand(string $cmd): void
    {
        if (class_exists(\Illuminate\Support\Facades\Log::class)) {
            \Illuminate\Support\Facades\Log::info('WSL bbox command', ['cmd' => $cmd]);
        }
    }

    private function logWslBboxError(?int $exitCode, string $stderr, string $stdout): void
    {
        if (class_exists(\Illuminate\Support\Facades\Log::class)) {
            \Illuminate\Support\Facades\Log::error('WSL bbox failed', [
                'exit_code' => $exitCode,
                'stderr' => $stderr,
                'stdout_head' => substr($stdout, 0, 500),
            ]);
        }
    }

    private function allowWslBboxFallback(): bool
    {
        $flag = getenv('WSL_BBOX_FALLBACK');
        if ($flag === false) {
            return false;
        }
        return strtolower(trim((string) $flag)) === 'true';
    }

    private function dumpDebugText(string $label, string $text, string $pdfPath, string $engine): void
    {
        $flag = getenv('DEBUG_RAW_TEXT_DUMP');
        if ($flag === false || strtolower(trim((string) $flag)) !== 'true') {
            return;
        }

        $safeName = preg_replace('/[^A-Za-z0-9_\-]+/', '_', pathinfo($pdfPath, PATHINFO_FILENAME));
        $safeLabel = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $label);
        $safeEngine = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $engine);
        $dir = storage_path('app/debug_text');
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $file = $dir . DIRECTORY_SEPARATOR . $safeName . '_' . $safeEngine . '_' . $safeLabel . '.txt';
        @file_put_contents($file, $text);
    }

    private function logBboxFallback(string $reason): void
    {
        if (class_exists(\Illuminate\Support\Facades\Log::class)) {
            \Illuminate\Support\Facades\Log::warning('WSL bbox failed, falling back to pdfplumber', ['reason' => $reason]);
        }
    }

    private function parseBboxHtml(string $html): array
    {
        $pages = [];
        if (!preg_match_all('/<page\b[^>]*>(.*?)<\/page>/s', $html, $pageMatches)) {
            return $pages;
        }

        foreach ($pageMatches[1] as $pageHtml) {
            $words = $this->parseBboxWords($pageHtml);
            $pages[] = $this->buildLinesFromWords($words);
        }

        return $pages;
    }

    private function parseBboxWords(string $pageHtml): array
    {
        $words = [];
        $pattern = '/<word\b[^>]*xMin="([^"]+)"[^>]*yMin="([^"]+)"[^>]*xMax="([^"]+)"[^>]*yMax="([^"]+)"[^>]*>(.*?)<\/word>/s';
        if (!preg_match_all($pattern, $pageHtml, $matches, PREG_SET_ORDER)) {
            return $words;
        }

        foreach ($matches as $m) {
            $text = html_entity_decode(strip_tags($m[5]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = trim($text);
            if ($text === '') {
                continue;
            }
            $words[] = [
                'xMin' => (float) $m[1],
                'yMin' => (float) $m[2],
                'xMax' => (float) $m[3],
                'yMax' => (float) $m[4],
                'text' => $text,
            ];
        }

        return $words;
    }

    private function buildLinesFromWords(array $words): string
    {
        $lines = [];
        $lineTolerance = 2.5;

        foreach ($words as $word) {
            $y = ($word['yMin'] + $word['yMax']) / 2;
            $placed = false;
            foreach ($lines as &$line) {
                if (abs($line['y'] - $y) <= $lineTolerance) {
                    $line['words'][] = $word;
                    $line['y'] = ($line['y'] + $y) / 2;
                    $placed = true;
                    break;
                }
            }
            unset($line);
            if (!$placed) {
                $lines[] = [
                    'y' => $y,
                    'words' => [$word],
                ];
            }
        }

        usort($lines, static function ($a, $b) {
            return $a['y'] <=> $b['y'];
        });

        $output = [];
        $prevY = null;
        foreach ($lines as $line) {
            if ($prevY !== null && ($line['y'] - $prevY) > 12) {
                $output[] = '';
            }
            $prevY = $line['y'];

            usort($line['words'], static function ($a, $b) {
                return $a['xMin'] <=> $b['xMin'];
            });

            $lineText = '';
            $prevXMax = null;
            foreach ($line['words'] as $word) {
                if ($prevXMax !== null) {
                    $gap = $word['xMin'] - $prevXMax;
                    if ($gap > 2) {
                        $lineText .= ' ';
                    }
                }
                $lineText .= $word['text'];
                $prevXMax = $word['xMax'];
            }
            $output[] = $lineText;
        }

        return trim(implode("\n", $output));
    }

    private function formatTextForOutput(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $lines = explode("\n", $text);
        $normalized = [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $line = rtrim($lines[$i]);
            if ($line === '') {
                $normalized[] = '';
                continue;
            }

            $line = preg_replace('/\s+/', ' ', $line);

            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{2},\s+\d{1,2}:\d{2}\s+[AP]M\s+Score Report \| Cibil Dashboard$/', $line, $m)) {
                $parts = preg_split('/\s+Score Report \| Cibil Dashboard/', $line);
                $normalized[] = trim($parts[0]);
                $normalized[] = 'Score Report | Cibil Dashboard';
                continue;
            }

            if (preg_match('/^(https?:\/\/\S+)\s+(\d{1,2}\/\d{2})$/', $line, $m)) {
                $normalized[] = $m[1];
                $normalized[] = $m[2];
                continue;
            }

            if ($line === '300 900' && isset($lines[$i + 1])) {
                $next = trim(preg_replace('/\s+/', ' ', $lines[$i + 1]));
                if (preg_match('/^\d{3}$/', $next)) {
                    $normalized[] = "300 {$next} 900";
                    $i++;
                    continue;
                }
            }

            $normalized[] = $line;
        }

        $final = [];
        $pendingDpd = null;
        $count = count($normalized);

        for ($i = 0; $i < $count; $i++) {
            $line = $normalized[$i];
            if ($line === '') {
                $final[] = '';
                continue;
            }

            if (preg_match('/^PAYMENT STATUS\s+(\d{1,3})$/', $line, $m)) {
                $pendingDpd = $m[1];
                $final[] = 'PAYMENT STATUS';
                continue;
            }

            if (preg_match('/^(.*\D)\s+(\d{1,2}\/\d{2})$/', $line, $m)) {
                $final[] = trim($m[1]);
                $final[] = $m[2];
                continue;
            }

            $months = $this->extractMonthYearTokens($line);
            $currentDpds = $this->extractDpdTokens($line);
            if (count($months) > 1 && count($currentDpds) < count($months) && isset($normalized[$i + 1])) {
                $nextLine = $normalized[$i + 1];
                $nextMonths = $this->extractMonthYearTokens($nextLine);
                $dpdTokens = $this->extractDpdTokens($nextLine);
                if (count($nextMonths) === 0 && count($dpdTokens) > 0) {
                    foreach ($months as $idx => $month) {
                        $dpd = $dpdTokens[$idx] ?? 'N/A';
                        $final[] = "{$month} {$dpd}";
                    }
                    $i++;
                    continue;
                }
            }

            if (count($months) === 1 && count($currentDpds) === 0 && isset($normalized[$i + 1])) {
                $nextLine = $normalized[$i + 1];
                $nextMonths = $this->extractMonthYearTokens($nextLine);
                $dpdTokens = $this->extractDpdTokens($nextLine);
                if (count($nextMonths) === 0 && count($dpdTokens) === 1) {
                    $final[] = "{$months[0]} {$dpdTokens[0]}";
                    $i++;
                    continue;
                }
            }

            if ($pendingDpd !== null && count($months) === 1) {
                $final[] = "{$months[0]} {$pendingDpd}";
                $pendingDpd = null;
                continue;
            }

            $final[] = $line;
        }

        return implode("\n", $final);
    }

    private function extractMonthYearTokens(string $line): array
    {
        if (!preg_match_all('/\b([A-Za-z]{3}\s+\d{4})\b/', $line, $matches)) {
            return [];
        }
        return $matches[1];
    }

    private function extractDpdTokens(string $line): array
    {
        if (!preg_match_all('/\b(\d{1,3}|STD|XXX|DBT|SMA|SUB|LSS)\b/', $line, $matches)) {
            return [];
        }
        return $matches[1];
    }
}
