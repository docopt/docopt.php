<?php

require(__DIR__.'/../src/docopt.php');

$in = '';

while (!feof(STDIN)) {
    $in .= fread(STDIN, 1024);
}

ob_start();
$result = Docopt\docopt($in);
$out = ob_end_clean();

if ($result === false)
    print '"user-error"';
elseif (empty($result))
    echo '{}';
else
    echo json_encode($result);
