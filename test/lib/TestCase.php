<?php
namespace Docopt\Test;

abstract class TestCase
{
    /**
     * @param string $usage
     * @param string[]|string $args
     */
    protected function docopt($usage, $args='', $extra=array())
    {
        $extra = array_merge(array('exit'=>false, 'help'=>false), $extra);
        $handler = new \Docopt\Handler($extra);
        return call_user_func(array($handler, 'handle'), $usage, $args);
    }

    protected function assertEquals($expected, $found) {
        if (serialize($expected) !== serialize($found)) {
            throw new ExpectationFailed($expected, $found, "expected equal, found not equal");
        }
    }

    protected function assertFalse($value) {
        if ($value !== false) {
            throw new ExpectationFailed(false, $value, "expected value to be false");
        }
    }

    protected function assertTrue($value) {
        if ($value !== true) {
            throw new ExpectationFailed(false, $value, "expected value to be true");
        }
    }

    protected function assertNotEquals($expected, $found) {
        if (serialize($expected) === serialize($found)) {
            throw new ExpectationFailed($expected, $found, "expected values to not be equal");
        }
    }

    protected function assertSame($expected, $found) {
        if ($expected !== $found) {
            throw new ExpectationFailed($expected, $found, "expected values to be the same instance");
        }
    }

    protected function assertNotSame($expected, $found) {
        if ($expected === $found) {
            throw new ExpectationFailed($expected, $found, "expected values not to be the same instance");
        }
    }

    protected function expectException($name, $impl) {
        $found = null;
        try {
            call_user_func($impl);
        } catch (\Exception $e) {
            $found = $e;
        }
        if ($found === null) {
            throw new ExpectationFailed($name, $found, "expected exception, but none thrown");
        } else if ((new \ReflectionClass($name))->getName() !== (new \ReflectionClass($found))->getName()) {
            throw new ExpectationFailed($name, $found, "exception did not match expected exception");
        }
    }
}

class ExpectationFailed extends \RuntimeException {
    public function __construct($expected, $value, $message) {
        parent::__construct($message);
        $this->expected = $expected;
        $this->value = $value;
    }
}
