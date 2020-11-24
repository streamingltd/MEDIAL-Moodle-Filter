<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Filter converting medial texts into images
 *
 * This filter uses the medial settings in Site admin > Appearance > HTML settings
 * and replaces medial texts with images.
 *
 * @package    filter
 * @subpackage medial
 * @see        medial_manager
 * @copyright  2010 David Mudrak <david@moodle.com>, 2020 MEDIAL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class filter_medial extends moodle_text_filter {

    /**
     * Internal cache used for replacing. Multidimensional array;
     * - dimension 1: language,
     * - dimension 2: theme.
     * @var array
     */
    protected static $medialtexts = array();

    /**
     * Internal cache used for replacing. Multidimensional array;
     * - dimension 1: language,
     * - dimension 2: theme.
     * @var array
     */
    protected static $medialimgs = array();

    /**
     * Apply the filter to the text
     *
     * @see filter_manager::apply_filter_chain()
     * @param string $text to be processed by the text
     * @param array $options filter options
     * @return string text after processing
     */
    public function filter($text, array $options = array()) {

        if (!isset($options['originalformat'])) {
            // If the format is not specified, we are probably called by {@see format_string()}.
            // In that case, it would be dangerous to replace text with the image because it could
            // be stripped. therefore, we do nothing.
            return $text;
        }
        if (in_array($options['originalformat'], explode(',', get_config('filter_medial', 'formats')))) {
            return $this->replace_medials($text);
        }
        return $text;
    }

    /**
     * Replace medials found in the text with their images
     *
     * @param string $text to modify
     * @return string the modified result
     */
    protected function replace_medials($text) {
        global $CFG, $OUTPUT, $PAGE;

        // Detect all zones that we should not handle (including the nested tags).
        $processing = preg_split('/(<\/?(?:span|script)[^>]*>)/is', $text, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        // Initialize the results.
        $resulthtml = "";
        $exclude = 0;

        // Define the patterns that mark the start of the forbidden zones.
        $excludepattern = array('/^<script/is', '/^<span[^>]+class="nolink[^"]*"/is');

        // Loop through the fragments.
        foreach ($processing as $fragment) {
            // If we are not ignoring, we MUST test if we should.
            if ($exclude == 0) {
                foreach ($excludepattern as $exp) {
                    if (preg_match($exp, $fragment)) {
                        $exclude = $exclude + 1;
                        break;
                    }
                }
            }

            if ($exclude > 0) {
                // If we are ignoring the fragment, then we must check if we may have reached the end of the zone.
                if (strpos($fragment, '</span') !== false || strpos($fragment, '</script') !== false) {
                    $exclude -= 1;
                    // This is needed because of a double increment at the first element.
                    if ($exclude == 1) {
                        $exclude -= 1;
                    }
                } else if (strpos($fragment, '<span') !== false || strpos($fragment, '<script') !== false) {
                    // If we find a nested tag we increase the exclusion level.
                    $exclude = $exclude + 1;
                }
            } else if ((strpos($fragment, '<span') === false ||
                       strpos($fragment, '</span') === false) && strpos($fragment, '<a') !== false) {
                // This is the meat of the code - this is run every time.
                // This code only runs for fragments that are not ignored (including the tags themselves).

                $pattern = $CFG->wwwroot."/mod/helixmedia/launch.php";
                $pp = strpos($fragment, $pattern);
                if ($pp !== false) {
                    $lp = strpos($fragment, "&amp;l=");
                    $ep = strpos($fragment, "\"", $lp);
                    $url = substr($fragment, $pp, $ep - $pp);
                    $lid = substr($fragment, $lp + 7, $ep - ($lp + 7));

                    $fragment = "<iframe style=\"overflow:hidden;border:0px none;background:#ffffff;width:680px;height:570px;\"".
                        " src=\"".$url."\" id=\"hmlvid-".$lid."\" allowfullscreen=\"true\" webkitallowfullscreen=\"true\"".
                        " mozallowfullscreen=\"true\"></iframe>";
                }
            }
            $resulthtml .= $fragment;
        }

        return $resulthtml;
    }
}
