<?php

namespace App\Services\Pdf;

/**
 * DomPDF paints glyphs left-to-right and does not apply Arabic joining.
 * Convert Persian/Arabic runs to presentation forms and visual order
 * so workbooks stay readable.
 */
class PersianPdfText
{
    /** @var array<string, array{0:string,1:string,2:?string,3:?string}> isolated, final, initial, medial */
    protected array $forms;

    /** @var array<string, true> */
    protected array $dual;

    /** @var array<string, true> */
    protected array $right;

    public function __construct()
    {
        // [isolated, final, initial, medial] — null initial/medial = right-joining only
        $this->forms = [
            'آ' => ['ﺁ', 'ﺂ', null, null],
            'أ' => ['ﺃ', 'ﺄ', null, null],
            'ؤ' => ['ﺅ', 'ﺆ', null, null],
            'إ' => ['ﺇ', 'ﺈ', null, null],
            'ئ' => ['ﺉ', 'ﺊ', 'ﺋ', 'ﺌ'],
            'ا' => ['ﺍ', 'ﺎ', null, null],
            'ب' => ['ﺏ', 'ﺐ', 'ﺑ', 'ﺒ'],
            'پ' => ['ﭖ', 'ﭗ', 'ﭘ', 'ﭙ'],
            'ة' => ['ﺓ', 'ﺔ', null, null],
            'ت' => ['ﺕ', 'ﺖ', 'ﺗ', 'ﺘ'],
            'ث' => ['ﺙ', 'ﺚ', 'ﺛ', 'ﺜ'],
            'ج' => ['ﺝ', 'ﺞ', 'ﺟ', 'ﺠ'],
            'چ' => ['ﭺ', 'ﭻ', 'ﭼ', 'ﭽ'],
            'ح' => ['ﺡ', 'ﺢ', 'ﺣ', 'ﺤ'],
            'خ' => ['ﺥ', 'ﺦ', 'ﺧ', 'ﺨ'],
            'د' => ['ﺩ', 'ﺪ', null, null],
            'ذ' => ['ﺫ', 'ﺬ', null, null],
            'ر' => ['ﺭ', 'ﺮ', null, null],
            'ز' => ['ﺯ', 'ﺰ', null, null],
            'ژ' => ['ﮊ', 'ﮋ', null, null],
            'س' => ['ﺱ', 'ﺲ', 'ﺳ', 'ﺴ'],
            'ش' => ['ﺵ', 'ﺶ', 'ﺷ', 'ﺸ'],
            'ص' => ['ﺹ', 'ﺺ', 'ﺻ', 'ﺼ'],
            'ض' => ['ﺽ', 'ﺾ', 'ﺿ', 'ﻀ'],
            'ط' => ['ﻁ', 'ﻂ', 'ﻃ', 'ﻄ'],
            'ظ' => ['ﻅ', 'ﻆ', 'ﻇ', 'ﻈ'],
            'ع' => ['ﻉ', 'ﻊ', 'ﻋ', 'ﻌ'],
            'غ' => ['ﻍ', 'ﻎ', 'ﻏ', 'ﻐ'],
            'ف' => ['ﻑ', 'ﻒ', 'ﻓ', 'ﻔ'],
            'ق' => ['ﻕ', 'ﻖ', 'ﻗ', 'ﻘ'],
            'ك' => ['ﻙ', 'ﻚ', 'ﻛ', 'ﻜ'],
            'ک' => ['ﮎ', 'ﮏ', 'ﮐ', 'ﮑ'],
            'گ' => ['ﮒ', 'ﮓ', 'ﮔ', 'ﮕ'],
            'ل' => ['ﻝ', 'ﻞ', 'ﻟ', 'ﻠ'],
            'م' => ['ﻡ', 'ﻢ', 'ﻣ', 'ﻤ'],
            'ن' => ['ﻥ', 'ﻦ', 'ﻧ', 'ﻨ'],
            'ه' => ['ﻩ', 'ﻪ', 'ﻫ', 'ﻬ'],
            'و' => ['ﻭ', 'ﻮ', null, null],
            'ى' => ['ﻯ', 'ﻰ', 'ﻳ', 'ﻴ'],
            'ي' => ['ﻱ', 'ﻲ', 'ﻳ', 'ﻴ'],
            'ی' => ['ﯼ', 'ﯽ', 'ﯾ', 'ﯿ'],
            'ء' => ['ء', 'ء', null, null],
        ];

        $this->dual = [];
        $this->right = [];
        foreach ($this->forms as $letter => $form) {
            if ($form[2] !== null) {
                $this->dual[$letter] = true;
            } else {
                $this->right[$letter] = true;
            }
        }
    }

    public function reshape(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $joined = [];
        $count = count($chars);

        for ($i = 0; $i < $count; $i++) {
            $ch = $chars[$i];
            if (! isset($this->forms[$ch])) {
                $joined[] = $ch;

                continue;
            }

            $prev = $this->previousJoiner($chars, $i);
            $next = $this->nextJoiner($chars, $i);
            $joinsPrev = $prev !== null && $this->canJoinLeft($prev);
            $joinsNext = $next !== null && isset($this->forms[$next]);

            $form = $this->forms[$ch];
            if ($joinsPrev && $joinsNext && $form[3] !== null) {
                $joined[] = $form[3];
            } elseif ($joinsNext && $form[2] !== null) {
                $joined[] = $form[2];
            } elseif ($joinsPrev) {
                $joined[] = $form[1];
            } else {
                $joined[] = $form[0];
            }
        }

        return $this->visualOrder($joined);
    }

    public function reshapeHtml(string $html): string
    {
        $reshaped = preg_replace_callback(
            '/>([^<]+)</u',
            fn (array $m) => '>'.$this->reshape($m[1]).'<',
            $html
        );

        return is_string($reshaped) ? $reshaped : $html;
    }

    /**
     * @param  list<string>  $chars
     */
    protected function previousJoiner(array $chars, int $index): ?string
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            if ($chars[$i] === "\u{200C}" || $chars[$i] === "\u{200D}") {
                return $chars[$i] === "\u{200D}" ? 'ب' : null;
            }
            if ($this->isIgnorable($chars[$i])) {
                continue;
            }

            return isset($this->forms[$chars[$i]]) ? $chars[$i] : null;
        }

        return null;
    }

    /**
     * @param  list<string>  $chars
     */
    /**
     * @param  list<string>  $chars
     */
    protected function nextJoiner(array $chars, int $index): ?string
    {
        $count = count($chars);
        for ($i = $index + 1; $i < $count; $i++) {
            if ($chars[$i] === "\u{200C}") {
                return null;
            }
            if ($chars[$i] === "\u{200D}") {
                return 'ب';
            }
            if ($this->isIgnorable($chars[$i])) {
                continue;
            }

            return isset($this->forms[$chars[$i]]) ? $chars[$i] : null;
        }

        return null;
    }

    protected function canJoinLeft(string $letter): bool
    {
        return isset($this->dual[$letter]);
    }

    protected function isIgnorable(string $ch): bool
    {
        return preg_match('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u', $ch) === 1;
    }

    /**
     * Reverse the line for LTR painters, keeping Latin/digit sequences intact.
     *
     * @param  list<string>  $chars
     */
    protected function visualOrder(array $chars): string
    {
        if ($chars === []) {
            return '';
        }

        $hasArabic = false;
        foreach ($chars as $ch) {
            if ($this->isArabicChar($ch)) {
                $hasArabic = true;
                break;
            }
        }

        if (! $hasArabic) {
            return implode('', $chars);
        }

        $runs = [];
        $buffer = '';
        $bufferIsLatin = null;

        foreach ($chars as $ch) {
            $isLatin = $this->isLatinRun($ch);
            if ($bufferIsLatin === null) {
                $buffer = $ch;
                $bufferIsLatin = $isLatin;

                continue;
            }
            if ($isLatin === $bufferIsLatin) {
                $buffer .= $ch;

                continue;
            }
            $runs[] = ['text' => $buffer, 'latin' => $bufferIsLatin];
            $buffer = $ch;
            $bufferIsLatin = $isLatin;
        }
        if ($buffer !== '') {
            $runs[] = ['text' => $buffer, 'latin' => $bufferIsLatin];
        }

        $runs = array_reverse($runs);
        $out = '';
        foreach ($runs as $run) {
            $out .= $run['latin'] ? $run['text'] : $this->utf8Reverse($run['text']);
        }

        return $out;
    }

    protected function isArabicChar(string $ch): bool
    {
        if (preg_match('/[\x{06F0}-\x{06F9}\x{0660}-\x{0669}]/u', $ch) === 1) {
            return false;
        }

        return preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $ch) === 1;
    }

    protected function isLatinRun(string $ch): bool
    {
        return preg_match('/[0-9A-Za-z@._:\/\\\\%+\-٪\x{06F0}-\x{06F9}\x{0660}-\x{0669}]/u', $ch) === 1;
    }

    protected function utf8Reverse(string $text): string
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode('', array_reverse($chars));
    }
}
