<?php
$fd = fopen("php://stdin", "rb");
$result = [];
while($line = stream_get_line($fd, 128, "\n")){
if ($line === "") {break;}
    $line = explode(";", $line);
    $name = $line[0];
    if($name === ""){
        continue;
    }
    $temp = (float)$line[1];
    $station = &$result[$name];
    if ($station !== null) {
        $station[0][] = $temp;
        $station[1] += $temp;
        $station[2] += 1;
    } else {
        $station = [[$temp], $temp, 1];
    }
}
foreach ($result as $name => $values) {
    [$temp, $sum, $count] = $values;
    $min = min($temp);
    $max = max($temp);
    echo "$name;$min;$max;$sum;$count\n";
}
fclose($fd);
exit(0);
