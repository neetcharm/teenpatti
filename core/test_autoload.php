<?php
require 'vendor/autoload.php';
echo "Autoloaded successfully\n";
use Illuminate\Foundation\Application;
echo "Application class exists: " . (class_exists(Application::class) ? 'YES' : 'NO') . "\n";
