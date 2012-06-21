<?php

require(__DIR__.'/../src/docopt.php');

$in = '';

while (!feof(STDIN)) {
    $in .= fread(STDIN, 1024);
}

try {
    $result = Docopt\docopt($in);
    if (empty($result))
        echo '{}';
    else
        echo json_encode($result);
}
catch (Docopt\ExitException $ex) {
    print '"user-error"';
    if (getenv('DOCOPT_DEBUG')==1)
        echo $ex;
}
