<?php
namespace Docopt\Testing;

class HeredocExtractor
{
    const HEREDOC = 'heredoc';
    const NOWDOC  = 'nowdoc';

    private $heredocFileCache = [];

    function extractFileHeredocs($file)
    {
        if (!isset($this->heredocFileCache[$file])) {
            $contents = file_get_contents($file);
            $this->heredocFileCache[$file] =  $this->extractStringHeredocs($contents);
        }
        return $this->heredocFileCache[$file];
    }

    function extractStringHeredocs($string)
    {
        $tokens = token_get_all($string);

        $current = null;
        $heredocs = ['indexed'=>[], 'named'=>[]];

        foreach ($tokens as $token) {
            $token = (array) $token;
            if (!isset($token[1])) {
                $token = [9999, $token, null];
            }

            if ($current === null) {
                if ($token[0] == T_START_HEREDOC) {
                    $current = (object) ['start'=>$token, 'contents'=>null, 'end'=>null];
                }
            }
            else {
                if ($token[0] == T_END_HEREDOC) {
                    if (preg_match("~^\s*<<<\s*'~", $current->start[1])) {
                        $current->type = 'nowdoc';
                    } else {
                        $current->type = 'heredoc';
                    }
                    $current->end = $token;
                    $current->name = $token[1];
                    $heredocs['indexed'][] = $current;
                    $heredocs['named'][$token[1]][] = $current;
                    $current = null;
                }
                else {
                    $current->contents .= $token[1];
                }
            }
        }

        return $heredocs;
    }

    function extractFileHeredocByName($file, $name, $types=null)
    {
        $types = $types === null ? [self::HEREDOC, self::NOWDOC] : (array)$types;

        $fileDocs = $this->extractFileHeredocs($file);

        $out = null;
        if ($docs = tryget($fileDocs['named'][$name])) {
            if (($count = count($docs)) > 1) {
                throw new \RuntimeException("Can only extract heredoc by name if the name is unique, found $count for $name");
            }
            $out = $docs[0];
        }

        if (!$out) {
            throw new \InvalidArgumentException("Heredoc $name not found in $file");
        }
        elseif (!in_array($out->type, $types)) {
            throw new \InvalidArgumentException("Expected type(s) ".implode(', ', $types).", found ".$out->type);
        }

        return $out;
    }
}
