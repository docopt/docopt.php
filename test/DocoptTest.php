<?php
namespace Docopt\Test;

use Docopt\Required;
use Docopt\OneOrMore;
use Docopt\AnyOptions;
use Docopt\Argument;
use Docopt\Option;
use Docopt\Optional;
use Docopt\Either;
use Docopt\Response;
use Docopt\TokenStream;
use Docopt\Command;

class DocoptTest extends \PHPUnit_Framework_TestCase
{
    /**
     * The arguments from the docopt test file are the other way around.
     */
    public static function assertEquals($expected, $actual, $message = '', $delta = 0, $maxDepth = 10, $canonicalize = false, $ignoreCase = false)
    {
        $args = func_get_args();
        list($args[1], $args[0]) = array($args[0], $args[1]);
        return call_user_func_array(array('parent', 'assertEquals'), $args);
    }

    function testPatternFlat()
    {
        $this->assertEquals(
            (new Required(array(new OneOrMore(new Argument('N')), 
                        new Option('-a'), new Argument('M'))))->flat(),
            array(new Argument('N'), new Option('-a'), new Argument('M'))
        );
    }
    
    function testOption()
    {
        $this->assertEquals(Option::parse('-h'), new Option('-h'));
        $this->assertEquals(Option::parse('--help'), new Option(null, '--help'));
        $this->assertEquals(Option::parse('-h --help'), new Option('-h', '--help'));
        $this->assertEquals(Option::parse('-h, --help'), new Option('-h', '--help'));
        
        $this->assertEquals(Option::parse('-h TOPIC'), new Option('-h', null, 1));
        $this->assertEquals(Option::parse('--help TOPIC'), new Option(null, '--help', 1));
        $this->assertEquals(Option::parse('-h TOPIC --help TOPIC'), new Option('-h', '--help', 1));
        $this->assertEquals(Option::parse('-h TOPIC, --help TOPIC'), new Option('-h', '--help', 1));
        $this->assertEquals(Option::parse('-h TOPIC, --help=TOPIC'), new Option('-h', '--help', 1));

        $this->assertEquals(Option::parse('-h  Description...'), new Option('-h', null));
        $this->assertEquals(Option::parse('-h --help  Description...'), new Option('-h', '--help'));
        $this->assertEquals(Option::parse('-h TOPIC  Description...'), new Option('-h', null, 1));

        $this->assertEquals(Option::parse('    -h'), new Option('-h', null));

        $this->assertEquals(Option::parse('-h TOPIC  Descripton... [default: 2]'), new Option('-h', null, 1, '2'));
        $this->assertEquals(Option::parse('-h TOPIC  Descripton... [default: topic-1]'), new Option('-h', null, 1, 'topic-1'));
        $this->assertEquals(Option::parse('--help=TOPIC  ... [default: 3.14]'), new Option(null, '--help', 1, '3.14'));
        $this->assertEquals(Option::parse('-h, --help=DIR  ... [default: ./]'), new Option('-h', '--help', 1, "./"));
        $this->assertEquals(Option::parse('-h TOPIC  Descripton... [dEfAuLt: 2]'), new Option('-h', null, 1, '2'));
    }
    
    public function testOptionName()
    {
        $this->assertEquals((new Option('-h', null))->name, '-h');
        $this->assertEquals((new Option('-h', '--help'))->name, '--help');
        $this->assertEquals((new Option(null, '--help'))->name, '--help');
    }
    
    /**
     * @group faulty
     */
    public function testCommands()
    {
        $this->assertEquals($this->docopt('Usage: prog add', 'add')->args, array('add' => true));
        
        $this->assertEquals($this->docopt('Usage: prog [add]', '')->args, array('add' => false));
        $this->assertEquals($this->docopt('Usage: prog [add]', 'add')->args, array('add' => true));
        $this->assertEquals($this->docopt('Usage: prog (add|rm)', 'add')->args, array('add' => true, 'rm' => false));
        $this->assertEquals($this->docopt('Usage: prog (add|rm)', 'rm')->args, array('add' => false, 'rm' => true));
        $this->assertEquals($this->docopt('Usage: prog a b', 'a b')->args, array('a' => true, 'b' => true));
        
        // invalid input exit test
        $this->assertEquals($this->docopt('Usage: prog a b', 'b a')->status, 1);
    }

    public function testPrintableAndFormalUsage()
    {
        $doc = 
            "Usage: prog [-hv] ARG\n"
           ."       prog N M\n"
           ."\n"
           ."prog is a program"
        ;
        
        $this->assertEquals(\Docopt\printable_usage($doc), "Usage: prog [-hv] ARG\n       prog N M");
        $this->assertEquals(\Docopt\formal_usage(\Docopt\printable_usage($doc)), "( [-hv] ARG ) | ( N M )");
        $this->assertEquals(\Docopt\printable_usage("uSaGe: prog ARG\n\t \t\n bla"), "uSaGe: prog ARG");
    }
    
    /**
     * @group faulty
     */
    public function testArgv()
    {
        $o = new \ArrayIterator(array(new Option('-h'), new Option('-v', '--verbose'), new Option('-f', '--file', 1)));
        $ts = function($s) { return new TokenStream($s, 'ExitException'); };
        
        $this->assertEquals(\Docopt\parse_argv($ts(''), $o), array());
        $this->assertEquals(\Docopt\parse_argv($ts('-h'), $o), array(new Option('-h', null, 0, true)));
        $this->assertEquals(\Docopt\parse_argv($ts('-h --verbose'), $o), array(new Option('-h', null, 0, true), new Option('-v', '--verbose', 0, true)));
        $this->assertEquals(
            \Docopt\parse_argv($ts('-h --file f.txt'), $o),
            array(new Option('-h', null, 0, true), new Option('-f', '--file', 1, 'f.txt'))
        );
        $this->assertEquals(
            \Docopt\parse_argv($ts('-h --file f.txt arg'), $o),
            array(new Option('-h', null, 0, true),
             new Option('-f', '--file', 1, 'f.txt'),
             new Argument(null, 'arg')
            )
        );
        $this->assertEquals(
            \Docopt\parse_argv($ts('-h --file f.txt arg arg2'), $o),
            array(new Option('-h', null, 0, true),
             new Option('-f', '--file', 1, 'f.txt'),
             new Argument(null, 'arg'),
             new Argument(null, 'arg2')
            )
        );
        $this->assertEquals(
            \Docopt\parse_argv($ts('-h arg -- -v'), $o),
            array(
             new Option('-h', null, 0, true),
             new Argument(null, 'arg'),
             new Argument(null, '--'),
             new Argument(null, '-v')
            )
        );
    }
    
    public function testParsePattern()
    {
        $o = new \ArrayIterator(array(new Option('-h'), new Option('-v', '--verbose'), new Option('-f', '--file', 1)));
        $this->assertEquals(
            \Docopt\parse_pattern('[ -h ]', $o),
            new Required(new Optional(new Option('-h')))
        );
        
        $this->assertEquals(
            \Docopt\parse_pattern('[ ARG ... ]', $o),
            new Required(new Optional(new OneOrMore(new Argument('ARG'))))
        );
        $this->assertEquals(
            \Docopt\parse_pattern('[ -h | -v ]', $o),
            new Required(new Optional(
                new Either(new Option('-h'), new Option('-v', '--verbose'))
            ))
        );
        $this->assertEquals(
            \Docopt\parse_pattern('( -h | -v [ --file <f> ] )', $o),
            new Required(new Required(new Either(new Option('-h'), new Required(new Option('-v', '--verbose'), new Optional(new Option('-f', '--file', 1, null))))))
        );
        $this->assertEquals(
            \Docopt\parse_pattern('(-h|-v[--file=<f>]N...)', $o),
            new Required(new Required(new Either(new Option('-h'),
            new Required(new Option('-v', '--verbose'),
            new Optional(new Option('-f', '--file', 1, null)),
            new OneOrMore(new Argument('N'))))))
        );
        $this->assertEquals(
            \Docopt\parse_pattern('(N [M | (K | L)] | O P)', new \ArrayIterator(array())),
            new Required(new Required(new Either(new Required(new Argument('N'),
            new Optional(new Either(new Argument('M'), new Required(
            new Either(new Argument('K'), new Argument('L')))))),
            new Required(new Argument('O'), new Argument('P')))))
        );
        $this->assertEquals(\Docopt\parse_pattern('[ -h ] [N]', $o),
                       new Required(
            new Optional(new Option('-h')),
            new Optional(new Argument('N')))             
        );
        $this->assertEquals(
            \Docopt\parse_pattern('[options]', $o),
            new Required(new Optional(new AnyOptions()))
        );
        $this->assertEquals(\Docopt\parse_pattern('[options] A', $o),
            new Required(
            new Optional(new AnyOptions()),
            new Argument('A'))
        );
        $this->assertEquals(\Docopt\parse_pattern('-v [options]', $o),
                    new Required(new Option('-v', '--verbose'),
                             new Optional(new AnyOptions()))
        );
        $this->assertEquals(\Docopt\parse_pattern('ADD', $o), new Required(new Argument('ADD')));
        $this->assertEquals(\Docopt\parse_pattern('<add>', $o), new Required(new Argument('<add>')));
        $this->assertEquals(\Docopt\parse_pattern('add', $o), new Required(new Command('add')));
    }
    
    public function testOptionMatch()
    {
        $this->assertEquals(
            (new Option('-a'))->match(array(new Option('-a', null, 0, true))),
            array(true, array(), array(new Option('-a', null, 0, true)))
        );
        $this->assertEquals(
            (new Option('-a'))->match(array(new Option('-x'))),
            array(false, array(new Option('-x')), array())
        );
        $this->assertEquals(
            (new Option('-a'))->match(array(new Argument('N'))),
            array(false, array(new Argument('N')), array())
        );
        $this->assertEquals(
            (new Option('-a'))->match(array(new Option('-x'), new Option('-a'), new Argument('N'))),
                array(true, array(new Option('-x'), new Argument('N')), array(new Option('-a')))
        );
        $this->assertEquals(
            (new Option('-a'))->match(array(new Option('-a', null, 0, true), new Option('-a'))),
                array(true, array(new Option('-a')), array(new Option('-a', null, 0, true)))
        );
    }
    
    function testArgumentMatch()
    {
        $this->assertEquals((new Argument('N'))->match(array(new Argument(null, 9))),
                array(true, array(), array(new Argument('N', 9))));
        $this->assertEquals((new Argument('N'))->match(array(new Option('-x'))),
            array(false, array(new Option('-x')), array()));
        $this->assertEquals((new Argument('N'))->match(array(new Option('-x'),
                                    new Option('-a'),
                                    new Argument(null, 5))),
                array(true, array(new Option('-x'), new Option('-a')), array(new Argument('N', 5))));
        $this->assertEquals((new Argument('N'))->match(array(new Argument(null, 9), new Argument(null, 0))),
                array(true, array(new Argument(null, 0)), array(new Argument('N', 9))));
    }

    function testCommandMatch()
    {
        $this->assertEquals(
            (new Command('c'))->match(array(new Argument(null, 'c'))),
                array(true, array(), array(new Command('c', true)))
        );
        $this->assertEquals(
            (new Command('c'))->match(array(new Option('-x'))), 
            array(false, array(new Option('-x')), array())
        );
        $this->assertEquals((new Command('c'))->match(array(new Option('-x'),
                                   new Option('-a'),
                                   new Argument(null, 'c'))),
            array(true, array(new Option('-x'), new Option('-a')), array(new Command('c', true)))
        );
        $this->assertEquals(
            (new Either(new Command('add', false), new Command('rm', false)))->match(
                array(new Argument(null, 'rm'))),
            array(true, array(), array(new Command('rm', true)))
        );
    }

    function testOptionalMatch() 
    {
        $this->assertEquals((new Optional(new Option('-a')))->match(array(new Option('-a'))),
            array(true, array(), array(new Option('-a')))
        );
        $this->assertEquals((new Optional(new Option('-a')))->match(array()),
            array(true, array(), array())
        );
        $this->assertEquals((new Optional(new Option('-a')))->match(array(new Option('-x'))),
            array(true, array(new Option('-x')), array())
        );
        $this->assertEquals((new Optional(new Option('-a'), new Option('-b')))->match(array(new Option('-a'))),
            array(true, array(), array(new Option('-a')))
        );
        $this->assertEquals((new Optional(new Option('-a'), new Option('-b')))->match(array(new Option('-b'))),
            array(true, array(), array(new Option('-b')))
        );
        $this->assertEquals((new Optional(new Option('-a'), new Option('-b')))->match(array(new Option('-x'))),
            array(true, array(new Option('-x')), array())
        );
        $this->assertEquals((new Optional(new Argument('N')))->match(array(new Argument(null, 9))),
            array(true, array(), array(new Argument('N', 9)))
        );
        $this->assertEquals((new Optional(new Option('-a'), new Option('-b')))->match(
                    array(new Option('-b'), new Option('-x'), new Option('-a'))),
            array(true, array(new Option('-x')), array(new Option('-a'), new Option('-b')))
        );
    }
    
    function testRequiredMatch()
    {
        $this->assertEquals((new Required(new Option('-a')))->match(array(new Option('-a'))),
            array(true, array(), array(new Option('-a')))
        );
        $this->assertEquals(
            (new Required(new Option('-a')))->match(array()),
            array(false, array(), array())
        );
        $this->assertEquals(
            (new Required(new Option('-a')))->match(array(new Option('-x'))),
            array(false, array(new Option('-x')), array())
        );
        $this->assertEquals(
            (new Required(new Option('-a'), new Option('-b')))->match(array(new Option('-a'))),
            array(false, array(new Option('-a')), array())
        );
    }

    function testEitherMatch()
    {
        $this->assertEquals((new Either(new Option('-a'), new Option('-b')))->match(
                array(new Option('-a'))),
            array(true, array(), array(new Option('-a')))
        );
        $this->assertEquals((new Either(new Option('-a'), new Option('-b')))->match(
                array(new Option('-a'), new Option('-b'))),
            array(true, array(new Option('-b')), array(new Option('-a')))
        );
        $this->assertEquals((new Either(new Option('-a'), new Option('-b')))->match(
                array(new Option('-x'))),
            array(false, array(new Option('-x')), array())
        );
        $this->assertEquals((new Either(new Option('-a'), new Option('-b'), new Option('-c')))->match(
                array(new Option('-x'), new Option('-b'))),
            array(true, array(new Option('-x')), array(new Option('-b')))
        );
        $this->assertEquals((new Either(new Argument('M'),
                      new Required(new Argument('N'), new Argument('M'))))->match(
                                       array(new Argument(null, 1), new Argument(null, 2))),
            array(true, array(), array(new Argument('N', 1), new Argument('M', 2)))
        );
    }

    function testOneOrMoreMatch()
    {
        $this->assertEquals((new OneOrMore(new Argument('N')))->match(array(new Argument(null, 9))),
            array(true, array(), array(new Argument('N', 9)))
        );
        $this->assertEquals((new OneOrMore(new Argument('N')))->match(array()),
            array(false, array(), array())
        );
        $this->assertEquals((new OneOrMore(new Argument('N')))->match(array(new Option('-x'))),
            array(false, array(new Option('-x')), array())
        );
        $this->assertEquals((new OneOrMore(new Argument('N')))->match(
                array(new Argument(null, 9), new Argument(null, 8))),
            array(true, array(), array(new Argument('N', 9), new Argument('N', 8)))
        );
        $this->assertEquals((new OneOrMore(new Argument('N')))->match(
                array(new Argument(null, 9), new Option('-x'), new Argument(null, 8))),
            array(true, array(new Option('-x')), array(new Argument('N', 9), new Argument('N', 8)))
        );
        $this->assertEquals((new OneOrMore(new Option('-a')))->match(
                array(new Option('-a'), new Argument(null, 8), new Option('-a'))),
            array(true, array(new Argument(null, 8)), array(new Option('-a'), new Option('-a')))
        );
        $this->assertEquals((new OneOrMore(new Option('-a')))->match(array(new Argument(null, 8),
                                              new Option('-x'))),
            array(false, array(new Argument(null, 8), new Option('-x')), array())
        );
        $this->assertEquals((new OneOrMore(new Required(new Option('-a'), new Argument('N'))))->match(
                array(new Option('-a'), new Argument(null, 1), new Option('-x'),
                 new Option('-a'), new Argument(null, 2))),
            array(true, array(new Option('-x')),
                  array(new Option('-a'), new Argument('N', 1), new Option('-a'), new Argument('N', 2)))
        );
        $this->assertEquals((new OneOrMore(new Optional(new Argument('N'))))->match(array(new Argument(null, 9))),
            array(true, array(), array(new Argument('N', 9)))
        );
    }

    function testListArgumentMatch()
    {
        $this->assertEquals((new Required(new Argument('N'), new Argument('N')))->fix()->match(
                array(new Argument(null, '1'), new Argument(null, '2'))),
                        array(true, array(), array(new Argument('N', array('1', '2'))))
        );
        $this->assertEquals((new OneOrMore(new Argument('N')))->fix()->match(
              array(new Argument(null, '1'), new Argument(null, '2'), new Argument(null, '3'))),
                        array(true, array(), array(new Argument('N', array('1', '2', '3'))))
        );
        $this->assertEquals((new Required(new Argument('N'), new OneOrMore(new Argument('N'))))->fix()->match(
              array(new Argument(null, '1'), new Argument(null, '2'), new Argument(null, '3'))),
                        array(true, array(), array(new Argument('N', array('1', '2', '3'))))
        );
        $this->assertEquals((new Required(new Argument('N'), new Required(new Argument('N'))))->fix()->match(
                array(new Argument(null, '1'), new Argument(null, '2'))),
                        array(true, array(), array(new Argument('N', array('1', '2'))))
        );
    }

    function testBasicPatternMatching()
    {
        # ( -a N [ -x Z ] )
        $pattern = new Required(new Option('-a'), new Argument('N'),
                           new Optional(new Option('-x'), new Argument('Z')))
        ;
        # -a N
        $this->assertEquals($pattern->match(array(new Option('-a'), new Argument(null, 9))),
                array(true, array(), array(new Option('-a'), new Argument('N', 9)))
        );
        # -a -x N Z
        $this->assertEquals($pattern->match(array(new Option('-a'), new Option('-x'),
                              new Argument(null, 9), new Argument(null, 5))),
                array(true, array(), array(new Option('-a'), new Argument('N', 9),
                            new Option('-x'), new Argument('Z', 5)))
        );
        # -x N Z  # BZZ!
        $this->assertEquals($pattern->match(array(new Option('-x'),
                              new Argument(null, 9),
                              new Argument(null, 5))),
                array(false, array(new Option('-x'), new Argument(null, 9), new Argument(null, 5)), array())
        );
    }

    /**
     * @group faulty
     */
    function testFixPatternEither()
    {
        $this->assertEquals(
            (new Option('-a'))->either(), 
            new Either(new Required(new Option('-a')))
        );
        $this->assertEquals(
            (new Argument('A'))->either(), 
            new Either(new Required(new Argument('A')))
        );
        $this->assertEquals(
            (new Required(new Either(new Option('-a'), new Option('-b')),
                        new Option('-c')))->either(),
            new Either(new Required(new Option('-a'), new Option('-c')),
                       new Required(new Option('-b'), new Option('-c')))
        );
        $this->assertEquals(
            (new Optional(new Option('-a'),
                          new Either(new Option('-b'),
                          new Option('-c'))))->either(),
            new Either(new Required(new Option('-b'), new Option('-a')),
                       new Required(new Option('-c'), new Option('-a')))
        );
        $this->assertEquals(
            (new Either(new Option('-x'), new Either(new Option('-y'), new Option('-z'))))->either(),
            new Either(new Required(new Option('-x')), 
               new Required(new Option('-y')),
               new Required(new Option('-z')))
        );
        $this->assertEquals(
            (new OneOrMore(new Argument('N'), new Argument('M')))->either(),
            new Either(new Required(new Argument('N'), new Argument('M'),
                            new Argument('N'), new Argument('M')))
        );
    }

    /**
     * @group faulty
     */
    function testPatternFixRepeatingArguments()
    {
        $this->assertEquals((new Option('-a'))->fixRepeatingArguments(), new Option('-a'));
        $this->assertEquals((new Argument('N', null))->fixRepeatingArguments(), new Argument('N', null));
        $this->assertEquals((new Required(new Argument('N'),
                        new Argument('N')))->fixRepeatingArguments(),
                new Required(new Argument('N', array()), new Argument('N', array()))
        );
        $this->assertEquals((new Either(new Argument('N'),
                            new OneOrMore(new Argument('N'))))->fix(),
                new Either(new Argument('N', array()), new OneOrMore(new Argument('N', array())))
        );
    }

    function testSet()
    {
        $this->assertEquals(new Argument('N'), new Argument('N'));
        $this->assertEquals(
            array_unique(array(new Argument('N'), new Argument('N'))), 
            array(new Argument('N'))
        );
    }

    function testPatternFixIdentities1()
    {
        $pattern = new Required(new Argument('N'), new Argument('N'));
        $this->assertEquals($pattern->children[0], $pattern->children[1]);
        $this->assertNotSame($pattern->children[0], $pattern->children[1]);
        $pattern->fixIdentities();
        $this->assertSame($pattern->children[0], $pattern->children[1]);
    }

    function testPatternFixIdentities2()
    {
        $pattern = new Required(new Optional(new Argument('X'), new Argument('N')), new Argument('N'));
        $this->assertEquals($pattern->children[0]->children[1], $pattern->children[1]);
        $this->assertNotSame($pattern->children[0]->children[1], $pattern->children[1]);
        $pattern->fixIdentities();
        $this->assertSame($pattern->children[0]->children[1], $pattern->children[1]);
    }

    function testLongOptionsErrorHandling()
    {
        #    $this->setExpectedException('Docopt\LanguageError');
        #        $this->docopt('Usage: prog --non-existent', '--non-existent')
        #    $this->setExpectedException('Docopt\LanguageError');
        #        $this->docopt('Usage: prog --non-existent')
        $result = $this->docopt('Usage: prog', '--non-existent');
        $this->assertFalse($result->success);

        $result = $this->docopt("Usage: prog array(--version --verbose)\n\n
                      --version\n--verbose", '--ver');
        $this->assertFalse($result->success);
    }

    function testLongOptionsErrorHandlingPart2()
    {
        $this->setExpectedException('Docopt\LanguageError');
        $result = $this->docopt("Usage: prog --long\n\n--long ARG");
    }

    function testLongOptionsErrorHandlingPart3()
    {
        $result = $this->docopt("Usage: prog --long ARG\n\n--long ARG", '--long');
        $this->assertFalse($result->success);
    }

    function testLongOptionsErrorHandlingPart4()
    {
        $this->setExpectedException('Docopt\LanguageError');
        $result = $this->docopt("Usage: prog --long=ARG\n\n--long");
    }

    function testLongOptionsErrorHandlingPart5()
    {
        $result = $this->docopt("Usage: prog --long\n\n--long", '--long=ARG');
        $this->assertFalse($result->success);
    }
    
    
    function testShortOptionsErrorHandlingPart1()
    {
        $this->setExpectedException('Docopt\LanguageError');
        $this->docopt("Usage: prog -x\n\n-x  this\n-x  that");
    }
    
    
    function testShortOptionsErrorHandlingPart2()
    {
        $result = $this->docopt('Usage: prog', '-x');
        $this->assertFalse($result->success);
    }
    
    function testShortOptionsErrorHandlingPart3()
    {
        $this->setExpectedException('Docopt\LanguageError');
        $this->docopt("Usage: prog -o\n\n-o ARG");
    }
    
    function testShortOptionsErrorHandlingPart4()
    {
        $result = $this->docopt("Usage: prog -o ARG\n\n-o ARG", '-o');
        $this->assertFalse($result->success);
    }
    
    function testMatchingParenPart1()
    {
        $this->setExpectedException('Docopt\LanguageError');
        $this->docopt('Usage: prog [a [b]');
    }
    
    /**
     * @group faulty
     */
    function testMatchingParenPart2()
    {
        $this->setExpectedException('Docopt\LanguageError');
        $this->docopt('Usage: prog [a [b] ] c )');
    }

    function testAllowDoubleDash()
    {
        $this->assertEquals($this->docopt("usage: prog [-o] [--] <arg>\n\n-o",
                      '-- -o')->args, array('-o'=> false, '<arg>'=>'-o', '--'=>true)
        );
        $this->assertEquals($this->docopt("usage: prog [-o] [--] <arg>\n\n-o",
                      '-o 1')->args, array('-o'=>true, '<arg>'=>'1', '--'=>false)
        );
        $result = $this->docopt("usage: prog [-o] <arg>\n\n-o", '-- -o');
        $this->assertFalse($result->success);
    }
    
    function testDocopt()
    {
        $doc = "Usage: prog [-v] A

        -v  Be verbose.";
        
        $this->assertEquals($this->docopt($doc, 'arg')->args, array('-v'=>false, 'A'=>'arg'));
        $this->assertEquals($this->docopt($doc, '-v arg')->args, array('-v'=>true, 'A'=>'arg'));

        $doc = "Usage: prog [-vqr] [FILE]
                  prog INPUT OUTPUT
                  prog --help

        Options:
          -v  print status messages
          -q  report only file names
          -r  show all occurrences of the same error
          --help

        ";
        $a = $this->docopt($doc, '-v file.py');
        $this->assertEquals($a->args, array('-v'=>true, '-q'=>false, '-r'=>false, '--help'=>false,
                     'FILE'=>'file.py', 'INPUT'=>null, 'OUTPUT'=>null));

        $a = $this->docopt($doc, '-v');
        $this->assertEquals($a->args, array('-v'=>true, '-q'=>false, '-r'=>false, '--help'=>false,
                     'FILE'=>null, 'INPUT'=>null, 'OUTPUT'=>null));

        $result = $this->docopt($doc, '-v input.py output.py');
        $this->assertFalse($result->success);

        $result = $this->docopt($doc, '--fake');
        $this->assertFalse($result->success);

        $result = $this->docopt($doc, '--hel');
        $this->assertTrue($result['--help']);
    }

    function testLanguageErrors()
    {
        $this->setExpectedException('Docopt\LanguageError');
        $this->docopt('no usage with colon here');
    }
    
    function testLanguageErrorsPart2()
    {
        $this->setExpectedException('Docopt\LanguageError');
        $this->docopt("usage: here \n\n and again usage: here");
    }

    function testIssue40()
    {
        $result = $this->docopt('usage: prog --help-commands | --help', '--help');
        $this->assertTrue($result['--help']);
        
        $this->assertEquals($this->docopt('usage: prog --aabb | --aa', '--aa')->args, array('--aabb'=>false,
                                                               '--aa'=>true));
    }

    /**
     * @group faulty
     */
    function testCountMultipleFlags()
    {
        $this->assertEquals($this->docopt('usage: prog [-v]', '-v')->args, array('-v'=>true));
        $this->assertEquals($this->docopt('usage: prog [-vv]', '')->args, array('-v'=>0));
        $this->assertEquals($this->docopt('usage: prog [-vv]', '-v')->args, array('-v'=>1));
        $this->assertEquals($this->docopt('usage: prog [-vv]', '-vv')->args, array('-v'=>2));
        
        $result = $this->$this->docopt('usage: prog [-vv]', '-vvv');
        $this->assertFalse($result->success);
        
        $this->assertEquals($this->docopt('usage: prog [-v | -vv | -vvv]', '-vvv')->args, array('-v'=>3));
        $this->assertEquals($this->docopt('usage: prog -v...', '-vvvvvv')->args, array('-v'=>6));
        $this->assertEquals($this->docopt('usage: prog [--ver --ver]', '--ver --ver')->args, array('--ver'=>2));
    }
    
    /**
     * @group faulty
     */
    function testCountMultipleCommands()
    {
        $this->assertEquals($this->docopt('usage: prog [go]', 'go')->args, array('go'=>true));
        $this->assertEquals($this->docopt('usage: prog [go go]', '')->args, array('go'=>0));
        $this->assertEquals($this->docopt('usage: prog [go go]', 'go')->args, array('go'=>1));
        $this->assertEquals($this->docopt('usage: prog [go go]', 'go go')->args, array('go'=>2));
        
        $result = $this->$this->docopt('usage: prog [go go]', 'go go go');
        $this->assertFalse($result->success);
        
        $this->assertEquals($this->docopt('usage: prog go...', 'go go go go go')->args, array('go'=>5));
    }


    function testAnyOptionsParameter()
    {
        $result = $this->docopt('usage: prog [options]', '-foo --bar --spam=eggs');
        $this->assertFalse($result->success);
        
    #    $this->assertEquals($this->docopt('usage: prog [options]', '-foo --bar --spam=eggs',
    #                  any_options=true), array('-f'=>true, '-o'=>2,
    #                                         '--bar'=>true, '--spam'=>'eggs'}
        $result = $this->docopt('usage: prog [options]', '--foo --bar --bar');
        $this->assertFalse($result->success);
        
    #    $this->assertEquals($this->docopt('usage: prog [options]', '--foo --bar --bar',
    #                  any_options=true), array('--foo'=>true, '--bar'=>2}
        $result = $this->docopt('usage: prog [options]', '--bar --bar --bar -ffff');
        $this->assertFalse($result->success);
        
    #    $this->assertEquals($this->docopt('usage: prog [options]', '--bar --bar --bar -ffff',
    #                  any_options=true), array('--bar'=>3, '-f'=>4}
        $result = $this->docopt('usage: prog [options]', '--long=arg --long=another');
        $this->assertFalse($result->success);
        
    #    $this->assertEquals($this->docopt('usage: prog [options]', '--long=arg --long=another',
    #                  any_options=true), array('--long'=>['arg', 'another']}
    }


    #def test_options_shortcut_multiple_commands():
    #    # any_options is disabled
    #    $this->assertEquals($this->docopt('usage: prog c1 [options] prog c2 [options]',
    #        'c2 -o', any_options=true), array('-o'=>true, 'c1'=>false, 'c2'=>true}
    #    $this->assertEquals($this->docopt('usage: prog c1 [options] prog c2 [options]',
    #        'c1 -o', any_options=true), array('-o'=>true, 'c1'=>true, 'c2'=>false}


    public function testOptionsShortcutDoesNotAddOptionsToPatternSecondTime()
    {
        $this->assertEquals($this->docopt("usage: prog [options] [-a]\n\n-a -b", '-a')->args,
                array('-a'=>true, '-b'=>false));
        
        $result = $this->docopt("usage: prog [options] [-a]\n\n-a -b", '-aa');
        $this->assertFalse($result->success);
    }

    /**
     * @group faulty
     */
    function testDefaultValueForPositionalArguments()
    {
        # disabled right now
        $this->assertEquals($this->docopt("usage: prog [<p>]\n\n<p>  [default: x]", '')->args,
                array('<p>'=>null));
        #       {'<p>'=>'x'}
        $this->assertEquals($this->docopt("usage: prog [<p>]...\n\n<p>  [default: x y]", '')->args,
                array('<p>'=>[]));
        #       {'<p>'=>['x', 'y']}
        $this->assertEquals($this->docopt("usage: prog [<p>]...\n\n<p>  [default: x y]", 'this')->args,
                array('<p>'=>['this']));
        #       {'<p>'=>['this']}
    }
    
    #def test_parse_defaults():
    #    $this->assertEquals(parse_defaults("""usage: prog
    #
    #                          -o, --option <o>
    #                          --another <a>  description
    #                                         [default: x]
    #                          <a>
    #                          <another>  description [default: y]"""),
    #           ([new Option('-o', '--option', 1, null),
    #             new Option(null, '--another', 1, 'x')],
    #            [new Argument('<a>', null),
    #             new Argument('<another>', 'y')])
    #
    #    doc = '''
    #    -h, --help  Print help message.
    #    -o FILE     Output file.
    #    --verbose   Verbose mode.'''
    #    $this->assertEquals(parse_defaults(doc)[0], [new Option('-h', '--help'),
    #                                      new Option('-o', null, 1),
    #                                      new Option(null, '--verbose')]

    /**
     * @group faulty
     */
    public function testOptionsFirst()
    {
        $this->assertEquals($this->docopt('usage: prog [--opt] [<args>...]',
                      '--opt this that')->args, array('--opt'=>true,
                                             '<args>'=>['this', 'that']));
        $this->assertEquals($this->docopt('usage: prog [--opt] [<args>...]',
                      'this that --opt')->args, array('--opt'=>true,
                                             '<args>'=>['this', 'that']));
        $this->assertEquals($this->docopt('usage: prog [--opt] [<args>...]',
                      'this that --opt',
                      array('optionsFirst'=>true))->args, array('--opt'=>false,
                                              '<args>'=>array('this', 'that', '--opt')));
    }
    
    public function testIssue68OptionsShortcutDoesNotIncludeOptionsInUsagePattern()
    {
        $args = $this->docopt("usage: prog [-ab] [options]\n\n-x\n-y", '-ax');
        $this->assertTrue($args['-a']);
        $this->assertFalse($args['-b']);
        $this->assertTrue($args['-x']);
        $this->assertFalse($args['-y']);
    }

    /**
     * @group faulty
     */
    public function testIssue85AnyOptionMultipleSubcommands()
    {
        $this->assertEquals(
            array('--loglevel' => '5', 'fail' => true, 'good' => false),
            $this->docopt("usage:\n  fs good [options]\n  fs fail [options]\n\nOptions:\n  --loglevel=<loglevel>\n",
                      'fail --loglevel 5')->args
        );
    }

    private function docopt($usage, $args='', $extra=array())
    {
        $extra = array_merge(array('exit'=>false, 'help'=>false), $extra);
        $handler = new \Docopt\Handler($extra);
        return call_user_func(array($handler, 'handle'), $usage, $args);
    }
}
