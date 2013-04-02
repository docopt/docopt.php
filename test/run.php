<?php
$basePath = __DIR__;
require $basePath.'/../src/docopt.php';
require_once 'PHPUnit/Autoload.php';

require $basePath.'/lib/PythonPortedTest.php';
require $basePath.'/lib/LanguageAgnosticTest.php';

$options = array(
    'py'=>getenv('DOCOPT_PYTHON_PATH'),
    'filter'=>null,
);
$options = array_merge(
    $options,
    getopt('', array('py:', 'filter:'))
);

if (!$options['py']) {
    die(
        "Please ensure the --py option or the DOCOPT_PYTHON_PATH environment\n".
        "variable point to the path of the python port"
    );
}

$suite = new PHPUnit_Framework_TestSuite();
$suite->addTest(new PHPUnit_Framework_TestSuite('Docopt\Test\PythonPortedTest'));
$suite->addTest(Docopt\Test\LanguageAgnosticTest::createSuite($options['py'].'/testcases.docopt'));

$runner = new PHPUnit_TextUI_TestRunner();
$runner->doRun($suite, array(
    'filter'=>$options['filter'],
    'strict'=>true,
));
