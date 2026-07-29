<?php
/*
 * bsxmlfmt.php
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 *
 * XML formatter for BrightScriptthat preserves multi-line formatting.
 *
 * Removes unnecessary whitespace within existing lines while preserving the original layout and minimizing diffs.
 * Indentation follows the BrightScript convention of 4-space tabs.
 * Blank lines are removed.
 */

function xmllint_is_ws($ch)
{
    return $ch === ' ' || $ch === "\t" || $ch === "\n" || $ch === "\r";
}

/*
 * Turn a run of whitespace into one 'newline' token per line break it contains; other whitespace is discarded
 */
function xmllint_newline_tokens($ws)
{
    $tokens = array();
    $len = strlen($ws);
    $i = 0;
    while ($i < $len) {
        $ch = $ws[$i];
        if ($ch === "\r") {
            $tokens[] = array('type' => 'newline');
            $i++;
            if ($i < $len && $ws[$i] === "\n") {
                $i++;
            }
        } elseif ($ch === "\n") {
            $tokens[] = array('type' => 'newline');
            $i++;
        } else {
            $i++;
        }
    }
    return $tokens;
}

/*
 * Tokenize a single tag's contents (name + attributes) into tag_open / attr /
 * tag_end tokens, plus a 'newline' token for each line break found in the
 * whitespace that gets discarded. Returns null if the tag doesn't parse
 * cleanly (valueless attribute, unbalanced quotes, etc.) so the caller can
 * fall back to treating it as opaque text instead of risking corruption.
 */
function xmllint_tag_tokens($tagText)
{
    $len = strlen($tagText);
    if ($len < 2 || $tagText[0] !== '<' || $tagText[$len - 1] !== '>') {
        return null;
    }

    $i = 1;
    $closing = false;
    if ($i < $len && $tagText[$i] === '/') {
        $closing = true;
        $i++;
    }

    $nameStart = $i;
    while ($i < $len && !xmllint_is_ws($tagText[$i]) && $tagText[$i] !== '/' && $tagText[$i] !== '>') {
        $i++;
    }
    if ($i === $nameStart) {
        return null;
    }
    $name = substr($tagText, $nameStart, $i - $nameStart);

    $tokens = array();
    $tokens[] = array('type' => 'tag_open', 'name' => $name, 'closing' => $closing);

    while (true) {
        $gapStart = $i;
        while ($i < $len && xmllint_is_ws($tagText[$i])) {
            $i++;
        }
        $gap = substr($tagText, $gapStart, $i - $gapStart);

        if ($i >= $len) {
            return null;
        }

        if ($tagText[$i] === '/') {
            $i++;
            if ($i >= $len || $tagText[$i] !== '>' || $i !== $len - 1) {
                return null;
            }
            foreach (xmllint_newline_tokens($gap) as $nt) {
                $tokens[] = $nt;
            }
            $tokens[] = array('type' => 'tag_end', 'selfClose' => true);
            return $tokens;
        }

        if ($tagText[$i] === '>') {
            if ($i !== $len - 1) {
                return null;
            }
            foreach (xmllint_newline_tokens($gap) as $nt) {
                $tokens[] = $nt;
            }
            $tokens[] = array('type' => 'tag_end', 'selfClose' => false);
            return $tokens;
        }

        $attrNameStart = $i;
        while ($i < $len && !xmllint_is_ws($tagText[$i]) && $tagText[$i] !== '=' && $tagText[$i] !== '/' && $tagText[$i] !== '>') {
            $i++;
        }
        if ($i === $attrNameStart) {
            return null;
        }
        $attrName = substr($tagText, $attrNameStart, $i - $attrNameStart);

        $eqGap1Start = $i;
        while ($i < $len && xmllint_is_ws($tagText[$i])) {
            $i++;
        }
        $eqGap1 = substr($tagText, $eqGap1Start, $i - $eqGap1Start);

        if ($i >= $len || $tagText[$i] !== '=') {
            return null; /* valueless attribute or malformed tag - bail, caller keeps it verbatim */
        }
        $i++;

        $eqGap2Start = $i;
        while ($i < $len && xmllint_is_ws($tagText[$i])) {
            $i++;
        }
        $eqGap2 = substr($tagText, $eqGap2Start, $i - $eqGap2Start);

        if ($i >= $len || ($tagText[$i] !== '"' && $tagText[$i] !== "'")) {
            return null;
        }
        $quote = $tagText[$i];
        $i++;

        $valStart = $i;
        $closeQuotePos = strpos($tagText, $quote, $i);
        if ($closeQuotePos === false) {
            return null;
        }
        $value = substr($tagText, $valStart, $closeQuotePos - $valStart);
        $i = $closeQuotePos + 1;

        foreach (xmllint_newline_tokens($gap) as $nt) {
            $tokens[] = $nt;
        }
        foreach (xmllint_newline_tokens($eqGap1) as $nt) {
            $tokens[] = $nt;
        }
        foreach (xmllint_newline_tokens($eqGap2) as $nt) {
            $tokens[] = $nt;
        }
        $tokens[] = array('type' => 'attr', 'name' => $attrName, 'quote' => $quote, 'value' => $value);
    }
}

/*
 * Tokenize the whole file into a flat token stream:
 *   tag_open / attr / tag_end - structural tag/attribute tokens
 *   verbatim                  - comment, CDATA, PI, DOCTYPE, or an unparsable tag - copied through untouched
 *   text                      - element content that isn't pure whitespace
 *   newline                   - one per discarded line break (the only whitespace that survives)
 */
function xmllint_lex($contents)
{
    $tokens = array();
    $n = strlen($contents);
    $i = 0;
    $textStart = 0;

    while ($i < $n) {
        if ($contents[$i] !== '<') {
            $i++;
            continue;
        }

        if ($i > $textStart) {
            $chunk = substr($contents, $textStart, $i - $textStart);
            if (trim($chunk, " \t\r\n") === '') {
                foreach (xmllint_newline_tokens($chunk) as $nt) {
                    $tokens[] = $nt;
                }
            } else {
                $tokens[] = array('type' => 'text', 'text' => $chunk);
            }
        }

        if (substr($contents, $i, 4) === '<!--') {
            $end = strpos($contents, '-->', $i + 4);
            $end = ($end === false) ? $n : $end + 3;
            $tokens[] = array('type' => 'verbatim', 'text' => substr($contents, $i, $end - $i));
            $i = $end;
        } elseif (substr($contents, $i, 9) === '<![CDATA[') {
            $end = strpos($contents, ']]>', $i + 9);
            $end = ($end === false) ? $n : $end + 3;
            $tokens[] = array('type' => 'verbatim', 'text' => substr($contents, $i, $end - $i));
            $i = $end;
        } elseif (substr($contents, $i, 2) === '<?') {
            $end = strpos($contents, '?>', $i + 2);
            $end = ($end === false) ? $n : $end + 2;
            $tokens[] = array('type' => 'verbatim', 'text' => substr($contents, $i, $end - $i));
            $i = $end;
        } elseif (substr($contents, $i, 2) === '<!') {
            /* DOCTYPE / markup declaration - naive scan to next '>' */
            $end = strpos($contents, '>', $i + 2);
            $end = ($end === false) ? $n : $end + 1;
            $tokens[] = array('type' => 'verbatim', 'text' => substr($contents, $i, $end - $i));
            $i = $end;
        } else {
            /* regular tag - scan to the matching unquoted '>' */
            $j = $i + 1;
            $quote = null;
            while ($j < $n) {
                $ch = $contents[$j];
                if ($quote !== null) {
                    if ($ch === $quote) {
                        $quote = null;
                    }
                } elseif ($ch === '"' || $ch === "'") {
                    $quote = $ch;
                } elseif ($ch === '>') {
                    $j++;
                    break;
                }
                $j++;
            }

            $tagText = substr($contents, $i, $j - $i);
            $tagTokens = xmllint_tag_tokens($tagText);
            if ($tagTokens === null) {
                $tokens[] = array('type' => 'verbatim', 'text' => $tagText);
            } else {
                foreach ($tagTokens as $tt) {
                    $tokens[] = $tt;
                }
            }
            $i = $j;
        }

        $textStart = $i;
    }

    if ($textStart < $n) {
        $chunk = substr($contents, $textStart, $n - $textStart);
        if (trim($chunk, " \t\r\n") === '') {
            foreach (xmllint_newline_tokens($chunk) as $nt) {
                $tokens[] = $nt;
            }
        } else {
            $tokens[] = array('type' => 'text', 'text' => $chunk);
        }
    }

    return $tokens;
}

/*
 * Pretty-printed render: one $indentUnit per element-nesting level, no other inserted whitespace beyond the syntactically required space before each attribute, and a bare LF per 'newline' token
 */
function xmllint_render($tokens, $indentUnit)
{
    $out = '';
    $lastType = 'newline';
    $depth = 0;
    $closingTag = false;
    $inTag = false;

    foreach ($tokens as $t) {
        if ($t['type'] === 'tag_open' && $t['closing']) {
            $depth--;
        }

        if ($t['type'] !== 'newline' && $lastType === 'newline') {
            $indentDepth = ($inTag && $t['type'] !== 'tag_end') ? $depth + 1 : $depth;
            $out .= str_repeat($indentUnit, $indentDepth);
        }

        switch ($t['type']) {
        case 'tag_open':
            $out .= '<' . ($t['closing'] ? '/' : '') . $t['name'];
            $closingTag = $t['closing'];
            $inTag = true;
            break;
        case 'attr':
            $sep = ($lastType === 'newline') ? '' : ' ';
            $out .= $sep . $t['name'] . '=' . $t['quote'] . $t['value'] . $t['quote'];
            break;
        case 'tag_end':
            $out .= $t['selfClose'] ? '/>' : '>';
            if (!$t['selfClose'] && !$closingTag) {
                $depth++;
            }
            $inTag = false;
            break;
        case 'text':
        case 'verbatim':
            $out .= $t['text'];
            break;
        case 'newline':
            /* strip blank lines: a newline right after another newline is dropped */
            if ($lastType === 'newline') {
                break;
            }
            $out .= "\n";
            break;
        }
        $lastType = $t['type'];
    }
    return $out;
}

function bsxmlfmt_usage()
{
    fwrite(STDERR, "usage: php bsxmlfmt.php [--indent <n|tab>] <filename>\n");
    exit(1);
}

$filename = null;
$indentArg = 'tab';

$args = array_slice($argv, 1);
$argCount = count($args);
$i = 0;
while ($i < $argCount) {
    if ($args[$i] === '--indent') {
        if ($i + 1 >= $argCount) {
            bsxmlfmt_usage();
        }
        $indentArg = $args[$i + 1];
        $i += 2;
        continue;
    }
    if ($filename !== null) {
        bsxmlfmt_usage();
    }
    $filename = $args[$i];
    $i++;
}

if ($filename === null) {
    bsxmlfmt_usage();
}

if ($indentArg === 'tab') {
    $indentUnit = "\t";
} elseif (ctype_digit($indentArg)) {
    $indentUnit = str_repeat(' ', (int) $indentArg);
} else {
    fwrite(STDERR, "bsxmlfmt: --indent must be 'tab' or a number of spaces, got: $indentArg\n");
    exit(1);
}

if (!is_file($filename)) {
    fwrite(STDERR, "bsxmlfmt: no such file: $filename\n");
    exit(1);
}

$contents = file_get_contents($filename);
if ($contents === false) {
    fwrite(STDERR, "bsxmlfmt: unable to read file: $filename\n");
    exit(1);
}

$tokens = xmllint_lex($contents);
$result = xmllint_render($tokens, $indentUnit);

if (file_put_contents($filename, $result) === false) {
    fwrite(STDERR, "bsxmlfmt: unable to write file: $filename\n");
    exit(1);
}

exit(0);
