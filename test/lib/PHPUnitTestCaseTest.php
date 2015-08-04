<?php
namespace Docopt\Test;

class PHPUnitTestCaseTest extends TestCase
{
    /**
     * @dataProvider provider
     */
    function testTest($class, $expects)
    {
        // you copy the exact way that phpunit tests itself, and what do you
        // get? dodgy error messages.

        $test   = new $class;
        $result = $test->run();

        $this->assertEquals($expects['errors']  , $result->errorCount());
        $this->assertEquals($expects['failures'], $result->failureCount());
        $this->assertEquals(1, count($result));
        if (isset($expects['message'])) {
            $this->assertEquals($expects['message'], $test->getStatusMessage());
        }
    }

    function provider()
    {
        $out = [];
        foreach (get_declared_classes() as $class) {
            $rc = new \ReflectionClass($class);
            if ($rc->getFilename() == __FILE__ && $rc->name != __CLASS__) {
                $out[] = [$class, $class::expects()];
            }
        }
        return $out;
    }
}

class PHPUnitAssertWordsValid extends \Docopt\Extras\PHPUnit\TestCase
{
    static function expects() { return ['errors'=>0, 'failures'=>0]; }

    protected function runTest()
    {
        $doc = "Usage: prog [foo] [bar]";
        $this->assertWords(['foo'], ['foo'], $doc);
        $this->assertWords(['foo', 'bar'], ['foo', 'bar'], $doc);
    }
}

class PHPUnitAssertWordsInvalid extends \Docopt\Extras\PHPUnit\TestCase
{
    static function expects()
    {
        return ['errors'=>0, 'failures'=>1, 'message'=>"Docopt failed with status 1"];
    }

    protected function runTest()
    {
        $doc = "Usage: prog [foo] [bar]";
        $this->assertWords(['foo', 'bar'], ['foo', 'bar', 'baz'], $doc);
    }
}

class PHPUnitAssertWordsMissing extends \Docopt\Extras\PHPUnit\TestCase
{
    static function expects() { return ['errors'=>0, 'failures'=>1, 'message'=>"Docopt failed with status 1"]; }

    protected function runTest()
    {
        $doc = "Usage: prog [foo] [bar] baz";
        $this->assertWords(['foo', 'bar'], ['foo', 'bar'], $doc);
    }
}
