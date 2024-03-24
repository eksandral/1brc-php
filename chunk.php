<?php

//$fd = fopen("php://stdin", "rb");
$fd = fopen("measurements-10.txt", "rb");
$result = [];
while($chunk = stream_get_contents($fd, 20)){
    if ($chunk[-1] !== "\n") {
        $chunk .= stream_get_line($fd, 100, "\n").PHP_EOL;
    }

    echo "============================\n";
    echo $chunk.PHP_EOL;
    echo "============================\n";
}
