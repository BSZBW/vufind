<?php

/**
 * Lucene query syntax helper class.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindSearch\Backend\Solr;

/**
 * Lucene query syntax helper class.
 *
 * @category VuFind2
 * @package  Search
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class LuceneSyntaxHelper
{
    /**
     * Regular expression matching a SOLR range.
     *
     * @var string
     */
    const SOLR_RANGE_RE = '/(\[.+\s+TO\s+.+\])|(\{.+\s+TO\s+.+\})/';

    /**
     * Lookahead that detects whether or not we are inside quotes.
     *
     * @var string
     */
    protected static $insideQuotes = '(?=(?:[^\"]*+\"[^\"]*+\")*+[^\"]*+$)';

    /**
     * Force ranges to uppercase?
     *
     * @var bool
     */
    protected $caseSensitiveRanges;

    /**
     * Force boolean operators to uppercase? Set to true to make all Booleans
     * case-sensitive; false to make no Booleans case-sensitive; comma-separated
     * string to make only certain operators case sensitive.
     *
     * @var bool|string
     */
    protected $caseSensitiveBooleans;

    /**
     * All boolean operators supported by the class.
     *
     * @var array
     */
    protected $allBools = array('AND', 'OR', 'NOT');

    /**
     * Constructor.
     *
     * @param bool|string $csBools  Case sensitive Booleans setting
     * @param bool        $csRanges Case sensitive ranges setting
     */
    public function __construct($csBools = true, $csRanges = true)
    {
        $this->caseSensitiveBooleans = $csBools;
        $this->caseSensitiveRanges = $csRanges;
    }

    /// Public API

    /**
     * Return true if the search string contains boolean operators.
     *
     * @param string $searchString Search string
     *
     * @return bool
     */
    public function containsBooleans($searchString)
    {
        // Build a regular expression to detect booleans -- AND/OR/NOT surrounded
        // by whitespace, or NOT leading the query and followed by whitespace.
        $lookahead = self::$insideQuotes;
        $boolReg = '/((\s+(AND|OR|NOT)\s+)|^NOT\s+)' . $lookahead . '/';
        $checkString = $this->capitalizeCaseInsensitiveBooleans($searchString);
        return preg_match($boolReg, $checkString) ? true : false;
    }

    /**
     * Return true if the search string contains ranges.
     *
     * @param string $searchString Search string
     *
     * @return bool
     */
    public function containsRanges($searchString)
    {
        $rangeReg = self::SOLR_RANGE_RE;
        if (!$this->caseSensitiveRanges) {
            $rangeReg .= "i";
        }
        return preg_match($rangeReg, $searchString) ? true : false;
    }

    /**
     * Return true if the search string contains advanced Lucene syntax.
     *
     * @param string $searchString Search string
     *
     * @return bool
     */
    public function containsAdvancedLuceneSyntax($searchString)
    {
        // Check for various conditions that flag an advanced Lucene query:
        if ($searchString == '*:*') {
            return true;
        }

        // The following conditions do not apply to text inside quoted strings,
        // so let's just strip all quoted strings out of the query to simplify
        // detection.  We'll replace quoted phrases with a dummy keyword so quote
        // removal doesn't interfere with the field specifier check below.
        $searchString = preg_replace('/"[^"]*"/', 'quoted', $searchString);

        // Check for field specifiers:
        if (preg_match("/[^\s\\\]\:[^\s]/", $searchString)) {
            return true;
        }

        // Check for unescaped parentheses:
        $stripped = str_replace(array('\(', '\)'), '', $searchString);
        if (strstr($stripped, '(') && strstr($stripped, ')')) {
            return true;
        }

        // Check for ranges, booleans, wildcards and fuzzy matches:
        if ($this->containsRanges($searchString)
            || $this->containsBooleans($searchString)
            || strstr($searchString, '*') || strstr($searchString, '?')
            || strstr($searchString, '~')
        ) {
            return true;
        }

        // Check for boosts:
        if (preg_match('/[\^][0-9]+/', $searchString)) {
            return true;
        }

        return false;
    }

    /**
     * Return normalized input string.
     *
     * @param string $searchString Input search string
     *
     * @return string
     */
    public function normalizeSearchString($searchString)
    {
        $searchString = $this->prepareForLuceneSyntax($searchString);

        // Force boolean operators to uppercase if we are in a
        // case-insensitive mode:
        $searchString = $this->capitalizeCaseInsensitiveBooleans($searchString);

        // Adjust range operators if we are in a case-insensitive mode:
        if (!$this->caseSensitiveRanges) {
            $searchString = $this->capitalizeRanges($searchString);
        }
        return $searchString;
    }

    /**
     * Wrapper around capitalizeBooleans that accounts for the caseSensitiveBooleans
     * property of this class.
     *
     * @param string $string Search string
     *
     * @return string
     */
    public function capitalizeCaseInsensitiveBooleans($string)
    {
        return $this->capitalizeBooleans($string, $this->getBoolsToCap());
    }

    /**
     * Capitalize boolean operators.
     *
     * @param string $string Search string
     * @param array  $bools  Which booleans to capitalize (default = all)
     *
     * @return string
     */
    public function capitalizeBooleans($string, $bools = array('AND', 'OR', 'NOT'))
    {
        // Short-circuit if no Booleans were selected:
        if (empty($bools)) {
            return $string;
        }

        // Load the "inside quotes" lookahead so we can use it to prevent
        // switching case of Boolean reserved words inside quotes, since
        // that can cause problems in case-sensitive fields when the reserved
        // words are actually used as search terms.
        $lookahead = self::$insideQuotes;

        // Create standard conversions:
        $regs = $replace = array();
        foreach ($bools as $bool) {
            $regs[] = "/\s+{$bool}\s+{$lookahead}/i";
            $replace[] = ' ' . $bool . ' ';
        }

        // Special extra case for NOT:
        if (in_array('NOT', $bools)) {
            $regs[] = "/\(NOT\s+{$lookahead}/i";
            $replace[] = '(NOT ';
        }

        return trim(preg_replace($regs, $replace, $string));
    }

    /**
     * Capitalize range operator.
     *
     * @param string $string Search string
     *
     * @return string
     */
    public function capitalizeRanges($string)
    {
        // Load the "inside quotes" lookahead so we can use it to prevent
        // switching case of ranges inside quotes, since that can cause
        // problems in case-sensitive fields when the reserved words are
        // actually used as search terms.
        $lookahead = self::$insideQuotes;
        $regs = array("/(\[)([^\]]+)\s+TO\s+([^\]]+)(\]){$lookahead}/i",
            "/(\{)([^}]+)\s+TO\s+([^}]+)(\}){$lookahead}/i");
        $callback = array($this, 'capitalizeRangesCallback');
        return trim(preg_replace_callback($regs, $callback, $string));
    }

    /**
     * Are there any case-sensitive Boolean operators configured?
     *
     * @return bool
     */
    public function hasCaseSensitiveBooleans()
    {
        // If there are some Boolean operators that are not in the list
        // of operators that need to be auto-capitalized, then some of
        // the operators will exhibit case-sensitive behavior.
        return count($this->allBools) > count($this->getBoolsToCap());
    }

    /**
     * Are case-sensitive ranges configured?
     *
     * @return bool
     */
    public function hasCaseSensitiveRanges()
    {
        return $this->caseSensitiveRanges;
    }

    /// Internal API

    /**
     * Normalize fancy quotes in a query.
     *
     * @param string $input String to normalize
     *
     * @return string
     */
    protected function normalizeFancyQuotes($input)
    {
        // Normalize fancy quotes:
        $quotes = array(
            "\xC2\xAB"     => '"', // « (U+00AB) in UTF-8
            "\xC2\xBB"     => '"', // » (U+00BB) in UTF-8
            "\xE2\x80\x98" => "'", // ‘ (U+2018) in UTF-8
            "\xE2\x80\x99" => "'", // ’ (U+2019) in UTF-8
            "\xE2\x80\x9A" => "'", // ‚ (U+201A) in UTF-8
            "\xE2\x80\x9B" => "'", // ? (U+201B) in UTF-8
            "\xE2\x80\x9C" => '"', // “ (U+201C) in UTF-8
            "\xE2\x80\x9D" => '"', // ” (U+201D) in UTF-8
            "\xE2\x80\x9E" => '"', // „ (U+201E) in UTF-8
            "\xE2\x80\x9F" => '"', // ? (U+201F) in UTF-8
            "\xE2\x80\xB9" => "'", // ‹ (U+2039) in UTF-8
            "\xE2\x80\xBA" => "'", // › (U+203A) in UTF-8
        );
        return strtr($input, $quotes);
    }

    /**
     * Normalize wildcards in a query.
     *
     * @param string $input String to normalize
     *
     * @return string
     */
    protected function normalizeWildcards($input)
    {
        // Ensure wildcards are not at beginning of input
        return ((substr($input, 0, 1) == '*') || (substr($input, 0, 1) == '?'))
            ? substr($input, 1) : $input;
    }

    /**
     * Normalize parentheses in a query.
     *
     * @param string $input String to normalize
     *
     * @return string
     */
    protected function normalizeParens($input)
    {
        // Ensure all parens match
        //   Better: Remove all parens if they are not balanced
        //     -- dmaus, 2012-11-11
        $start = preg_match_all('/\(/', $input, $tmp);
        $end = preg_match_all('/\)/', $input, $tmp);
        return ($start != $end) ? str_replace(array('(', ')'), '', $input) : $input;
    }

    /**
     * Normalize boosts in a query.
     *
     * @param string $input String to normalize
     *
     * @return string
     */
    protected function normalizeBoosts($input)
    {
        // Ensure ^ is used properly
        //   Better: Remove all ^ if not followed by digits
        //     -- dmaus, 2012-11-11
        $cnt = preg_match_all('/\^/', $input, $tmp);
        $matches = preg_match_all('/[^^]+\^[0-9]/', $input, $tmp);
        return (($cnt) && ($cnt !== $matches))
            ? str_replace('^', '', $input) : $input;
    }

    /**
     * Normalize braces/brackets in a query.
     *
     * IMPORTANT: This should only be called on a string that has already been
     * cleaned up by normalizeBoosts().
     *
     * @param string $input String to normalize
     *
     * @return string
     */
    protected function normalizeBracesAndBrackets($input)
    {
        // Remove unwanted brackets/braces that are not part of range queries.
        // This is a bit of a shell game -- first we replace valid brackets and
        // braces with tokens that cannot possibly already be in the query (due
        // to the work of normalizeBoosts()).  Next, we remove all remaining
        // invalid brackets/braces, and transform our tokens back into valid ones.
        // Obviously, the order of the patterns/merges array is critically
        // important to get this right!!
        $patterns = array(
            // STEP 1 -- escape valid brackets/braces
            '/\[([^\[\]\s]+\s+TO\s+[^\[\]\s]+)\]/' .
            ($this->caseSensitiveRanges ? '' : 'i'),
            '/\{([^\{\}\s]+\s+TO\s+[^\{\}\s]+)\}/' .
            ($this->caseSensitiveRanges ? '' : 'i'),
            // STEP 2 -- destroy remaining brackets/braces
            '/[\[\]\{\}]/',
            // STEP 3 -- unescape valid brackets/braces
            '/\^\^lbrack\^\^/', '/\^\^rbrack\^\^/',
            '/\^\^lbrace\^\^/', '/\^\^rbrace\^\^/');
        $matches = array(
            // STEP 1 -- escape valid brackets/braces
            '^^lbrack^^$1^^rbrack^^', '^^lbrace^^$1^^rbrace^^',
            // STEP 2 -- destroy remaining brackets/braces
            '',
            // STEP 3 -- unescape valid brackets/braces
            '[', ']', '{', '}');
        return preg_replace($patterns, $matches, $input);
    }

    /**
     * Normalize various problems found in unquoted text within the query.
     *
     * @param string $input String to normalize
     *
     * @return string
     */
    protected function normalizeUnquotedText($input)
    {
        // Freestanding hyphens and slashes can cause problems:
        $lookahead = self::$insideQuotes;
        $input = preg_replace(
            '/(\s+[-\/]$|\s+[-\/]\s+|^[-\/]\s+)' . $lookahead . '/',
            ' ', $input
        );

        // A proximity of 1 is illegal and meaningless -- remove it:
        $input = preg_replace('/~1(\.0*)?$/', '', $input);
        $input = preg_replace('/~1(\.0*)?\s+' . $lookahead . '/', ' ', $input);

        // Remove empty parentheses outside of quotation marks -- these will
        // cause a fatal Solr error and should be ignored.
        $parenRegex = '/\(\s*\)' . $lookahead . '/';
        while (preg_match($parenRegex, $input)) {
            $input = preg_replace($parenRegex, '', $input);
        }

        return $input;
    }
   
    /**
     * Prepare input to be used in a SOLR query.
     *
     * Handles certain cases where the input might conflict with Lucene
     * syntax rules.
     *
     * @param string $input Input string
     *
     * @return string
     *
     * @todo Check if it is safe to assume $input to be an UTF-8 encoded string.
     */
    protected function prepareForLuceneSyntax($input)
    {
        $input = $this->normalizeFancyQuotes($input);

        // If the user has entered a lone BOOLEAN operator, convert it to lowercase
        // so it is treated as a word (otherwise it will trigger a fatal error):
        switch(trim($input)) {
        case 'OR':
            return 'or';
        case 'AND':
            return 'and';
        case 'NOT':
            return 'not';
        }

        // If the string consists only of control characters and/or BOOLEANs with no
        // other input, wipe it out entirely to prevent weird errors:
        $operators = array('AND', 'OR', 'NOT', '+', '-', '"', '&', '|');
        if (trim(str_replace($operators, '', $input)) == '') {
            return '';
        }

        // Translate "all records" search into a blank string
        if (trim($input) == '*:*') {
            return '';
        }

        // Standard normalization actions (order is significant):
        $input = $this->normalizeWildcards($input);
        $input = $this->normalizeParens($input);
        $input = $this->normalizeBoosts($input);
        $input = $this->normalizeBracesAndBrackets($input);
        $input = $this->normalizeUnquotedText($input);

        // Remove surrounding slashes and whitespace -- these serve no purpose
        // and can cause problems.
        $input = trim($input, '/ ');

        return $input;
    }

    /**
     * Convert the caseSensitiveBooleans property into an array for use with the
     * capitalizeBooleans function.
     *
     * @return array
     */
    protected function getBoolsToCap()
    {
        if ($this->caseSensitiveBooleans === false
            || $this->caseSensitiveBooleans === 0
            || $this->caseSensitiveBooleans === "0"
        ) {
            return $this->allBools;
        } else if ($this->caseSensitiveBooleans === true
            || $this->caseSensitiveBooleans === 1
            || $this->caseSensitiveBooleans === "1"
        ) {
            return array();
        }

        // Callback function to clean up configuration settings:
        $callback = function ($i) {
            return strtoupper(trim($i));
        };

        // Return all values from $this->allBools not found in the configuration:
        return array_values(
            array_diff(
                $this->allBools,
                array_map($callback, explode(',', $this->caseSensitiveBooleans))
            )
        );
    }

    /**
     * Callback helper function.
     *
     * @param array $match Matches as of preg_replace_callback()
     *
     * @return string
     *
     * @see self::capitalizeRanges
     *
     * @todo Check possible problem with umlauts/non-ASCII word characters
     */
    protected function capitalizeRangesCallback($match)
    {
        // Extract the relevant parts of the expression:
        $open = $match[1];         // opening symbol
        $close = $match[4];        // closing symbol
        $start = $match[2];        // start of range
        $end = $match[3];          // end of range

        // Is this a case-sensitive range?
        if (strtoupper($start) != strtolower($start)
            || strtoupper($end) != strtolower($end)
        ) {
            // Build a lowercase version of the range:
            $lower = $open . trim(strtolower($start)) . ' TO ' .
                trim(strtolower($end)) . $close;
            // Build a uppercase version of the range:
            $upper = $open . trim(strtoupper($start)) . ' TO ' .
                trim(strtoupper($end)) . $close;

            // Special case: don't create illegal timestamps!
            $timestamp = '/[0-9]{4}-[0-9]{2}-[0-9]{2}t[0-9]{2}:[0-9]{2}:[0-9]{2}z/i';
            if (preg_match($timestamp, $start) || preg_match($timestamp, $end)) {
                return $upper;
            }

            // Accept results matching either range:
            return '(' . $lower . ' OR ' . $upper . ')';
        } else {
            // Simpler case -- case insensitive (probably numeric) range:
            return $open . trim($start) . ' TO ' . trim($end) . $close;
        }
    }
}