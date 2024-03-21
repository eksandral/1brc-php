<?php
declare(strict_types=1);
$filename = $_SERVER['argv'][1] ?? 'measurements-100M.txt';
$concurrency = (int)($_SERVER['argv'][2] ?? 1);
const PHP_APP = <<<'PHP'
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
    fwrite($stdout, "$name;$min;$max;$sum;$count\n");
}
fclose($stdout);
fclose($fd);
exit(0);
PHP;

const CHUNK_SIZE = 64 * 1024 * 1024;
$fd = fopen($filename, "rb");
$result = new Box();
$pipes = [];
$processes = [];
$i = 0;
$fiberList = [];
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

    $fiber = new Fiber(processChunk(...));
    $fiber->start($chunk, $len, $result);

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

// Run in a loop until all subprocesses finish.
while (array_filter($processes, function ($proc) {
    return proc_get_status($proc)['running'];
})) {
    foreach ($pipes as $pipe) {
        usleep(1000); // 1e-3 seconds

        // Read all available output (unread output is buffered).
        $content = stream_get_contents($pipe[1]);

        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = explode(";", $line);
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
    $stderr = fopen("/tmp/multithread1.txt", "a");
    $descriptors = [0 => ['pipe', 'r'], // stdin
        1 => ['pipe', 'w'], // stdout
        2 => $stderr// stderr

    ];
    // Spawn a subprocess.
    $proc = proc_open('php -dmemory_limit=-1 -r \'' . PHP_APP . '\'', $descriptors, $procPipes);
    // Write input to the subprocess.
    fwrite($procPipes[0], $chunk, $len);
    fclose($procPipes[0]);

    // Make the subprocess non-blocking (only output pipe).
    stream_set_blocking($procPipes[1], false);
    do {
        //usleep(1000); //Wait 1ms before checking
        Fiber::suspend();
        $status = proc_get_status($proc);
    } while ($status['running']);
    // Read all available output (unread output is buffered).
    $content = stream_get_contents($procPipes[1]);

    $lines = explode("\n", $content);
    foreach ($lines as $line) {
        $line = explode(";", $line);
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
    fclose($procPipes[1]);
    fclose($stderr);
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
        usleep(1000);
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