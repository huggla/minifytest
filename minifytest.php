<?php
function minify_html($input) {
    if (trim($input) === "") return $input;

    // Remove extra white-space(s) between HTML attribute(s)
    $input = preg_replace_callback('#<([^\/\s<>!]+)(?:\s+([^<>]*?)\s*|\s*)(\/?)>#s', function($matches) {
        // Skip attributes containing JavaScript to avoid breaking event handlers
        if (preg_match('#\bon[a-z]+\s*=\s*[\'"][^\'"]*[\'"]#i', $matches[2])) {
            return '<' . $matches[1] . ' ' . trim($matches[2]) . $matches[3] . '>';
        }
        return '<' . $matches[1] . preg_replace('#([^\s=]+)(\=([\'"]?)(.*?)\3)?(\s+|$)#s', ' $1$2', $matches[2]) . $matches[3] . '>';
    }, str_replace("\r", "", $input));

    // Minify inline CSS declaration(s)
    if (strpos($input, ' style=') !== false) {
        $input = preg_replace_callback('#<([^<]+?)\s+style=([\'"])(.*?)\2(?=[\/\s>])#s', function($matches) {
            return '<' . $matches[1] . ' style=' . $matches[2] . minify_css($matches[3]) . $matches[2];
        }, $input);
    }
    if (strpos($input, '</style>') !== false) {
        $input = preg_replace_callback('#<style(.*?)>(.*?)</style>#is', function($matches) {
            return '<style' . $matches[1] . '>' . minify_css($matches[2]) . '</style>';
        }, $input);
    }

    // Minify inline JavaScript, but skip if already minified or marked with data-nominify
    if (strpos($input, '</script>') !== false) {
        $input = preg_replace_callback('#<script\b(?![^>]*\bdata-nominify\b)(.*?)>(.*?)</script>#is', function($matches) {
            // Check if JavaScript appears to be already minified
            $js = $matches[2];
            if (is_already_minified($js)) {
                return '<script' . $matches[1] . '>' . $js . '</script>';
            }
            return '<script' . $matches[1] . '>' . minify_js($matches[2]) . '</script>';
        }, $input);
    }

    return preg_replace(
        array(
            // Keep important white-space(s) after self-closing HTML tag(s)
            '#<(img|input|br)(>| .*?>)#s',
            // Remove a line break and two or more white-space(s) between tag(s)
            '#(<!--.*?-->)|(>)(?:\n*|\s{2,})(<)|^\s*|\s*$#s',
            '#(<!--.*?-->)|(?<!\>)\s+(<\/.*?>)|(<[^\/]*?>)\s+(?!\<)#s', // t+c || o+t
            '#(<!--.*?-->)|(<[^\/]*?>)\s+(<[^\/]*?>)|(<\/.*?>)\s+(<\/.*?>)#s', // o+o || c+c
            '#(<!--.*?-->)|(<\/.*?>)\s+(\s)(?!\<)|(?<!\>)\s+(\s)(<[^\/]*?\/?>)|(<[^\/]*?\/?>)\s+(\s)(?!\<)#s', // c+t || t+o || o+t
            '#(<!--.*?-->)|(<[^\/]*?>)\s+(<\/.*?>)#s', // empty tag
            '#<(img|input|br)(>| .*?>)<\/\1>#s', // reset previous fix
            '#(&nbsp;)&nbsp;(?![<\s])#', // clean up ...
            '#(?<=\>)(&nbsp;)(?=\<)#', // --ibid
            // Remove HTML comment(s) except IE conditionals
            '#\s*<!--(?!\[if\s).*?-->\s*|(?<!\>)\n+(?=\<[^!])#s'
        ),
        array(
            '<$1$2</$1>',
            '$1$2$3',
            '$1$2$3',
            '$1$2$3$4$5',
            '$1$2$3$4$5$6$7',
            '$1$2$3',
            '<$1$2',
            '$1 ',
            '$1',
            ''
        ),
    $input);
}

// Helper function to detect if JavaScript is already minified
function is_already_minified($code) {
    if (trim($code) === '') return false;
    // Calculate the ratio of non-whitespace characters
    $total_length = strlen($code);
    $non_whitespace = strlen(preg_replace('#\s+#', '', $code));
    $ratio = $non_whitespace / $total_length;
    // If > 90% of characters are non-whitespace, assume it's minified
    return $ratio > 0.9;
}

// CSS Minifier (unchanged, as it seems robust enough)
function minify_css($input) {
    if (trim($input) === "") return $input;
    return preg_replace(
        array(
            '#("(?:[^"\\\\ ]++|\\\\ .)*+"|\'(?:[^\'\\\\\\\\ ]++|\\\\ .)*+\')|/\*(?!\!)(?>.*?\*/)|^\s*|\s*$#s',
            '#("(?:[^"\\\\ ]++|\\\\ .)*+"|\'(?:[^\'\\\\\\\\ ]++|\\\\ .)*+\'|/\*(?>.*?\*/))|\s*+;\s*+(})\s*+|\s*+([*$~^|]?+=|[{};,>~]|\s(?![0-9\.])|!important\b)\s*+|([[(:])\s++|\s++([])])|\s++(:)\s*+(?!(?>[^{}"\' ]++|"(?:[^"\\\\ ]++|\\\\ .)*+"|\'(?:[^\'\\\\\\\\ ]++|\\\\ .)*+\')*+{)|^\s++|\s++\z|(\s)\s+#si',
            '#(?<=[\s:])(0)(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)#si',
            '#:(0\s+0|0\s+0\s+0\s+0)(?=[;\}]|\!important)#i',
            '#(background-position):0(?=[;\}])#si',
            '#(?<=[\s:,\-])0+\.(\d+)#s',
            '#(\/\*(?>.*?\*\/))|(?<!content\:)([\'"])([a-z_][a-z0-9\-_]*?)\2(?=[\s\{\}\];,])#si',
            '#(\/\*(?>.*?\*\/))|(\burl\()([\'"])([^\s]+?)\3(\))#si',
            '#(?<=[\s:,\-]\#)([a-f0-6]+)\1([a-f0-6]+)\2([a-f0-6]+)\3#i',
            '#(?<=[\{;])(border|outline):none(?=[;\}\!])#',
            '#(\/\*(?>.*?\*\/))|(^|[\{\}])(?:[^\s\{\}]+)\{\}#s'
        ),
        array(
            '$1',
            '$1$2$3$4$5$6$7',
            '$1',
            ':0',
            '$1:0 0',
            '.$1',
            '$1$3',
            '$1$2$4$5',
            '$1$2$3',
            '$1:0',
            '$1$2'
        ),
    $input);
}

// Improved JavaScript Minifier
function minify_js($input) {
    if (trim($input) === "") return $input;
    return preg_replace(
        array(
            // Remove comments (skip regex literals and template literals)
            '#("(?:[^"\\\\ ]++|\\\\ .)*+"|\'(?:[^\'\\\\ ]++|\\\\ .)*+\'|\/(?!\/)[^\n\r]*?\/[gimuy]*)\s*|\s*\/\*(?!\!|@cc_on)(?>[\s\S]*?\*\/)\s*|\s*(?<![\:\=])\/\/.*(?=[\n\r]|$)#',
            // Remove unnecessary whitespace, preserving regex and string literals
            '#("(?:[^"\\\\ ]++|\\\\ .)*+"|\'(?:[^\'\\\\ ]++|\\\\ .)*+\'|\/(?!\/)[^\n\r]*?\/[gimuy]*)|\s*([!%&*\(\)\-=+\[\]\{\}|;:,.<>?\/])\s*#s',
            // Remove trailing semicolon in blocks
            '#;+\}#',
            // Minify object attributes (e.g., {'foo': 'bar'} to {foo: 'bar'})
            '#([\{,])([\'])(\d+|[a-z_][a-z0-9_]*)\2(?=\:)#i',
            // Convert foo['bar'] to foo.bar
            '#([a-z0-9_\)\]])\[([\'"])([a-z_][a-z0-9_]*)\2\]#i'
        ),
        array(
            '$1',
            '$1$2',
            '}',
            '$1$3',
            '$1.$3'
        ),
    $input);
}
?>
