<?php
$basePath = __DIR__;
$testPath = __DIR__.'/test';
require $basePath.'/vendor/autoload.php';

require $testPath.'/lib/TestCase.php';
require $testPath.'/lib/LanguageAgnosticTest.php';
require $testPath.'/lib/PythonPortedTest.php';

$options = array(
    'filter'=>null,
);
$options = array_merge(
    $options,
    getopt('', array('filter:'))
);

$pyTestFile = $basePath.'/py/testcases.docopt';
if (!file_exists($pyTestFile)) {
    die("Please ensure you have loaded the git submodules\n");
}

$suite = new PHPUnit_Framework_TestSuite();
$suite->addTest(new PHPUnit_Framework_TestSuite('Docopt\Test\PythonPortedTest'));
$suite->addTest(Docopt\Test\LanguageAgnosticTest::createSuite($pyTestFile));

$runner = new PHPUnit_TextUI_TestRunner();
$runner->doRun($suite, array(
    'filter'=>$options['filter'],
    'strict'=>true,
));
