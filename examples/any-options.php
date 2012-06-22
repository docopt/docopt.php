<?php

require(__DIR__.'/../src/docopt.php');

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

$arguments = Docopt\docopt($doc, array('version'=>'1.0.0rc2'));
if ($arguments === false)
    exit(1);
var_dump($arguments);
