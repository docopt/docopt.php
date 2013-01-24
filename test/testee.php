<?php

require(__DIR__.'/../src/docopt.php');

$in = '';

while (!feof(STDIN)) {
    $in .= fread(STDIN, 1024);
}

$ex = null;
$result = null;
ob_start();
try {
    $result = Docopt\docopt($in, array('exit'=>false));
}
catch (Exception $ex) {
}
$out = ob_get_clean();

if (getenv('DOCOPT_DEBUG')) {
    if ($out)
        echo "Output:\n".rtrim($out)."\n\n";
    if ($ex)
        echo "Exception:\n".$ex;
}

if ($result) {
    if (!$result->success)
        echo '"user-error"';
    elseif (empty($result->args))
        echo '{}';
    else
        echo json_encode($result->args);
}
