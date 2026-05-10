<?php
$pattern = '/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/';
$test = 'RSSMRA80A01H501W';
echo "Test $test: " . (preg_match($pattern, $test) ? "OK" : "FAIL") . "\n";
