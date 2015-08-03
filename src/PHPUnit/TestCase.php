<?php
namespace Docopt\PHPUnit;

class TestCase extends \PHPUnit_Framework_TestCase
{
    function getHeredocExtractor()
    {
        return new HeredocExtractor;
    }

    function runDocopt($args, $usage, $flags)
    {
        $flags = $flags ?: [];
        $flags['exit'] = false;

        $handler = new \Docopt\Handler($flags);
        $response = $handler->handle($usage, $args);
        return $response;
    }

    function groupOptions($options)
    {
        $groups = [
            'word'=>[],
            'short'=>[],
            'long'=>[],
            'flag'=>[],
            'arg'=>[],
        ];
        foreach ($options as $k=>$v) {
            $isLong  = strpos($k, '--');
            $isShort = !$isLong && strpos($k, '-');

            if ($isLong || $isShort) {
                if (is_bool($v)) {
                    $groups['flag'][$k] = $v;
                }
            }
            if (is_int($v)) {
                $groups['counted'][$k] = $v;
            }
            if ($isLong) {
                $groups['long'][$k] = $v;
            }
            elseif ($isShort) {
                $groups['short'][$k] = $v;
            }
            elseif ($k[0] == '<' && $k[strlen($k)-1] == '>') {
                $groups['arg'][$k] = $v;
            }
            else {
                $groups['word'][$k] = $v;
            }
        }
        return $groups;
    }

    function ensureDocoptSuccess($response)
    {
        if ($response->status !== 0) {
            $msg = "Docopt failed with status {$response->status}";
            $this->fail($msg);
        }
    }

    /**
     * Asserts that the output of the $usage program is equal to $expected.
     *
     * $doc = "Usage: pants [-abcd] [<a>]
     * $this->assertOptions(['-a'=>true, '-b'=>true], ['-abc'], $doc); // pass
     * $this->assertOptions(['-a'=>true, '-b'=>true], ['-a']  , $doc); // fail
     * $this->assertOptions(['<a>'=>'yep'], ['yep'], $doc);            // pass
     */
    function assertOptions($expected, array $args, $usage, $flags=null)
    {
        $response = $this->runDocopt($args, $usage, $flags);
        $this->assertResponseOptions($expected, $response);
    }

    function assertResponseOptions($expected, \Docopt\Response $response)
    {
        $this->ensureDocoptSuccess($response);
        $this->assertEquals($expected, $response->args);
    }

    function assertFlags($expected, array $args, $usage, $flags=null)
    {
        $response = $this->runDocopt($args, $usage, $flags);
        $this->assertResponseFlags($expected, $response);
    }

    function assertResponseFlags($expected, \Docopt\Response $response)
    {
        $this->ensureDocoptSuccess($response);
        $grouped = $this->groupOptions($response->args);
        $this->assertActive($expected, $grouped['flags']);
    }
    
    /**
     * Asserts that word options (docopt options without <> or --) are set in
     * the option list
     *
     * $doc = "Usage: pants [foo] [bar]";
     * $this->assertOptionsWords(['foo'], ['foo'], $doc);               // pass
     * $this->assertOptionsWords(['foo', 'bar'], ['foo', 'bar'], $doc); // pass
     * $this->assertOptionsWords(['foo'], ['foo', 'bar'], $doc);        // fail
     * $this->assertOptionsWords(['foo', 'bar'], ['foo'], $doc);        // fail
     *
     * @param $expected array  Each word which must be set to true in the result
     * @param $args     array  List of arguments to be interpreted by the usage program
     * @param $usage    string The usage program to test
     * @param $flags    array  Flags passed to the \Docopt\Handler constructor
     */
    function assertWords($expected, array $args, $usage, $flags=null)
    {
        $response = $this->runDocopt($args, $usage, $flags);
        $this->assertResponseWords($expected, $response);
    }

    function assertResponseWords($expected, \Docopt\Response $response)
    {
        $this->ensureDocoptSuccess($response);
        $grouped = $this->groupOptions($response->args);
        $this->assertActive($expected, $grouped['word']);
    }
 
    /**
     * Asserts that the output of the $usage program is intersected by $expected.
     *
     * $doc = "Usage: pants [-abcd] [<a>]
     * $this->assertOptionsContains(['-a'=>true, '-b'=>true], '-abc', $doc); // pass
     * $this->assertOptionsContains(['-a'=>true, '-b'=>true], '-a'  , $doc); // fail
     * $this->assertOptionsContains(['<a>'=>'yep'], 'yep', $doc);            // pass
     */
    function assertOptionSubset($expected, array $args, $usage, $flags=null)
    {
        if (!$expected) {
            throw new \InvalidArgumentException("Expected was empty");
        }
        $response = $this->runDocopt($args, $usage, $flags);
        $this->assertResponseOptionSubset($expected, $response);
    }

    function assertResponseOptionSubset($expected, \Docopt\Response $response)
    {
        $this->ensureDocoptSuccess($response);
        $this->assertArraySubset($expected, $response->args);
    }

    function assertActive($expected, $group)
    {
        $active = [];
        foreach ($group as $k=>$v) {
            if ($v === true) {
                $active[] = $k;
            }
        }
        sort($expected);
        sort($active);
        $this->assertEquals($expected, $active);
    }
}
