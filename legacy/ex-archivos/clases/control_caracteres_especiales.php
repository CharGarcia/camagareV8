<?php
class sanitize{

/*
     * Parameters:
     *     $string - The string to sanitize.
     *     $force_lowercase - Force the string to lowercase?
     *     $anal - If set to true, will remove all non-alphanumeric characters.
     */
    public function string_sanitize($string, $force_lowercase = false, $anal = false) {
        $strip = array("~", "`", "!", "#", "$", "^", "&", "*", "=", "+", "[", "{", "]",
                       "}", "|", ";", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
                       "â€”", "â€“", "<", ">", "?",'"');
        $clean = trim(str_replace($strip, "", strip_tags($string)));
        //$clean = preg_replace('/\s+/', "-", $clean);
        //$clean = ($anal) ? preg_replace("/[^a-zA-Z0-9]/", "", $clean) : $clean ;
        return ($force_lowercase) ?
            (function_exists('mb_strtolower')) ?
                mb_strtolower($clean, 'UTF-8') :
                strtolower($clean) :
            $clean;
    }
}
	
?>


