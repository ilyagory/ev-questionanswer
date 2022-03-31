<?php

namespace App\Util;

class Html
{
    static function nl2p(string $text): string
    {
        $paragraphs = '';

        foreach (explode("\n", $text) as $line) {
            if (trim($line)) {
                $paragraphs .= '<p>' . $line . '</p>';
            }
        }

        return $paragraphs;
    }
    
    static function humanFilesize($val){
            $val = (int)trim($val);
            $last = strtolower($val[strlen($val) - 1]);
            switch ($last) {
                // The 'G' modifier is available
                case 'g':
                    $val *= 1024;
                case 'm':
                    $val *= 1024;
                case 'k':
                    $val *= 1024;
            }

            return $val;
    }
}