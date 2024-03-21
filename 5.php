<?php
declare(strict_types=1);
$filename = $_SERVER['argv'][1] ?? 'measurements-100M.txt';

const PHP_APP = <<<'PHP'
$procName = $_SERVER["argv"][1] ?? "proc_name_undefined";
$fd = fopen("php://stdin", "rb");
$result = [];
$chunk = stream_get_contents($fd);
$lines = explode("\n", $chunk);
foreach ($lines as $line) {
    $line = explode(";", $line);
    $name = $line[0];
    if($name === ""){
        continue;
    }
    $temp = (float)$line[1];
    if($name === ""){
        continue;
    }
    if (isset($result[$name])) {
        $station = &$result[$name];
        $station[0][] = $temp;
        $station[1][] = $temp;
        $station[2] += $temp;
        $station[3] += 1;
    } else {
        $result[$name] = [[$temp], [$temp], $temp, 1];
    }
}
$stdout = fopen("php://stdout", "wb");
foreach ($result as $name => $values) {
    [$min, $max, $sum, $count] = $values;
    $min = min($min);
    $max = max($max);
    fwrite($stdout, "$name,$min,$max,$sum,$count\n");
}
fclose($stdout);
fclose($fd);
exit(0);
PHP;
const CHUNK_SIZE = 32 * 1024 * 1024;
$fd = fopen($filename, "rb");
$result = [];
$start = microtime(true);

$descriptors = [0 => ['pipe', 'r'], // stdin
    1 => ['pipe', 'w'], // stdout
    2 => ["file", "/tmp/multithread1.txt", "a"] // stderr

];
$pipes = [];
$processes = [];
$i = 0;
while (!feof($fd)) {
    $chunk = fread($fd, CHUNK_SIZE);
    if ($chunk === false) {
        break;
    }
    $len = CHUNK_SIZE;
    if ($chunk[-1] !== "\n") {
        $subchunk = fgets($fd);
        $len += strlen($subchunk);
        $chunk .= $subchunk;
    }
    $proc = proc_open('php -dmemory_limit=-1 -r \'' . PHP_APP . '\'', $descriptors, $procPipes);
    fwrite($procPipes[0], $chunk, $len);
    fclose($procPipes[0]);
    $processes[$i] = $proc;
    stream_set_blocking($procPipes[1], false);
    $pipes[$i] = $procPipes;
}
while (array_filter($processes, function ($proc) {
    return proc_get_status($proc)['running'];
})) {
    foreach ($pipes as $pipe) {
        usleep(1000); // 1e-3 seconds

        // Read all available output (unread output is buffered).
        $content = stream_get_contents($pipe[1]);

        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = explode(",", $line);
            $name = $line[0];
            if ($name === "") {
                continue;
            }
            $min = (float)$line[1];
            $max = (float)$line[2];
            $sum = (float)$line[3];
            $count = (int)$line[4];
            if (isset($result[$name])) {
                $station = &$result[$name];
                $station[0][] = $min;
                $station[1][] = $max;
                $station[2] += $sum;
                $station[3] += $count;
            } else {
                $result[$name] = [[$min], [$max], $sum, $count];
            }
        }
    }

}

// Close all pipes and processes.
foreach ($pipes as $i => $pipe) {
    fclose($pipe[1]);
    proc_close($processes[$i]);
}

ksort($result, SORT_STRING);
foreach ($result as $name => $values) {
    [$min, $max, $sum, $count] = $values;
    $min = min($min);
    $max = max($max);
    $mean = round($sum / $count, 1);
    echo "$name=$min/$mean/$max\n";
}
echo "Done in " . round(microtime(true) - $start, 2) . "s\n";