<?php

namespace App\Http\Controllers;

abstract class Controller
{
    public function __construct()
    {
        // Keep constructor for child controllers that call parent::__construct().
    }

    public static function middleware()
    {
        return [];
    }
}
