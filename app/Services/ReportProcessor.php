<?php

namespace App\Services;

class ReportProcessor
{
    private OcrTextExtractor $ocr;
    private CibilParser $parser;
    private ReportSanitizer $sanitizer;
    private TextNormalizer $normalizer;

    public function __construct(
        OcrTextExtractor $ocr,
        CibilParser $parser,
        ReportSanitizer $sanitizer,
        TextNormalizer $normalizer
    ) {
        $this->ocr = $ocr;
        $this->parser = $parser;
        $this->sanitizer = $sanitizer;
        $this->normalizer = $normalizer;
    }

    public function process(string $pdfPath, ?string $password, string $userId): array
    {
        $ocrResult = $this->ocr->extractText($pdfPath, $password);
        $text = $this->normalizer->normalize($ocrResult['fullText'] ?? '');
        $headerText = $this->normalizer->normalize($ocrResult['headerText'] ?? '');
        $parsed = $this->parser->parse($text, $headerText);
        $cleanData = $this->sanitizer->sanitize($parsed);

        $fullReport = [
            'InquiryRequestInfo' => [
                'UserId' => $userId,
            ],
            'InputResponse' => $cleanData,
        ];

        return [
            'extractedText' => $text,
            'structuredData' => $fullReport,
            'failedAccounts' => [],
        ];
    }
}
