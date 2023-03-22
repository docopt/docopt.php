<?php
if (php_sapi_name() != 'cli') {
    throw new \Exception();
}

$basePath = __DIR__;
$testPath = __DIR__.'/test';
require $basePath.'/vendor/autoload.php';

require $testPath.'/lib/TestCase.php';
require $testPath.'/lib/LanguageAgnosticTest.php';
require $testPath.'/lib/PythonPortedTest.php';

function dump($mixed = null) {
  ob_start();
  var_dump($mixed);
  $content = ob_get_contents();
  ob_end_clean();
  return rtrim($content);
}

$pyTestFile = $basePath.'/py/testcases.docopt';
if (!file_exists($pyTestFile)) {
    die("Please ensure you have loaded the git submodules\n");
}

$verbose = false;
$dumpMode = 'serialize'; // or vardump

$suite = [];
$suite[] = new \Docopt\Test\PythonPortedTest();
$suite = array_merge($suite, \Docopt\Test\LanguageAgnosticTest::createSuite($pyTestFile));
$suite = array_merge($suite, \Docopt\Test\LanguageAgnosticTest::createSuite("$basePath/test/extra.docopt"));

$tests = 0;
$passed = 0;
$failed = 0;

$details = [];

foreach ($suite as $test) {
    $rc = new ReflectionClass($test);
    foreach ($rc->getMethods() as $method) {
        if (substr($method->getName(), 0, 4) !== "test") {
            continue;
        }

        $tests++;
        $name = substr($method->getName(), 4);
        if (!$name) $name = $test->name;

        try {
            $method->invoke($test);
            if ($verbose) {
                echo "PASS: {$rc->getName()} {$name}\n";
            }
            $passed++;

        } catch (\Docopt\Test\ExpectationFailed $e) {
            echo "FAIL: {$rc->getName()} {$name} {$e->getMessage()}\n";
            $details[] = [
                "suite" => $rc->getName(),
                "name" => $name,
                "message" => $e->getMessage(),
                "vardump" => [
                    "want" => dump($e->expected),
                    "got" => dump($e->value),
                ],
                "serialize" => [
                    "want" => serialize($e->expected),
                    "got" => serialize($e->value),
                ],
            ];
            $failed++;
        }
    }
}

if (count($details)) {
    echo "\n";
    echo "Failure details:\n";
    foreach ($details as $failure) {
        $dump = $failure[$dumpMode];
        echo "{$failure['suite']} {$failure['name']}\n";
        echo "message: {$failure['message']}\n";
        echo "want: {$dump['want']}\n";
        echo "got:  {$dump['got']}\n";
        echo "\n";
    }
}

echo "{$tests} test(s), {$passed} passed, {$failed} failed\n";

if ($failed > 0) {
    exit(2);
}
