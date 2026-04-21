<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Lib\FileManager;
use App\Models\UpdateLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SystemController extends Controller
{
    public function systemInfo()
    {
        $laravelVersion = app()->version();
        $timeZone       = config('app.timezone');
        $pageTitle      = 'Application Information';
        return view('admin.system.info', compact('pageTitle', 'laravelVersion', 'timeZone'));
    }

    public function optimize()
    {
        $pageTitle = 'Clear System Cache';
        return view('admin.system.optimize', compact('pageTitle'));
    }

    public function optimizeClear()
    {
        Artisan::call('optimize:clear');
        $notify[] = ['success', 'Cache cleared successfully'];
        return back()->withNotify($notify);
    }

    public function systemServerInfo()
    {
        $currentPHP    = phpversion();
        $pageTitle     = 'Server Information';
        $serverDetails = $_SERVER;
        return view('admin.system.server', compact('pageTitle', 'currentPHP', 'serverDetails'));
    }

    public function systemUpdate()
    {
        $pageTitle = 'System Updates';
        return view('admin.system.update', compact('pageTitle'));
    }

    public function systemUpdateProcess()
    {
        return response()->json([
            'status'  => 'info',
            'message' => ['Automatic vendor updates have been removed from this build. Apply updates manually when needed.'],
        ]);
    }

    public function systemUpdateLog()
    {
        $pageTitle = 'System Update Log';
        $updates   = UpdateLog::orderBy('id', 'desc')->paginate(getPaginate());
        return view('admin.system.update_log', compact('pageTitle', 'updates'));
    }

    protected function extractZip($file, $extractTo)
    {
        $zip = new \ZipArchive;
        $res = $zip->open($file);
        if ($res != true) {
            return false;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            if ($zip->getNameIndex($i) != '/' && $zip->getNameIndex($i) != '__MACOSX/_') {
                $zip->extractTo($extractTo, array($zip->getNameIndex($i)));
            }
        }

        $zip->close();
        return true;
    }

    protected function removeFile($path)
    {
        $fileManager = new FileManager();
        $fileManager->removeFile($path);
    }

    protected function removeDir($location)
    {
        $fileManager = new FileManager();
        $fileManager->removeDirectory($location);
    }
}
