<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Nettoie le texte HTML "abime" issu de l'ancienne application VetReport.
 *
 * Le texte de l'ancienne base provient de PDF extraits avec un encodage de
 * police defaillant. Trois symptomes :
 *  - des balises HTML coupees en plein milieu par des retours a la ligne ;
 *  - des caracteres isoles (a, b, d, n, o, t, u) rejetes sur leur propre
 *    ligne, ce qui decoupe les mots ;
 *  - des entites HTML mal formees ou pointant vers du Windows-1252.
 *
 * Hypothese cle : les vrais retours a la ligne sont portes par des <br>.
 * Tout "\n" nu est donc un artefact d'extraction.
 */
class LegacyHtmlCleaner
{
    /**
     * Plage de controle C1 (128-159) : html_entity_decode() traduirait ces
     * entites numeriques en caracteres invisibles, alors que dans les donnees
     * legacy elles encodent en realite des caracteres Windows-1252.
     *
     * @var array<int, string>
     */
    private const CP1252 = [
        128 => '€', 130 => '‚', 131 => 'ƒ', 132 => '„', 133 => '…',
        134 => '†', 135 => '‡', 136 => 'ˆ', 137 => '‰', 138 => 'Š',
        139 => '‹', 140 => 'Œ', 142 => 'Ž', 145 => '‘', 146 => '’',
        147 => '“', 148 => '”', 149 => '•', 150 => '–', 151 => '—',
        152 => '˜', 153 => '™', 154 => 'š', 155 => '›', 156 => 'œ',
        158 => 'ž', 159 => 'Ÿ',
    ];

    /**
     * Entites nommees pertinentes en francais. Sert a la fois de table
     * principale et de support pour les entites sans point-virgule final.
     * La casse est significative (eacute != Eacute).
     *
     * @var array<string, string>
     */
    private const NAMED_ENTITIES = [
        // Voyelles accentuees minuscules.
        'agrave' => 'à', 'aacute' => 'á', 'acirc' => 'â', 'atilde' => 'ã', 'auml' => 'ä', 'aring' => 'å',
        'egrave' => 'è', 'eacute' => 'é', 'ecirc' => 'ê', 'euml' => 'ë',
        'igrave' => 'ì', 'iacute' => 'í', 'icirc' => 'î', 'iuml' => 'ï',
        'ograve' => 'ò', 'oacute' => 'ó', 'ocirc' => 'ô', 'otilde' => 'õ', 'ouml' => 'ö',
        'ugrave' => 'ù', 'uacute' => 'ú', 'ucirc' => 'û', 'uuml' => 'ü',
        'yacute' => 'ý', 'yuml' => 'ÿ',
        'ccedil' => 'ç', 'ntilde' => 'ñ',
        'aelig' => 'æ', 'oelig' => 'œ', 'szlig' => 'ß',
        // Voyelles accentuees majuscules.
        'Agrave' => 'À', 'Aacute' => 'Á', 'Acirc' => 'Â', 'Atilde' => 'Ã', 'Auml' => 'Ä', 'Aring' => 'Å',
        'Egrave' => 'È', 'Eacute' => 'É', 'Ecirc' => 'Ê', 'Euml' => 'Ë',
        'Igrave' => 'Ì', 'Iacute' => 'Í', 'Icirc' => 'Î', 'Iuml' => 'Ï',
        'Ograve' => 'Ò', 'Oacute' => 'Ó', 'Ocirc' => 'Ô', 'Otilde' => 'Õ', 'Ouml' => 'Ö',
        'Ugrave' => 'Ù', 'Uacute' => 'Ú', 'Ucirc' => 'Û', 'Uuml' => 'Ü',
        'Yacute' => 'Ý', 'Yuml' => 'Ÿ',
        'Ccedil' => 'Ç', 'Ntilde' => 'Ñ',
        'AElig' => 'Æ', 'OElig' => 'Œ',
        // Espaces.
        'nbsp' => ' ', 'ensp' => ' ', 'emsp' => ' ', 'thinsp' => ' ',
        // Ponctuation typographique.
        'laquo' => '«', 'raquo' => '»',
        'lsquo' => '‘', 'rsquo' => '’', 'sbquo' => '‚',
        'ldquo' => '“', 'rdquo' => '”', 'bdquo' => '„',
        'lsaquo' => '‹', 'rsaquo' => '›',
        'ndash' => '–', 'mdash' => '—', 'hellip' => '…',
        'middot' => '·', 'bull' => '•', 'dagger' => '†', 'Dagger' => '‡',
        'prime' => '′', 'Prime' => '″', 'permil' => '‰',
        'iexcl' => '¡', 'iquest' => '¿', 'sect' => '§', 'para' => '¶',
        // Symboles et unites.
        'deg' => '°', 'plusmn' => '±', 'times' => '×', 'divide' => '÷',
        'micro' => 'µ', 'sup1' => '¹', 'sup2' => '²', 'sup3' => '³',
        'frac12' => '½', 'frac14' => '¼', 'frac34' => '¾',
        'euro' => '€', 'cent' => '¢', 'pound' => '£', 'yen' => '¥', 'curren' => '¤',
        'copy' => '©', 'reg' => '®', 'trade' => '™',
        // Structurelles.
        'amp' => '&', 'lt' => '<', 'gt' => '>', 'quot' => '"', 'apos' => "'",
    ];

    /**
     * Nettoie le HTML en CONSERVANT le balisage (<br>, <b>, ...).
     */
    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $html = self::normalizeWhitespace($html);
        $html = self::stripNonContent($html);
        $html = self::repairBrokenTags($html);
        $html = self::collapseStrayNewlines($html);
        $html = self::collapseSpaces($html);

        return trim($html);
    }

    /**
     * Texte brut sur une seule ligne (balisage retire, entites decodees).
     */
    public static function plainText(string $html): string
    {
        $html = self::clean($html);

        if ($html === '') {
            return '';
        }

        $html = preg_replace('#<\s*br\s*/?\s*>#i', ' ', $html) ?? $html;
        $html = preg_replace('#</?\s*(p|div|li|tr|td|th|h[1-6]|ul|ol|table|blockquote|section)\b[^>]*>#i', ' ', $html) ?? $html;
        $html = strip_tags($html);
        $html = self::decodeEntities($html);

        return trim(self::collapseSpaces(str_replace(["\r", "\n"], ' ', $html)));
    }

    /**
     * Texte brut en conservant les sauts de ligne logiques (<br>, blocs).
     */
    public static function plainTextWithBreaks(string $html): string
    {
        $html = self::clean($html);

        if ($html === '') {
            return '';
        }

        $html = preg_replace('#<\s*br\s*/?\s*>#i', "\n", $html) ?? $html;
        $html = preg_replace('#</\s*(p|div|li|tr|h[1-6]|ul|ol|table|blockquote|section)\s*>#i', "\n", $html) ?? $html;
        $html = preg_replace('#<\s*(p|div|li|tr|h[1-6]|blockquote|section)\b[^>]*>#i', "\n", $html) ?? $html;
        $html = preg_replace('#</?\s*(td|th)\b[^>]*>#i', ' ', $html) ?? $html;
        $html = strip_tags($html);
        $html = self::decodeEntities($html);

        $html = str_replace(["\r\n", "\r"], "\n", $html);
        $html = preg_replace('/[^\S\n]+/u', ' ', $html) ?? $html;
        $html = preg_replace('/ *\n */', "\n", $html) ?? $html;
        $html = preg_replace('/\n{3,}/', "\n\n", $html) ?? $html;

        return trim($html);
    }

    /**
     * Nettoie recursivement (texte brut) toutes les chaines d'un tableau.
     */
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
     * Nettoie recursivement (HTML conserve) toutes les chaines d'un tableau.
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

    /**
     * Decode toutes les entites HTML : numeriques (decimal et hexadecimal),
     * nommees, avec ou sans point-virgule, doublement encodees, et corrige
     * les entites numeriques pointant vers la plage Windows-1252.
     */
    public static function decodeEntities(string $html): string
    {
        if (! str_contains($html, '&')) {
            return $html;
        }

        // Plusieurs passes pour le double encodage (&amp;eacute; -> &eacute; -> e accent).
        for ($pass = 0; $pass < 3; $pass++) {
            $before = $html;

            // 1. Entites numeriques (&#233; &#xE9; &#146 ...).
            $html = preg_replace_callback(
                '/&#(x[0-9a-f]+|[0-9]+);?/i',
                static function (array $m): string {
                    $raw = $m[1];
                    $code = ($raw[0] === 'x' || $raw[0] === 'X')
                        ? (int) hexdec(substr($raw, 1))
                        : (int) $raw;

                    // Plage de controle C1 -> remappage Windows-1252.
                    if ($code >= 128 && $code <= 159) {
                        return self::CP1252[$code] ?? '';
                    }

                    // Code invalide ou surrogate -> on retire.
                    if ($code <= 0 || $code > 0x10FFFF || ($code >= 0xD800 && $code <= 0xDFFF)) {
                        return '';
                    }

                    $char = mb_chr($code, 'UTF-8');

                    return $char === false ? '' : $char;
                },
                $html,
            ) ?? $html;

            // 2. Entites nommees, point-virgule final optionnel.
            $html = preg_replace_callback(
                '/&([a-z][a-z0-9]{1,31});?/i',
                static function (array $m): string {
                    $name = $m[1];

                    // Correspondance exacte (table francaise).
                    if (isset(self::NAMED_ENTITIES[$name])) {
                        return self::NAMED_ENTITIES[$name];
                    }

                    // Repli : table HTML5 complete de PHP.
                    $decoded = html_entity_decode('&'.$name.';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if ($decoded !== '&'.$name.';') {
                        return $decoded;
                    }

                    // Sans point-virgule, l'entite peut etre collee au mot
                    // suivant (&eacutemot) : on cherche le plus long prefixe connu.
                    if (! str_ends_with($m[0], ';')) {
                        for ($len = strlen($name) - 1; $len >= 2; $len--) {
                            $prefix = substr($name, 0, $len);
                            if (isset(self::NAMED_ENTITIES[$prefix])) {
                                return self::NAMED_ENTITIES[$prefix].substr($name, $len);
                            }
                        }
                    }

                    return $m[0];
                },
                $html,
            ) ?? $html;

            if ($html === $before) {
                break;
            }
        }

        // Espaces speciaux residuels -> espace normal.
        return str_replace(["\u{00A0}", "\u{202F}", "\u{2007}"], ' ', $html);
    }

    // ------------------------------------------------------------------
    // Helpers prives
    // ------------------------------------------------------------------

    private static function normalizeWhitespace(string $html): string
    {
        // BOM, espaces de largeur nulle, separateurs, marqueurs directionnels.
        $html = preg_replace('/[\x{FEFF}\x{200B}-\x{200F}\x{2028}\x{2029}\x{202A}-\x{202E}]/u', '', $html) ?? $html;

        // Trait d'union conditionnel (soft hyphen) : recolle le mot.
        $html = preg_replace('/\x{00AD}[^\S\n]*\n?/u', '', $html) ?? $html;

        // CRLF / CR / form feed / tab verticale -> \n.
        $html = preg_replace('/\r\n|\r|\x0C|\x0B/', "\n", $html) ?? $html;

        return $html;
    }

    private static function stripNonContent(string $html): string
    {
        $patterns = [
            '#<(script|style)\b[^>]*>.*?</\1\s*>#is',
            '/<!--.*?-->/s',
            '/<!\[CDATA\[.*?\]\]>/s',
            '/<!DOCTYPE[^>]*>/i',
        ];

        foreach ($patterns as $pattern) {
            $html = preg_replace($pattern, '', $html) ?? $html;
        }

        return $html;
    }

    private static function repairBrokenTags(string $html): string
    {
        return preg_replace_callback(
            '/<[^<>]*>/',
            static fn (array $m): string => preg_replace('/\s*\n\s*/', '', $m[0]) ?? $m[0],
            $html,
        ) ?? $html;
    }

    private static function collapseStrayNewlines(string $html): string
    {
        // Ponctuation forte ou fin de balise suivie d'un \n -> espace.
        $html = preg_replace('/([.!?:;>])[^\S\n]*\n+/u', '$1 ', $html) ?? $html;

        // Frontiere de phrase sans ponctuation : minuscule/chiffre \n Majuscule.
        $html = preg_replace('/([\p{Ll}\p{N}])[^\S\n]*\n+(?=\p{Lu})/u', '$1 ', $html) ?? $html;

        // Tout \n restant est une coupure interne de mot -> suppression.
        return str_replace("\n", '', $html);
    }

    private static function collapseSpaces(string $html): string
    {
        return preg_replace('/[^\S\n]{2,}/u', ' ', $html) ?? $html;
    }
}
