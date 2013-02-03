<?php
namespace Docopt\Test;

use Docopt\Required;
use Docopt\OneOrMore;
use Docopt\Argument;
use Docopt\Option;
use Docopt\Response;

class DocoptTest extends \PHPUnit_Framework_TestCase
{
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

    public function testIssue68OptionsShortcutDoesNotIncludeOptionsInUsagePattern()
    {
        $args = $this->docopt("usage: prog [-ab] [options]\n\n-x\n-y", '-ax');
        $this->assertTrue($args['-a']);
        $this->assertFalse($args['-b']);
        $this->assertTrue($args['-x']);
        $this->assertFalse($args['-y']);
    }

    public function testIssue85AnyOptionMultipleSubcommands()
    {
        $this->assertEquals(
            array('--loglevel' => '5', 'fail' => true, 'good' => false),
            $this->docopt('usage:\n  fs good [options]\n  fs fail [options]\n\nOptions:\n  --loglevel=<loglevel>\n',
                      'fail --loglevel 5')->args
        );
    }

    private function docopt($usage, $args)
    {
        $handler = new \Docopt\Handler(array('exit'=>false, 'help'=>false));
        return call_user_func(array($handler, 'handle'), $usage, $args);
    }
    
    

}

/*

def test_printable_and_formal_usage():
    doc = """
    Usage: prog [-hv] ARG
           prog N M

    prog is a program."""
    assert printable_usage(doc) == "Usage: prog [-hv] ARG\n           prog N M"
    assert formal_usage(printable_usage(doc)) == "( [-hv] ARG ) | ( N M )"
    assert printable_usage('uSaGe: prog ARG\n\t \t\n bla') == "uSaGe: prog ARG"


def test_parse_argv():
    o = [Option('-h'), Option('-v', '--verbose'), Option('-f', '--file', 1)]
    TS = lambda s: TokenStream(s, error=DocoptExit)
    assert parse_argv(TS(''), options=o) == []
    assert parse_argv(TS('-h'), options=o) == [Option('-h', null, 0, true)]
    assert parse_argv(TS('-h --verbose'), options=o) == \
            [Option('-h', null, 0, true), Option('-v', '--verbose', 0, true)]
    assert parse_argv(TS('-h --file f.txt'), options=o) == \
            [Option('-h', null, 0, true), Option('-f', '--file', 1, 'f.txt')]
    assert parse_argv(TS('-h --file f.txt arg'), options=o) == \
            [Option('-h', null, 0, true),
             Option('-f', '--file', 1, 'f.txt'),
             Argument(null, 'arg')]
    assert parse_argv(TS('-h --file f.txt arg arg2'), options=o) == \
            [Option('-h', null, 0, true),
             Option('-f', '--file', 1, 'f.txt'),
             Argument(null, 'arg'),
             Argument(null, 'arg2')]
    assert parse_argv(TS('-h arg -- -v'), options=o) == \
            [Option('-h', null, 0, true),
             Argument(null, 'arg'),
             Argument(null, '--'),
             Argument(null, '-v')]


def test_parse_pattern():
    o = [Option('-h'), Option('-v', '--verbose'), Option('-f', '--file', 1)]
    assert parse_pattern('[ -h ]', options=o) == \
               Required(Optional(Option('-h')))
    assert parse_pattern('[ ARG ... ]', options=o) == \
               Required(Optional(OneOrMore(Argument('ARG'))))
    assert parse_pattern('[ -h | -v ]', options=o) == \
               Required(Optional(Either(Option('-h'),
                                Option('-v', '--verbose'))))
    assert parse_pattern('( -h | -v [ --file <f> ] )', options=o) == \
               Required(Required(
                   Either(Option('-h'),
                          Required(Option('-v', '--verbose'),
                               Optional(Option('-f', '--file', 1, null))))))
    assert parse_pattern('(-h|-v[--file=<f>]N...)', options=o) == \
               Required(Required(Either(Option('-h'),
                              Required(Option('-v', '--verbose'),
                                  Optional(Option('-f', '--file', 1, null)),
                                     OneOrMore(Argument('N'))))))
    assert parse_pattern('(N [M | (K | L)] | O P)', options=[]) == \
               Required(Required(Either(
                   Required(Argument('N'),
                            Optional(Either(Argument('M'),
                                            Required(Either(Argument('K'),
                                                            Argument('L')))))),
                   Required(Argument('O'), Argument('P')))))
    assert parse_pattern('[ -h ] [N]', options=o) == \
               Required(Optional(Option('-h')),
                        Optional(Argument('N')))
    assert parse_pattern('[options]', options=o) == \
            Required(Optional(AnyOptions()))
    assert parse_pattern('[options] A', options=o) == \
            Required(Optional(AnyOptions()),
                     Argument('A'))
    assert parse_pattern('-v [options]', options=o) == \
            Required(Option('-v', '--verbose'),
                     Optional(AnyOptions()))
    assert parse_pattern('ADD', options=o) == Required(Argument('ADD'))
    assert parse_pattern('<add>', options=o) == Required(Argument('<add>'))
    assert parse_pattern('add', options=o) == Required(Command('add'))


def test_option_match():
    assert Option('-a').match([Option('-a', value=true)]) == \
            (true, [], [Option('-a', value=true)])
    assert Option('-a').match([Option('-x')]) == (false, [Option('-x')], [])
    assert Option('-a').match([Argument('N')]) == (false, [Argument('N')], [])
    assert Option('-a').match([Option('-x'), Option('-a'), Argument('N')]) == \
            (true, [Option('-x'), Argument('N')], [Option('-a')])
    assert Option('-a').match([Option('-a', value=true), Option('-a')]) == \
            (true, [Option('-a')], [Option('-a', value=true)])


def test_argument_match():
    assert Argument('N').match([Argument(null, 9)]) == \
            (true, [], [Argument('N', 9)])
    assert Argument('N').match([Option('-x')]) == (false, [Option('-x')], [])
    assert Argument('N').match([Option('-x'),
                                Option('-a'),
                                Argument(null, 5)]) == \
            (true, [Option('-x'), Option('-a')], [Argument('N', 5)])
    assert Argument('N').match([Argument(null, 9), Argument(null, 0)]) == \
            (true, [Argument(null, 0)], [Argument('N', 9)])


def test_command_match():
    assert Command('c').match([Argument(null, 'c')]) == \
            (true, [], [Command('c', true)])
    assert Command('c').match([Option('-x')]) == (false, [Option('-x')], [])
    assert Command('c').match([Option('-x'),
                               Option('-a'),
                               Argument(null, 'c')]) == \
            (true, [Option('-x'), Option('-a')], [Command('c', true)])
    assert Either(Command('add', false), Command('rm', false)).match(
            [Argument(null, 'rm')]) == (true, [], [Command('rm', true)])


def test_optional_match():
    assert Optional(Option('-a')).match([Option('-a')]) == \
            (true, [], [Option('-a')])
    assert Optional(Option('-a')).match([]) == (true, [], [])
    assert Optional(Option('-a')).match([Option('-x')]) == \
            (true, [Option('-x')], [])
    assert Optional(Option('-a'), Option('-b')).match([Option('-a')]) == \
            (true, [], [Option('-a')])
    assert Optional(Option('-a'), Option('-b')).match([Option('-b')]) == \
            (true, [], [Option('-b')])
    assert Optional(Option('-a'), Option('-b')).match([Option('-x')]) == \
            (true, [Option('-x')], [])
    assert Optional(Argument('N')).match([Argument(null, 9)]) == \
            (true, [], [Argument('N', 9)])
    assert Optional(Option('-a'), Option('-b')).match(
                [Option('-b'), Option('-x'), Option('-a')]) == \
            (true, [Option('-x')], [Option('-a'), Option('-b')])


def test_required_match():
    assert Required(Option('-a')).match([Option('-a')]) == \
            (true, [], [Option('-a')])
    assert Required(Option('-a')).match([]) == (false, [], [])
    assert Required(Option('-a')).match([Option('-x')]) == \
            (false, [Option('-x')], [])
    assert Required(Option('-a'), Option('-b')).match([Option('-a')]) == \
            (false, [Option('-a')], [])


def test_either_match():
    assert Either(Option('-a'), Option('-b')).match(
            [Option('-a')]) == (true, [], [Option('-a')])
    assert Either(Option('-a'), Option('-b')).match(
            [Option('-a'), Option('-b')]) == \
                    (true, [Option('-b')], [Option('-a')])
    assert Either(Option('-a'), Option('-b')).match(
            [Option('-x')]) == (false, [Option('-x')], [])
    assert Either(Option('-a'), Option('-b'), Option('-c')).match(
            [Option('-x'), Option('-b')]) == \
                    (true, [Option('-x')], [Option('-b')])
    assert Either(Argument('M'),
                  Required(Argument('N'), Argument('M'))).match(
                                   [Argument(null, 1), Argument(null, 2)]) == \
            (true, [], [Argument('N', 1), Argument('M', 2)])


def test_one_or_more_match():
    assert OneOrMore(Argument('N')).match([Argument(null, 9)]) == \
            (true, [], [Argument('N', 9)])
    assert OneOrMore(Argument('N')).match([]) == (false, [], [])
    assert OneOrMore(Argument('N')).match([Option('-x')]) == \
            (false, [Option('-x')], [])
    assert OneOrMore(Argument('N')).match(
            [Argument(null, 9), Argument(null, 8)]) == (
                    true, [], [Argument('N', 9), Argument('N', 8)])
    assert OneOrMore(Argument('N')).match(
            [Argument(null, 9), Option('-x'), Argument(null, 8)]) == (
                    true, [Option('-x')], [Argument('N', 9), Argument('N', 8)])
    assert OneOrMore(Option('-a')).match(
            [Option('-a'), Argument(null, 8), Option('-a')]) == \
                    (true, [Argument(null, 8)], [Option('-a'), Option('-a')])
    assert OneOrMore(Option('-a')).match([Argument(null, 8),
                                          Option('-x')]) == \
                    (false, [Argument(null, 8), Option('-x')], [])
    assert OneOrMore(Required(Option('-a'), Argument('N'))).match(
            [Option('-a'), Argument(null, 1), Option('-x'),
             Option('-a'), Argument(null, 2)]) == \
             (true, [Option('-x')],
              [Option('-a'), Argument('N', 1), Option('-a'), Argument('N', 2)])
    assert OneOrMore(Optional(Argument('N'))).match([Argument(null, 9)]) == \
                    (true, [], [Argument('N', 9)])


def test_list_argument_match():
    assert Required(Argument('N'), Argument('N')).fix().match(
            [Argument(null, '1'), Argument(null, '2')]) == \
                    (true, [], [Argument('N', ['1', '2'])])
    assert OneOrMore(Argument('N')).fix().match(
          [Argument(null, '1'), Argument(null, '2'), Argument(null, '3')]) == \
                    (true, [], [Argument('N', ['1', '2', '3'])])
    assert Required(Argument('N'), OneOrMore(Argument('N'))).fix().match(
          [Argument(null, '1'), Argument(null, '2'), Argument(null, '3')]) == \
                    (true, [], [Argument('N', ['1', '2', '3'])])
    assert Required(Argument('N'), Required(Argument('N'))).fix().match(
            [Argument(null, '1'), Argument(null, '2')]) == \
                    (true, [], [Argument('N', ['1', '2'])])


def test_basic_pattern_matching():
    # ( -a N [ -x Z ] )
    pattern = Required(Option('-a'), Argument('N'),
                       Optional(Option('-x'), Argument('Z')))
    # -a N
    assert pattern.match([Option('-a'), Argument(null, 9)]) == \
            (true, [], [Option('-a'), Argument('N', 9)])
    # -a -x N Z
    assert pattern.match([Option('-a'), Option('-x'),
                          Argument(null, 9), Argument(null, 5)]) == \
            (true, [], [Option('-a'), Argument('N', 9),
                        Option('-x'), Argument('Z', 5)])
    # -x N Z  # BZZ!
    assert pattern.match([Option('-x'),
                          Argument(null, 9),
                          Argument(null, 5)]) == \
            (false, [Option('-x'), Argument(null, 9), Argument(null, 5)], [])


def test_pattern_either():
    assert Option('-a').either == Either(Required(Option('-a')))
    assert Argument('A').either == Either(Required(Argument('A')))
    assert Required(Either(Option('-a'), Option('-b')),
                    Option('-c')).either == \
            Either(Required(Option('-a'), Option('-c')),
                   Required(Option('-b'), Option('-c')))
    assert Optional(Option('-a'),
                    Either(Option('-b'),
                    Option('-c'))).either == \
            Either(Required(Option('-b'), Option('-a')),
                   Required(Option('-c'), Option('-a')))
    assert Either(Option('-x'), Either(Option('-y'), Option('-z'))).either == \
            Either(Required(Option('-x')),
                   Required(Option('-y')),
                   Required(Option('-z')))
    assert OneOrMore(Argument('N'), Argument('M')).either == \
            Either(Required(Argument('N'), Argument('M'),
                            Argument('N'), Argument('M')))


def test_pattern_fix_repeating_arguments():
    assert Option('-a').fix_repeating_arguments() == Option('-a')
    assert Argument('N', null).fix_repeating_arguments() == Argument('N', null)
    assert Required(Argument('N'),
                    Argument('N')).fix_repeating_arguments() == \
            Required(Argument('N', []), Argument('N', []))
    assert Either(Argument('N'),
                        OneOrMore(Argument('N'))).fix() == \
            Either(Argument('N', []), OneOrMore(Argument('N', [])))


def test_set():
    assert Argument('N') == Argument('N')
    assert set([Argument('N'), Argument('N')]) == set([Argument('N')])


def test_pattern_fix_identities_1():
    pattern = Required(Argument('N'), Argument('N'))
    assert pattern.children[0] == pattern.children[1]
    assert pattern.children[0] is not pattern.children[1]
    pattern.fix_identities()
    assert pattern.children[0] is pattern.children[1]


def test_pattern_fix_identities_2():
    pattern = Required(Optional(Argument('X'), Argument('N')), Argument('N'))
    assert pattern.children[0].children[1] == pattern.children[1]
    assert pattern.children[0].children[1] is not pattern.children[1]
    pattern.fix_identities()
    assert pattern.children[0].children[1] is pattern.children[1]


def test_long_options_error_handling():
#    with raises(DocoptLanguageError):
#        docopt('Usage: prog --non-existent', '--non-existent')
#    with raises(DocoptLanguageError):
#        docopt('Usage: prog --non-existent')
    with raises(DocoptExit):
        docopt('Usage: prog', '--non-existent')
    with raises(DocoptExit):
        docopt('''Usage: prog [--version --verbose]\n\n
                  --version\n--verbose''', '--ver')
    with raises(DocoptLanguageError):
        docopt('Usage: prog --long\n\n--long ARG')
    with raises(DocoptExit):
        docopt('Usage: prog --long ARG\n\n--long ARG', '--long')
    with raises(DocoptLanguageError):
        docopt('Usage: prog --long=ARG\n\n--long')
    with raises(DocoptExit):
        docopt('Usage: prog --long\n\n--long', '--long=ARG')


def test_short_options_error_handling():
    with raises(DocoptLanguageError):
        docopt('Usage: prog -x\n\n-x  this\n-x  that')

#    with raises(DocoptLanguageError):
#        docopt('Usage: prog -x')
    with raises(DocoptExit):
        docopt('Usage: prog', '-x')

    with raises(DocoptLanguageError):
        docopt('Usage: prog -o\n\n-o ARG')
    with raises(DocoptExit):
        docopt('Usage: prog -o ARG\n\n-o ARG', '-o')


def test_matching_paren():
    with raises(DocoptLanguageError):
        docopt('Usage: prog [a [b]')
    with raises(DocoptLanguageError):
        docopt('Usage: prog [a [b] ] c )')


def test_allow_double_dash():
    assert docopt('usage: prog [-o] [--] <arg>\n\n-o',
                  '-- -o') == {'-o': false, '<arg>': '-o', '--': true}
    assert docopt('usage: prog [-o] [--] <arg>\n\n-o',
                  '-o 1') == {'-o': true, '<arg>': '1', '--': false}
    with raises(DocoptExit):
        docopt('usage: prog [-o] <arg>\n\n-o', '-- -o')  # '--' not allowed


def test_docopt():
    doc = '''Usage: prog [-v] A

    -v  Be verbose.'''
    assert docopt(doc, 'arg') == {'-v': false, 'A': 'arg'}
    assert docopt(doc, '-v arg') == {'-v': true, 'A': 'arg'}

    doc = """Usage: prog [-vqr] [FILE]
              prog INPUT OUTPUT
              prog --help

    Options:
      -v  print status messages
      -q  report only file names
      -r  show all occurrences of the same error
      --help

    """
    a = docopt(doc, '-v file.py')
    assert a == {'-v': true, '-q': false, '-r': false, '--help': false,
                 'FILE': 'file.py', 'INPUT': null, 'OUTPUT': null}

    a = docopt(doc, '-v')
    assert a == {'-v': true, '-q': false, '-r': false, '--help': false,
                 'FILE': null, 'INPUT': null, 'OUTPUT': null}

    with raises(DocoptExit):  # does not match
        docopt(doc, '-v input.py output.py')

    with raises(DocoptExit):
        docopt(doc, '--fake')

    with raises(SystemExit):
        docopt(doc, '--hel')

    #with raises(SystemExit):
    #    docopt(doc, 'help')  XXX Maybe help command?


def test_language_errors():
    with raises(DocoptLanguageError):
        docopt('no usage with colon here')
    with raises(DocoptLanguageError):
        docopt('usage: here \n\n and again usage: here')


def test_issue_40():
    with raises(SystemExit):  # i.e. shows help
        docopt('usage: prog --help-commands | --help', '--help')
    assert docopt('usage: prog --aabb | --aa', '--aa') == {'--aabb': false,
                                                           '--aa': true}


def test_issue34_unicode_strings():
    try:
        assert docopt(eval("u'usage: prog [-o <a>]'"), '') == \
                {'-o': false, '<a>': null}
    except SyntaxError:
        pass  # Python 3


def test_count_multiple_flags():
    assert docopt('usage: prog [-v]', '-v') == {'-v': true}
    assert docopt('usage: prog [-vv]', '') == {'-v': 0}
    assert docopt('usage: prog [-vv]', '-v') == {'-v': 1}
    assert docopt('usage: prog [-vv]', '-vv') == {'-v': 2}
    with raises(DocoptExit):
        docopt('usage: prog [-vv]', '-vvv')
    assert docopt('usage: prog [-v | -vv | -vvv]', '-vvv') == {'-v': 3}
    assert docopt('usage: prog -v...', '-vvvvvv') == {'-v': 6}
    assert docopt('usage: prog [--ver --ver]', '--ver --ver') == {'--ver': 2}


def test_count_multiple_commands():
    assert docopt('usage: prog [go]', 'go') == {'go': true}
    assert docopt('usage: prog [go go]', '') == {'go': 0}
    assert docopt('usage: prog [go go]', 'go') == {'go': 1}
    assert docopt('usage: prog [go go]', 'go go') == {'go': 2}
    with raises(DocoptExit):
        docopt('usage: prog [go go]', 'go go go')
    assert docopt('usage: prog go...', 'go go go go go') == {'go': 5}


def test_any_options_parameter():
    with raises(DocoptExit):
        docopt('usage: prog [options]', '-foo --bar --spam=eggs')
#    assert docopt('usage: prog [options]', '-foo --bar --spam=eggs',
#                  any_options=true) == {'-f': true, '-o': 2,
#                                         '--bar': true, '--spam': 'eggs'}
    with raises(DocoptExit):
        docopt('usage: prog [options]', '--foo --bar --bar')
#    assert docopt('usage: prog [options]', '--foo --bar --bar',
#                  any_options=true) == {'--foo': true, '--bar': 2}
    with raises(DocoptExit):
        docopt('usage: prog [options]', '--bar --bar --bar -ffff')
#    assert docopt('usage: prog [options]', '--bar --bar --bar -ffff',
#                  any_options=true) == {'--bar': 3, '-f': 4}
    with raises(DocoptExit):
        docopt('usage: prog [options]', '--long=arg --long=another')
#    assert docopt('usage: prog [options]', '--long=arg --long=another',
#                  any_options=true) == {'--long': ['arg', 'another']}


#def test_options_shortcut_multiple_commands():
#    # any_options is disabled
#    assert docopt('usage: prog c1 [options] prog c2 [options]',
#        'c2 -o', any_options=true) == {'-o': true, 'c1': false, 'c2': true}
#    assert docopt('usage: prog c1 [options] prog c2 [options]',
#        'c1 -o', any_options=true) == {'-o': true, 'c1': true, 'c2': false}


def test_options_shortcut_does_not_add_options_to_patter_second_time():
    assert docopt('usage: prog [options] [-a]\n\n-a -b', '-a') == \
            {'-a': true, '-b': false}
    with raises(DocoptExit):
        docopt('usage: prog [options] [-a]\n\n-a -b', '-aa')


def test_default_value_for_positional_arguments():
    # disabled right now
    assert docopt('usage: prog [<p>]\n\n<p>  [default: x]', '') == \
            {'<p>': null}
    #       {'<p>': 'x'}
    assert docopt('usage: prog [<p>]...\n\n<p>  [default: x y]', '') == \
            {'<p>': []}
    #       {'<p>': ['x', 'y']}
    assert docopt('usage: prog [<p>]...\n\n<p>  [default: x y]', 'this') == \
            {'<p>': ['this']}
    #       {'<p>': ['this']}


#def test_parse_defaults():
#    assert parse_defaults("""usage: prog
#
#                          -o, --option <o>
#                          --another <a>  description
#                                         [default: x]
#                          <a>
#                          <another>  description [default: y]""") == \
#           ([Option('-o', '--option', 1, null),
#             Option(null, '--another', 1, 'x')],
#            [Argument('<a>', null),
#             Argument('<another>', 'y')])
#
#    doc = '''
#    -h, --help  Print help message.
#    -o FILE     Output file.
#    --verbose   Verbose mode.'''
#    assert parse_defaults(doc)[0] == [Option('-h', '--help'),
#                                      Option('-o', null, 1),
#                                      Option(null, '--verbose')]


def test_issue_59():
    assert docopt('usage: prog --long=<a>', '--long=') == {'--long': ''}
    assert docopt('usage: prog -l <a>\n\n-l <a>', ['-l', '']) == {'-l': ''}


def test_options_first():
    assert docopt('usage: prog [--opt] [<args>...]',
                  '--opt this that') == {'--opt': true,
                                         '<args>': ['this', 'that']}
    assert docopt('usage: prog [--opt] [<args>...]',
                  'this that --opt') == {'--opt': true,
                                         '<args>': ['this', 'that']}
    assert docopt('usage: prog [--opt] [<args>...]',
                  'this that --opt',
                  options_first=true) == {'--opt': false,
                                          '<args>': ['this', 'that', '--opt']}


def test_issue_68_options_shortcut_does_not_include_options_in_usage_patter():
    args = docopt('usage: prog [-ab] [options]\n\n-x\n-y', '-ax')
    # Need to use `is` (not `==`) since we want to make sure
    # that they are not 1/0, but strictly true/false:
    assert args['-a'] is true
    assert args['-b'] is false
    assert args['-x'] is true
    assert args['-y'] is false


*/
