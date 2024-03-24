<?php
declare(strict_types=1);
$filename = $_SERVER['argv'][1] ?? 'measurements-100M.txt';
$concurrency = (int)($_SERVER['argv'][2] ?? 1);
const PHP_APP = <<<'PHP'
$fd = fopen("php://stdin", "rb");
$result = [];
//$chunk = stream_get_contents($fd);
//$lines = explode("\n", $chunk);
//foreach ($lines as $line) {
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
PHP;

const CHUNK_SIZE = 64 * 1024 * 1024;
$fd = fopen($filename, "rb");
$result = new Box();
$pipes = [];
$i = 0;
$fiberList = [];
while (!feof($fd)) {
    $chunk = fread($fd, CHUNK_SIZE);
    if ($chunk === false) {
        break;
    }
    $len = CHUNK_SIZE;
    if ($chunk[-1] !== "\n") {
        //$subchunk = fgets($fd);
        $subchunk = stream_get_line($fd, 120, "\n");
        $len += strlen($subchunk);
        $chunk .= $subchunk;
    }

    $fiber = new Fiber(processChunk(...));
    $fiber->start($chunk."\n\n", $len, $result);

    $fiberList[] = $fiber;
    if (count($fiberList) >= $concurrency) {
        foreach (waitForFibers($fiberList, 1) as $fiber) {
        }
    }
    //processChunk($chunk, $len, $result);


}
while ($fiberList) {
    foreach ($fiberList as $idx => $fiber) {
        if ($fiber->isTerminated()) {
            $fiber->getReturn();
            //            echo 'Successfully created clip from ' . $source . ' => ' . $destination . PHP_EOL;
            unset($fiberList[$idx]);
        } else {
            $fiber->resume();
        }
    }
}


ksort($result->data, SORT_STRING);
foreach ($result->data as $name => $values) {
    [$min, $max, $sum, $count] = $values;
    $min = min($min);
    $max = max($max);
    $mean = $sum / $count;
    printf("%s=%01.2f/%01.2f/%01.2f\n", $name, $min, $mean, $max);
}

/// FUNCTIONS
function processChunk(string $chunk, int $len, Box $data): void
{
    $result = &$data->data;
    $descriptors = [0 => ['pipe', 'r'], // stdin
        1 => ['pipe', 'w'], // stdout

    ];
    // Spawn a subprocess.
    $proc = proc_open('php  -dmemory_limit=-1 -r \'' . PHP_APP . '\'', $descriptors, $procPipes);
    // Write input to the subprocess.
    fwrite($procPipes[0], $chunk, $len);
    fclose($procPipes[0]);

    $input = &$procPipes[1];
    // Make the subprocess non-blocking (only output pipe).
    stream_set_blocking($input, false);
    do {
        //usleep(1000); //Wait 1ms before checking
        Fiber::suspend();
        $status = proc_get_status($proc);
    } while ($status['running']);
    // Read all available output (unread output is buffered).

    while ($line = stream_get_line($input, 128, "\n")){
    //foreach ($lines as $line) {
        $line = explode(";", $line);
        $name = $line[0];
        if ($name === "") {
            continue;
        }
        $min = (float)$line[1];
        $max = (float)$line[2];
        $sum = (float)$line[3];
        $count = (int)$line[4];
            $station = &$result[$name];
        if ($station !== null) {
            $station[0][] = $min;
            $station[1][] = $max;
            $station[2] += $sum;
            $station[3] += $count;
        } else {
            $station = [[$min], [$max], $sum, $count];
        }
    }
    fclose($input);
    proc_close($proc);
}

class Box
{
    public array $data = [];
}

function waitForFibers(array &$fiberList, ?int $completionCount = null): array
{
    $completedFibers = [];
    $completionCount ??= count($fiberList);
    while (count($fiberList) && count($completedFibers) < $completionCount) {
        usleep(2000);
        foreach ($fiberList as $idx => $fiber) {
            if ($fiber->isSuspended()) {
                $fiber->resume();
            } else if ($fiber->isTerminated()) {
                $completedFibers[] = $fiber;
                unset($fiberList[$idx]);
            }
        }
    }

    return $completedFibers;
}
