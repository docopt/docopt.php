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
    public $children = array();
    
    public function __construct($children=null)
    {
        if (!$children)
            $children = array();
        elseif ($children instanceof Pattern)
            $children = array($children);
        
        if (!is_array($children) && !$children instanceof \Traversable)
            throw new \InvalidArgumentException("Type was not a traversable: found ".gettype($children).':'.get_class($children));
        
        foreach ($children as $c) {
            if (!$c instanceof Pattern)
                throw new \InvalidArgumentException("Type was not a pattern: found ".gettype($c).':'.get_class($c));
            $this->children[] = $c;
        }
    }

    public function equals($other)
    {
        return $this->repr() == $other->repr();
    }
    
    public function hash()
    {
        return crc32($this->repr());
    }
    
    public function __toString()
    {
        return serialize($this);
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
        if (!$this->children)
            return $this;
        
        if (!$uniq) {
            $uniq = array_unique($this->flat());
        }
        
        foreach ($this->children as $i=>$c) {
            if (!$c->children) {
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
        
        foreach ($either as $i) {
            $case = array();
            foreach ($i as $c) {
                $num = 0;
                foreach ($i as $ch) {
                    if ($ch == $c)
                        ++$num;
                    if ($num > 1) {
                        $case[] = $c;
                        break;
                    }
                }
            }
            foreach ($case as $e) {
                if ($e instanceof Argument) {
                    $e->value = array();
                }
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
        if (!$this->children) {
            return new Either(new Required($this));
        }
        
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
}

class Argument extends Pattern
{
    public $name;
    public $value;
    
    public function __construct($name, $value=null)
    {
        $this->name = $name;
        $this->value = $value;
    }
    
    public function match($left, $collected=null)
    {
        if (!$collected) $collected = array();
        
        $args = array();
        foreach ($left as $l) {
            if ($l instanceof Argument)
                $args[] = $l;
        }
        
        if (!$args)
            return array(false, $left, $collected);
        
        $argIdx = array_search($args[0], $left);
        if ($argIdx !== false) unset($left[$argIdx]);
        
        if (!is_array($this->value) && !$this->value instanceof Traversable) {
            $collected[] = new Argument($this->name, $args[0]->value);
            return array(true, $left, $collected);
        }
        
        $sameName = array();
        foreach ($collected as $a) {
            if ($a instanceof Argument && $a->name == $this->name)
                $sameName[] = $a;
        }
        
        if ($sameName) {
            $sameName[0]->value[] = $args[0]->value;
            return array(true, $left, $collected);
        }
        else {
            $collected[] = new Argument($this->name, array($args[0]->value));
            return array(true, $left, $collected);
        }
    }
}

class Command extends Pattern
{
    public $name;
    public $value;
    
    public function __construct($name, $value=false)
    {
        $this->name = $name;
        $this->value = $value;
    }
    
    public function match($left, $collected=null)
    {
        if (!$collected) $collected = array();
        $args = array();
        foreach ($left as $l) {
            if ($l instanceof Argument)
                $args[] = $l;
        }
        if (!$args || $args[0]->value != $this->name)
            return array(false, $left, $collected);
        
        unset($left[array_search($args[0], $left)]);
        
        $collected[] = new Command($this->name, true);
        return array(true, $left, $collected);
    }
}

class Option extends Pattern
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
            elseif ($s[0] == '-')
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
    
    public function match($left, $collected=null)
    {
        if (!$collected)
            $collected = array();
        
        $left2 = array();
        foreach ($left as $l) {
             // if this is so greedy, how to handle OneOrMore then?
             if (!($l instanceof Option && $this->short == $l->short && $this->long == $l->long))
                 $left2[] = $l;
        }
        return array($left != $left2, $left2, $collected); 
    }
    
    public function name()
    {
        return $this->long ?: $this->short;
    }
    
    public function __get($name)
    {
        if ($name == 'name')
            return $this->name();
        else
            throw new \BadMethodCallException("Unknown property $name");
    }
}

class AnyOptions extends Pattern
{
    public function match($left, $collected=null)
    {
        if (!$collected)
            $collected = array();
        
        $left2 = array();
        foreach ($left as $l) {
            if (!$l instanceof Option)
                $left2[] = $l;
        }
        return array($left != $left2, $left2, $collected);
    }
}

class Required extends Pattern
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

class Optional extends Pattern
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

class OneOrMore extends Pattern
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

class Either extends Pattern
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
    
    $opt = array();
    foreach ($options as $o) {
        if ($raw && $o->long && strpos($o->long, $raw)===0)
            $opt[] = $o;
    }
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
    
    $opt->value = $value ?: true;
    
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
            $value = true;
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
        $opt->value = $value;
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
        $result = array_merge($result, $atom);
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
    if ($token == '(') {
        $tokens->move();
        $result = array(new Required(parse_expr($tokens, $options)));
        if ($tokens->move() != ')')
            $tokens->raiseException("Unmatched '('");
        
        return $result;
    }
    elseif ($token == '[') {
        $tokens->move();
        $result = array(new Optional(parse_expr($tokens, $options)));
        if ($tokens->move() != ']')
            $tokens->raiseException("Unmatched '['");
        return $result;
    }
    elseif ($token == 'options') {
        $tokens->move();
        return array(new AnyOptions());
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

function parse_args($source, \ArrayIterator $options)
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
            $ret[] = '|';
        else
            $ret[] = $s; 
    }
    return implode(' ', $ret);
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
            
            ExitException::$usage = $usage = printable_usage($doc);
            $potOptions = parse_doc_options($doc);
            $formalUse = formal_usage($usage);
            $formalPattern = parse_pattern($formalUse, $potOptions);
            
            $argv = parse_args($argv, $potOptions);
            extras($this->help, $this->version, $argv, $doc);
            
            list($matched, $left, $arguments) = $formalPattern->fix()->match($argv);
            if ($matched && !$left) {
                $options = array();
                foreach ($argv as $o) {
                    if ($o instanceof Option) $options[] = $o;
                }
                $potArguments = array();
                foreach ($formalPattern->flat() as $a) {
                    if ($a instanceof Argument || $a instanceof Command)
                        $potArguments[] = $a;
                }
                $return = array();
                foreach (array_merge($potOptions->getArrayCopy(), $options, $potArguments, $arguments) as $a) {
                    $return[$a->name] = $a->value;
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
