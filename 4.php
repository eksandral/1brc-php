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
        $result[$name][0][] = $temp;
        $result[$name][1] += $temp;
        $result[$name][2] += 1;
    } else {
        $result[$name] = [[$temp], $temp, 1];
    }
}
fclose($fd);
ksort($result);
foreach ($result as $name => $station) {
    [$temps, $sum, $count] = $station;
    $min = min($temps);
    $max = max($temps);
    $mean = $sum / $count;
    printf("%s=%01.2f/%01.2f/%01.2f\n", $name, $min, $mean, $max);
}