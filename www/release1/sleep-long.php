<?php
$targetSleepTime = 5.0; // Total desired sleep time in seconds

$startTime = microtime(true); // Record the start time

while ((microtime(true) - $startTime) < $targetSleepTime) {
    usleep(100000); // Sleep for 0.1 seconds
}

$currDir = getcwd();

echo "ver1: Success. Slept for $targetSleepTime seconds. CWD: $currDir\n";
?>
