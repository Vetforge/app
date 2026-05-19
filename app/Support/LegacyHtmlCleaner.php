<?php

namespace App\Support;

class LegacyHtmlCleaner
{
    /**
     * Clean garbled HTML from legacy PDF-extracted text.
     *
     * The old VetReport app stored text extracted from PDFs where certain characters
     * (a, b, d, n, o, t, u) were placed on separate lines due to font encoding issues,
     * and HTML tags themselves were split across lines.
     */
    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        // Step 1: Fix broken HTML tags — remove spurious newlines inside < ... >
        $html = preg_replace_callback(
            '/<[^>]*>/s',
            static fn (array $m): string => preg_replace('/[\r\n]+/', '', $m[0]) ?? $m[0],
            $html
        ) ?? $html;

        // Step 2: Remove intra-word newlines before characters commonly split by the PDF font.
        // Characters a, b, d, n, o, t, u frequently appear on a new line mid-word.
        // We also match > so that <tag>\naudit becomes <tag>audit (no leading space).
        // We iterate until stable because sequences like \na\nt require multiple passes.
        do {
            $prev = $html;
            $html = preg_replace('/([\p{L}\'> ])\n(?=[abdnotu])/u', '$1', $html) ?? $html;
        } while ($html !== $prev);

        // Step 3: Convert remaining newlines to spaces — real line breaks use <br> tags.
        $html = str_replace(["\r\n", "\r", "\n"], ' ', $html);

        // Step 4: Collapse multiple spaces
        $html = preg_replace('/ {2,}/', ' ', $html) ?? $html;

        return trim($html);
    }

    public static function plainText(string $html): string
    {
        $html = self::clean($html);

        if ($html === '') {
            return '';
        }

        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('/<\s*(br|\/p|\/div|\/li)\s*\/?>/i', ' ', $html) ?? $html;
        $html = preg_replace('/<\s*(p|div|li)\b[^>]*>/i', ' ', $html) ?? $html;
        $html = strip_tags($html);
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('/[\h\r\n]+/u', ' ', $html) ?? $html;

        return trim($html);
    }

    public static function plainTextWithBreaks(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $html = preg_replace_callback(
            '/<[^>]*>/s',
            static fn (array $m): string => preg_replace('/[\r\n]+/', '', $m[0]) ?? $m[0],
            $html
        ) ?? $html;

        do {
            $prev = $html;
            $html = preg_replace('/([\p{L}\'> ])\n(?=[abdnotu])/u', '$1', $html) ?? $html;
        } while ($html !== $prev);

        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('/<\s*br\s*\/?>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\s*\/\s*(p|div|li)\s*>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\s*(p|div|li)\b[^>]*>/i', '', $html) ?? $html;
        $html = strip_tags($html);
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = str_replace(["\r\n", "\r"], "\n", $html);
        $html = preg_replace('/\h+/u', ' ', $html) ?? $html;
        $html = preg_replace('/ *\n */', "\n", $html) ?? $html;
        $html = preg_replace('/\n{3,}/', "\n\n", $html) ?? $html;

        return trim($html);
    }

    public static function plainPayload(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::plainText($value);
        }

        if (is_array($value)) {
            return array_map([self::class, 'plainPayload'], $value);
        }

        return $value;
    }

    /**
     * Recursively clean all string values in an array (e.g. a decoded JSON payload).
     */
    public static function cleanPayload(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::clean($value);
        }

        if (is_array($value)) {
            return array_map([self::class, 'cleanPayload'], $value);
        }

        return $value;
    }
}
