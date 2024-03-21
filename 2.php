<?php
declare(strict_types=1);
$filename = $_SERVER['argv'][1] ?? 'measurements-1000M.txt';
$fd = fopen($filename, "rb");
$result = [];
while ($line = fgets($fd)) {
    $line = explode(";", $line);
    $name = $line[0];
    $temp = (float)$line[1];
    if (isset($result[$name])) {
        [$min, $max, $sum, $count] = $result[$name];
        $result[$name] = [min($min, $temp), max($max, $temp), $sum + $temp, $count + 1];
    } else {
        $result[$name] = [$temp, $temp, $temp, 1];
    }
}
fclose($fd);
ksort($result);
foreach ($result as $name => $station) {
    [$min, $max, $sum, $count] = $station;
    $mean = $sum / $count;
    printf("%s=%01.2f/%01.2f/%01.2f\n", $name, $min, $mean, $max);
}