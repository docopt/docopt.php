<?php
namespace Docopt\Test;

class LanguageAgnosticTest extends TestCase
{
	public static function createSuite($testFile)
	{
		if (!file_exists($testFile)) {
			throw new \InvalidArgumentException("Test file $testFile does not exist");
        }
		
		$suite = [];
		
		$raw = file_get_contents($testFile);
		$raw = trim(preg_replace("/#.*$/m", "", $raw));
		if (strpos($raw, '"""')===0) {
			$raw = substr($raw, 3);
		}
		
		$idx = 1;
		foreach (explode('r"""', $raw) as $fixture) {
			if (!$fixture) {
				continue;
			}
			
			$name = '';
			$nIdx = 1;
			
			$parts = explode('"""', $fixture, 2);
            if (!isset($parts[1])) {
                throw new \Exception("Missing string close");
            }
            list ($doc, $body) = $parts;

			$cases = array();
			foreach (array_slice(explode('$', $body), 1) as $case) {
				$case = trim($case);
				list ($argv, $expect) = explode("\n", $case, 2);
				$expect = json_decode($expect, true);
				
				$argx = explode(' ', $argv, 2);
				$prog = $argx[0];
				$argv = isset($argx[1]) ? $argx[1] : "";
				
				$tName = $name ? ($name.$nIdx) : 'unnamed'.$idx;
                $test = new self($tName, $doc, $prog, $argv, $expect);
				$suite[] = $test;
				$idx++;
			}
		}
		
		return $suite;
	}

    /** @var string */
    public $name;

    /** @var string */
    private $doc;

    /** @var string */
    private $prog;

    /** @var string[] */
    private $argv;

    /** @var string[]|string */
    private $expect;

	public function __construct($name, $doc, $prog, $argv, $expect)
	{
		$this->doc = $doc;
		$this->name = $name;
		$this->prog = $prog;
		$this->argv = $argv;
		
		if ($expect == "user-error") {
			$expect = array('user-error');
        }
		
		$this->expect = $expect;
	}

	public function test()
    {
        $opt = null;

		try {
		    $opt = \Docopt::handle($this->doc, array('argv'=>$this->argv, 'exit'=>false));
		}
		catch (\Exception $ex) {
			// gulp
		}

		$found = null;
		if ($opt) {
		    if (!$opt->success) {
		        $found = array('user-error');
		    } elseif (empty($opt->args)) {
		        $found = array();
		    } else {
		        $found = $opt->args;
		    }
		}

        ksort($this->expect);
        array_walk_recursive($this->expect, function($item) { if (is_array($item)) ksort($item); });

        ksort($found);
        array_walk_recursive($found, function($item) { if (is_array($item)) ksort($item); });

        $this->assertEquals($this->expect, $found);
    }
}
