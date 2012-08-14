<?php

namespace Docopt;

/**
 * Return true if all cased characters in the string are uppercase and there is 
 * at least one cased character, false otherwise.
 * Python method with no known equivalent in PHP.
 */
function is_upper($string)
{
    return preg_match('/[A-Z]/', $string) && !preg_match('/[a-z]/', $string);
}

/**
 * Return True if any element of the iterable is true. If the iterable is empty, return False.
 * Python method with no known equivalent in PHP.
 */
function any($iterable)
{
    foreach ($iterable as $element) {
        if ($element)
            return true;
    }
    return false;
}

/**
 * The PHP version of this function doesn't work properly if the values aren't scalar.
 */
function array_count_values($array)
{
    $counts = array();
    foreach ($array as $v) {
        if ($v && is_scalar($v))
            $key = $v;
        elseif (is_object($v))
            $key = spl_object_hash($v);
        else
            $key = serialize($v);
        
        if (!isset($counts[$key]))
            $counts[$key] = array($v, 1);
        else
            $counts[$key][1]++;
    }
    return $counts;
}

/**
 * The PHP version of this doesn't support array iterators
 */
function array_filter($input, $callback, $reKey=false)
{
    if ($input instanceof \ArrayIterator)
        $input = $input->getArrayCopy();
    
    $filtered = \array_filter($input, $callback);
    if ($reKey) $filtered = array_values($filtered);
    return $filtered;
}

/**
 * The PHP version of this doesn't support array iterators
 */
function array_merge()
{
    $values = func_get_args();
    $resolved = array();
    foreach ($values as $v) {
        if ($v instanceof \ArrayIterator)
            $resolved[] = $v->getArrayCopy();
        else
            $resolved[] = $v;
    }
    return call_user_func_array('array_merge', $resolved);
}

function ends_with($str, $test)
{
    $len = strlen($test);
    return substr_compare($str, $test, -$len, $len) === 0;
}

/**
 * Error in construction of usage-message by developer
 */
class LanguageError extends \Exception
{
}

/**
 * Exit in case user invoked program with incorrect arguments.
 * DocoptExit equivalent.
 */
class ExitException extends \RuntimeException
{
    public static $usage;
    
    public $status;
    
    public function __construct($message=null, $status=1)
    {
        parent::__construct(trim($message.PHP_EOL.static::$usage));
        $this->status = $status;
    }
}

class Pattern
{
    public function equals($other)
    {
        return $this->repr() == $other->repr();
    }
    
    public function hash()
    {
        return crc32($this->repr());
    }
    
    public function fix()
    {
        $this->fixIdentities();
        $this->fixListArguments();
        return $this;
    }
    
    /**
     * Make pattern-tree tips point to same object if they are equal.
     */
    public function fixIdentities($uniq=null)
    {
        if (!isset($this->children) || !$this->children)
            return $this;
        
        if (!$uniq) {
            $uniq = array_unique($this->flat());
        }
        
        foreach ($this->children as $i=>$c) {
            if (!isset($c->children) || !$c->children) {
                if (!in_array($c, $uniq)) {
                    // Not sure if this is a true substitute for 'assert c in uniq'
                    throw new \UnexpectedValueException();
                }
                $this->children[$i] = $uniq[array_search($c, $uniq)];
            }
            else {
                $c->fixIdentities($uniq);   
            }     
        }
    }
    
    /**
     * Find arguments that should accumulate values and fix them.
     */
    public function fixListArguments()
    {
        $either = array();
        foreach ($this->either()->children as $c) {
            $either[] = $c->children;
        }
        
        foreach ($either as $case) {
            $case = array_map(
                function($value) { return $value[0]; },
                array_filter(array_count_values($case), function($value) { return $value[1] > 1; })
            );
            
            foreach ($case as $e) {
                if ($e instanceof Argument || ($e instanceof Option && $e->argcount))
                    $e->value = array();
                if ($e instanceof Command || ($e instanceof Option && $e->argcount == 0))
                    $e->value = 0;
            }
        }
        
        return $this;
    }
    
    /**
     * Transform pattern into an equivalent, with only top-level Either.
     */
    public function either()
    {
        // Currently the pattern will not be equivalent, but more "narrow",
        // although good enough to reason about list arguments.
        $ret = array();
        $groups = array(array($this));
        while ($groups) {
            $children = array_pop($groups);
            $types = array();
            foreach ($children as $c) {
                if (is_object($c)) {
                    $cls = get_class($c);
                    $types[] = substr($cls, strrpos($cls, '\\')+1);
                }
            }
            
            if (in_array('Either', $types)) {
                $either = null;
                foreach ($children as $c) {
                    if ($c instanceof Either) {
                        $either = $c;
                        break;
                    }
                }
                
                unset($children[array_search($either, $children)]);
                foreach ($either->children as $c) {
                    $groups[] = array_merge(array($c), $children);
                }
            }
            elseif (in_array('Required', $types)) {
                $required = null;
                foreach ($children as $c) {
                    if ($c instanceof Required) {
                        $required = $c;
                        break;
                    }
                }
                unset($children[array_search($required, $children)]);
                $groups[] = array_merge($required->children, $children);
            }
            elseif (in_array('Optional', $types)) {
                $optional = null;
                foreach ($children as $c) {
                    if ($c instanceof Optional) {
                        $optional = $c;
                        break;
                    }
                }
                unset($children[array_search($optional, $children)]);
                $groups[] = array_merge($optional->children, $children);
            }
            elseif (in_array('OneOrMore', $types)) {
                $oneormore = null;
                foreach ($children as $c) {
                    if ($c instanceof OneOrMore) {
                        $oneormore = $c;
                        break;
                    }
                }
                unset($children[array_search($oneormore, $children)]);
                $groups[] = array_merge($oneormore->children, $oneormore->children, $children);
            }
            else {
                $ret[] = $children;
            }
        }
        
        $rs = array();
        foreach ($ret as $e) {
            $rs[] = new Required($e);
        }
        return new Either($rs);
    }
    
    public function name()
    {}

    public function __get($name)
    {
        if ($name == 'name')
            return $this->name();
        else
            throw new \BadMethodCallException("Unknown property $name");
    }
}

class ChildPattern extends Pattern
{
    public function flat()
    {
        return array($this);
    }
    
    public function __toString()
    {
        return serialize($this);
    }
    
    public function match($left, $collected=null)
    {
        if (!$collected) $collected = array();
        
        list ($pos, $match) = $this->singleMatch($left);
        if (!$match)
            return array(false, $left, $collected);
        
        $left_ = $left;
        unset($left_[$pos]);
        $left_ = array_values($left_);
        
        $name = $this->name;
        $sameName = array_filter($collected, function ($a) use ($name) { return $name == $a->name; }, true);
        
        if (is_int($this->value) || is_array($this->value) || $this->value instanceof \Traversable) {
            $increment = is_int($this->value) ? 1 : array($match->value);
            if (!$sameName) {
                $match->value = $increment;
                return array(true, $left_, array_merge($collected, array($match)));
            }
            
            if (is_array($increment) || $increment instanceof \Traversable)
                $sameName[0]->value = array_merge($sameName[0]->value, $increment);
            else
                $sameName[0]->value += $increment;
            
            return array(true, $left_, $collected);
        }
        
        return array(true, $left_, array_merge($collected, array($match)));
    }
}

class ParentPattern extends Pattern
{
    public $children = array();
    
    public function __construct($children=null)
    {
        if (!$children)
            $children = array();
        elseif ($children instanceof Pattern)
            $children = array($children);
        
        foreach ($children as $c) {
            $this->children[] = $c;
        }
    }
    
    public function flat()
    {
        if (!$this->children) {
            return array($this);
        }
        else {
            $flat = array();
            foreach ($this->children as $c) {
                $flat = array_merge($flat, $c->flat());
            }
            return $flat;
        }
    }
}

class Argument extends ChildPattern
{
    public $name;
    public $value;
    
    public function __construct($name, $value=null)
    {
        $this->name = $name;
        $this->value = $value;
    }
    
    public function singleMatch($left)
    {
        foreach ($left as $n=>$p) {
            if ($p instanceof Argument) {
                return array($n, new Argument($this->name, $p->value));
            }
        }
        
        return array(null, null);
    }
}

class Command extends Argument
{
    public $name;
    public $value;
    
    public function __construct($name, $value=false)
    {
        $this->name = $name;
        $this->value = $value;
    }
    
    function singleMatch($left)
    {
        foreach ($left as $n=>$p) {
            if ($p instanceof Argument) {
                if ($p->value == $this->name)
                    return array($n, new Command($this->name, true));
                else
                    break;
            }
        }
        return array(null, null);
    }
}

class Option extends ChildPattern
{
    public $short;
    public $long;
    
    public function __construct($short=null, $long=null, $argcount=0, $value=false)
    {
        if ($argcount != 0 && $argcount != 1)
            throw new \InvalidArgumentException();
        
        $this->short = $short;
        $this->long = $long;
        $this->argcount = $argcount;
        $this->value = $value;
        
        if (!$value && $argcount)
            $this->value = null; // apparently a hack
    }
    
    public static function parse($optionDescription)
    {
        $short = null;
        $long = null;
        $argcount = 0;
        $value = false;
        
        $exp = explode('  ', trim($optionDescription), 2);
        $options = $exp[0];
        $description = isset($exp[1]) ? $exp[1] : '';
        
        $options = str_replace(',', ' ', str_replace('=', ' ', $options));
        foreach (preg_split('/\s+/', $options) as $s) {
            if (strpos($s, '--')===0)
                $long = $s;
            elseif ($s && $s[0] == '-')
                $short = $s;
            else
                $argcount = 1;
        }
        
        if ($argcount) {
            $value = null;
            if (preg_match('@\[default: (.*)\]@i', $description, $match)) {
                $value = $match[1];
            }
        }
        
        return new static($short, $long, $argcount, $value);
    }
    
    public function singleMatch($left)
    {
        foreach ($left as $n=>$p) {
            if ($this->name == $p->name) {
                return array($n, $p);
            }
        }
        return array(null, null);
    }
    
    public function name()
    {
        return $this->long ?: $this->short;
    }
}

class Required extends ParentPattern
{
    public function match($left, $collected=null)
    {
        if (!$collected)
            $collected = array();
        
        $l = $left;
        $c = $collected;

        foreach ($this->children as $p) {
            list ($matched, $l, $c) = $p->match($l, $c);
            if (!$matched)
                return array(false, $left, $collected);
        }
        
        return array(true, $l, $c);
    }
}

class Optional extends ParentPattern
{
    public function match($left, $collected=null)
    {
        if (!$collected)
            $collected = array();
        
        foreach ($this->children as $p) {
            list($m, $left, $collected) = $p->match($left, $collected);
        }
        
        return array(true, $left, $collected);
    }
}

class OneOrMore extends ParentPattern
{
    public function match($left, $collected=null)
    {
        if (count($this->children) != 1)
            throw new \UnexpectedValueException();
        
        if (!$collected)
            $collected = array();
        
        $l = $left;
        $c = $collected;
        
        $lnew = array();
        $matched = true;
        $times = 0;
        
        while ($matched) {
            # could it be that something didn't match but changed l or c?
            list ($matched, $l, $c) = $this->children[0]->match($l, $c);
            if ($matched) $times += 1;
            if ($lnew == $l)
                break;
            $lnew = $l;
        }
        
        if ($times >= 1)
            return array(true, $l, $c);
        else
            return array(false, $left, $collected);
    }
}

class Either extends ParentPattern
{
    public function match($left, $collected=null)
    {
        if (!$collected)
            $collected = array();
        
        $outcomes = array();
        foreach ($this->children as $p) {
            list ($matched, $dump1, $dump2) = $outcome = $p->match($left, $collected);
            if ($matched)
                $outcomes[] = $outcome;
        }
        if ($outcomes) {
            // return min(outcomes, key=lambda outcome: len(outcome[1]))
            $min = null;
            $ret = null;
            foreach ($outcomes as $o) {
                $cnt = count($o[1]);
                if ($min === null || $cnt < $min) {
                   $min = $cnt;
                   $ret = $o;
                }
            }
            return $ret;
        }
        else
            return array(false, $left, $collected);
    }
}

class TokenStream extends \ArrayIterator
{
    public $error;
    
    public function __construct($source, $error)
    {
        if (!is_array($source))
            $source = preg_split('/\s+/', trim($source));
        
        parent::__construct($source);
                
        $this->error = $error; 
    }
    
    function move()
    {
        $item = $this->current();
        $this->next();
        return $item;
    }
    
    function raiseException($message)
    {
        $class = __NAMESPACE__.'\\'.$this->error;
        throw new $class($message);
    }
}

function parse_long($tokens, \ArrayIterator $options)
{
    $token = $tokens->move();
    $exploded = explode('=', $token, 2);
    if (count($exploded) == 2) {
        $raw = $exploded[0];
        $eq = '=';
        $value = $exploded[1];
    }
    else {
        $raw = $token;
        $eq = null;
        $value = null;
    }

    if (!$value) $value = null;
    
    $opt = array_filter($options, function($o) use ($raw) { return $o->long && $o->long == $raw; }, true);
    if ('ExitException' == $tokens->error && !$opt)
        $opt = array_filter($options, function($o) use ($raw) { return $o->long && strpos($o->long, $raw)===0; }, true);
    
    if (!$opt) {
        if ($tokens->error == 'ExitException') {
            $tokens->raiseException("$raw is not recognised");
        }
        else {
            $o = new Option(null, $raw, $eq == '=' ? 1 : 0);
            $options[] = $o;
            return array($o);
        }
    }
    
    if (count($opt) > 1) {
        $oLongs = array();
        foreach ($opt as $o) {
            $oLongs[] = $o->long;
        }
        $tokens->raiseException(sprintf("%s is not a unique prefix: %s?", $raw, implode(", ", $oLongs)));
    }
    
    $o = $opt[0];
    $opt = new Option($o->short, $o->long, $o->argcount, $o->value);
    if ($opt->argcount == 1) {
        if ($value === null) {
            if ($tokens->current() == null) {
                $tokens->raiseException("{$opt->name} requires argument");
            }
            $value = $tokens->move();
        }
    }
    elseif ($value !== null) {
        $tokens->raiseException("{$opt->name} must not have an argument");
    }
    
    if ($tokens->error == 'ExitException')
        $opt->value = $value ?: true;
    else
        $opt->value = $value ? null : false;
    
    return array($opt);
}

function parse_shorts($tokens, \ArrayIterator $options)
{
    $raw = substr($tokens->move(), 1);
    $parsed = array();
    while ($raw != '') {
        $opt = array();
        foreach ($options as $o) {
            if ($o->short && strpos(ltrim($o->short, '-'), $raw[0])===0)
                $opt[] = $o;
        }
        $optc = count($opt);
        if ($optc > 1) {
            $tokens->raiseException(sprintf('-%s is specified ambiguously %d times', $raw[0], $optc));
        }
        elseif ($optc < 1) {
            if ($tokens->error == 'ExitException') {
                $tokens->raiseException("-{$raw[0]} is not recognised");
            }
            else {
                $o = new Option('-'.$raw[0], null);
                $options[] = $o;
                $parsed[] = $o;
                $raw = substr($raw, 1);
                continue;
            }
        }
        
        $o = $opt[0];
        $opt = new Option($o->short, $o->long, $o->argcount, $o->value);
        $raw = substr($raw, 1);
        
        if ($opt->argcount == 0) {
            $value = 'ExitException' == $tokens->error ? true : false;
        }
        else {
            if ($raw == '') {
                if ($tokens->current() == null) {
                    $tokens->raiseException("-{$opt->short[0]} requires argument");
                }
                $raw = $tokens->move();
            }
            $value = $raw;
            $raw = '';
        }

        if ('ExitException' == $tokens->error)
            $opt->value = $value;
        else
            $opt->value = $value ? null : false;
        
        $parsed[] = $opt;
    }
    
    return $parsed;
}

function parse_pattern($source, \ArrayIterator $options)
{
    $tokens = new TokenStream(preg_replace('@([\[\]\(\)\|]|\.\.\.)@', ' $1 ', $source), 'LanguageError');
    
    $result = parse_expr($tokens, $options);
    if ($tokens->current() != null) {
        $tokens->raiseException('unexpected ending: '.implode(' ', $tokens));
    }
    return new Required($result);
}

/**
 * expr ::= seq ( '|' seq )* ;
 */
function parse_expr($tokens, \ArrayIterator $options)
{
    $seq = parse_seq($tokens, $options);
    if ($tokens->current() != '|')
        return $seq;
    
    $result = null;
    if (count($seq) > 1)
        $result = array(new Required($seq));
    else
        $result = $seq;
    
    while ($tokens->current() == '|') {
        $tokens->move();
        $seq = parse_seq($tokens, $options);
        if (count($seq) > 1)
            $result[] = new Required($seq);
        else
            $result = array_merge($result, $seq);
    }

    if (count($result) > 1)
        return new Either($result);
    else
        return $result;
}

/**
 * seq ::= ( atom [ '...' ] )* ;
 */
function parse_seq($tokens, \ArrayIterator $options)
{
    $result = array();
    $not = array(null, '', ']', ')', '|');
    while (!in_array($tokens->current(), $not, true)) {
        $atom = parse_atom($tokens, $options);
        if ($tokens->current() == '...') {
            $atom = array(new OneOrMore($atom));
            $tokens->move();
        }
        if ($atom instanceof \ArrayIterator)
            $atom = $atom->getArrayCopy();
        if ($atom) {
            $result = array_merge($result, $atom);
        }
    }
    return $result;
}

/**
 * atom ::= '(' expr ')' | '[' expr ']' | 'options'
 *       | long | shorts | argument | command ;
 */
function parse_atom($tokens, \ArrayIterator $options)
{
    $token = $tokens->current();
    $result = array();
    if ($token == '(' || $token == '[') {
        $tokens->move();
        
        static $index;
        if (!$index) $index = array('('=>array(')', __NAMESPACE__.'\Required'), '['=>array(']', __NAMESPACE__.'\Optional'));
        list ($matching, $pattern) = $index[$token];
        
        $result = new $pattern(parse_expr($tokens, $options));
        if ($tokens->move() != $matching)
            $tokens->raiseException("Unmatched '$token'");
        
        return array($result);
    }
    elseif ($token == 'options') {
        $tokens->move();
        return $options;
    }
    elseif (strpos($token, '--') === 0 && $token != '--') {
        return parse_long($tokens, $options);
    }
    elseif (strpos($token, '-') === 0 && $token != '-' && $token != '--') {
        return parse_shorts($tokens, $options);
    }
    elseif (strpos($token, '<') === 0 && ends_with($token, '>') || is_upper($token)) {
        return array(new Argument($tokens->move()));
    }
    else {
        return array(new Command($tokens->move()));
    }
}

function parse_argv($source, \ArrayIterator $options)
{
    $tokens = new TokenStream($source, 'ExitException');
    $parsed = array();
    
    while ($tokens->current() !== null) {
        if ($tokens->current() == '--') {
            foreach ($tokens as $v) {
                $parsed[] = new Argument(null, $v);
            }
            return $parsed;
        }
        elseif (strpos($tokens->current(), '--')===0) {
            $parsed = array_merge($parsed, parse_long($tokens, $options));
        }
        elseif (strpos($tokens->current(), '-')===0 && $tokens->current() != '-') {
            $parsed = array_merge($parsed, parse_shorts($tokens, $options));
        }
        else {
            $parsed[] = new Argument(null, $tokens->move());
        }
    }
    return $parsed;
}

function parse_doc_options($doc)
{
    $items = new \ArrayIterator();
    foreach (array_slice(preg_split('@^ *-|\n *-@', $doc), 1) as $s) {
        $items[] = Option::parse('-'.$s);
    }
    return $items; 
}

function printable_usage($doc)
{
    $usageSplit = preg_split("@([Uu][Ss][Aa][Gg][Ee]:)@", $doc, null, PREG_SPLIT_DELIM_CAPTURE);
    
    if (count($usageSplit) < 3)
        throw new LanguageError('"usage:" (case-insensitive) not found.');
    elseif (count($usageSplit) > 3)
        throw new LanguageError('More than one "usage:" (case-insensitive).');
    
    $split = preg_split("@\n\s*\n@", implode('', array_slice($usageSplit, 1)));
    
    return trim($split[0]);
}

function formal_usage($printableUsage)
{
    $pu = array_slice(preg_split('/\s+/', $printableUsage), 1);
    
    $ret = array();
    foreach (array_slice($pu, 1) as $s) {
        if ($s == $pu[0])
            $ret[] = ') | (';
        else
            $ret[] = $s; 
    }
    
    return '( '.implode(' ', $ret).' )';
}

function extras($help, $version, $options, $doc)
{
    $ofound = false;
    $vfound = false;
    foreach ($options as $o) {
        if ($o->value && ($o->name == '-h' || $o->name == '--help'))
            $ofound = true; 
        if ($o->value && $o->name == '--version')
            $vfound = true;
    }
    if ($help && $ofound) {
        ExitException::$usage = null;
        throw new ExitException($doc, 0);
    }
    if ($version && $vfound) {
        ExitException::$usage = null;
        throw new ExitException($version, 0);
    }
}

/**
 * API compatibility with python docopt
 */
function docopt($doc, $params=array())
{
    $argv = array();
    if (isset($params['argv'])) {
        $argv = $params['argv'];
        unset($params['argv']);
    }
    $h = new Handler($params);
    return $h->handle($doc, $argv);
}

/**
 * Use a class in PHP because we can't autoload functions yet.
 */
class Handler
{
    public $exit = true;
    public $help = true;
    public $version;
    
    public function __construct($options=array())
    {
        foreach ($options as $k=>$v)
            $this->$k = $v;
    }
    
    function handle($doc, $argv=null)
    {
        try {
            if (!$argv && isset($_SERVER['argv']))
                $argv = array_slice($_SERVER['argv'], 1);
            
            ExitException::$usage = printable_usage($doc);
            $options = parse_doc_options($doc);
            $formalUse = formal_usage(ExitException::$usage);
            $pattern = parse_pattern($formalUse, $options);
            
            $argv = parse_argv($argv, $options);
            extras($this->help, $this->version, $argv, $doc);
            
            list($matched, $left, $collected) = $pattern->fix()->match($argv);
            if ($matched && !$left) {
                $return = array();
                foreach (array_merge($pattern->flat(), $options, $collected) as $a) {
                    $name = $a->name;
                    if ($name)
                        $return[$name] = $a->value;
                }
                return new Response($return);
            }
            throw new ExitException();
        }
        catch (ExitException $ex) {
            $this->handleExit($ex);
            return new Response(null, $ex->status, $ex->getMessage());
        }
    }
    
    function handleExit(ExitException $ex)
    {
        if ($this->exit) {
            echo $ex->getMessage().PHP_EOL;
            exit($ex->status);
        }
    }
}

class Response implements \ArrayAccess, \IteratorAggregate
{
    public $status;
    public $output;
    public $args;
    
    public function __construct($args, $status=0, $output='')
    {
        $this->args = $args ?: array();
        $this->status = $status;
        $this->output = $output;
    }
    
    public function __get($name)
    {
        if ($name == 'success')
            return $this->status === 0;
        else
            throw new \BadMethodCallException("Unknown property $name");
    }
    
    public function offsetExists ($offset)
    {
        return isset($this->args[$offset]);
    }

    public function offsetGet ($offset)
    {
        return $this->args[$offset];
    }

    public function offsetSet ($offset, $value)
    {
        $this->args[$offset] = $value;
    }

    public function offsetUnset ($offset)
    {
        unset($this->args[$offset]);
    }
    
    public function getIterator ()
    {
        return new \ArrayIterator($this->args);
    }
}
