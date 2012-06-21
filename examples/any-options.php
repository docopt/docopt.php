<?php

require(__DIR__.'/../docopt.php');

$doc = "
Example of program which uses [options] shortcut in pattern.

Usage:
  any_options_example.py [options] <port>

Options:
  -h --help                show this help message and exit
  --version                show version and exit
  -n, --number N           use N as a number
  -t, --timeout TIMEOUT    set timeout TIMEOUT seconds
  --apply                  apply changes to database
  -q                       operate in quiet mode

";

try {
    $arguments = Docopt\docopt($doc, array('version'=>'1.0.0rc2'));
    var_dump($arguments);
}
catch (Docopt\ExitException $ex) {
    echo $ex->getMessage();
}
