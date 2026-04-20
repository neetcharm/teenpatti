<?php
$lines = file('core/storage/logs/laravel.log');
$lastLines = array_slice($lines, -100);
file_put_contents('readlog.txt', implode('', $lastLines));
