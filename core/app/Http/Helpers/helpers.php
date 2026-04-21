<?php

use App\Constants\Status;
use App\Lib\Captcha;
use App\Lib\ClientInfo;
use App\Lib\FileManager;
use App\Lib\GoogleAuthenticator;
use App\Models\CommissionLog;
use App\Models\Extension;
use App\Models\Frontend;
use App\Models\GeneralSetting;
use App\Models\Language;
use App\Models\Referral;
use App\Models\Transaction;
use App\Models\User;
use App\Notify\Notify;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

function systemDetails() {
    $system['name']          = 'My Games';
    $system['version']       = '3.8';
    $system['build_version'] = '5.1.19';
    return $system;
}

function slug($string) {
    return Str::slug($string);
}

function verificationCode($length) {
    if ($length == 0) {
        return 0;
    }

    $min = pow(10, $length - 1);
    $max = (int) ($min - 1) . '9';
    return random_int($min, $max);
}

function getNumber($length = 8) {
    $characters       = '1234567890';
    $charactersLength = strlen($characters);
    $randomString     = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}

function activeTemplate($asset = false) {
    $template = session('template') ?? gs('active_template');
    if ($asset) {
        return 'assets/templates/' . $template . '/';
    }

    return 'templates.' . $template . '.';
}

function activeTemplateName() {
    $template = session('template') ?? gs('active_template');
    return $template;
}

function liveGameAliases() {
    return [
        'teen_patti',
    ];
}

function liveAutoBetAliases() {
    return [
        'teen_patti',
    ];
}

function gameMeta($game) {
    if (!$game) {
        return [];
    }

    $rawMeta = $game->type ?? null;

    if (is_array($rawMeta)) {
        return $rawMeta;
    }

    if (is_object($rawMeta)) {
        return (array) $rawMeta;
    }

    if (!is_string($rawMeta) || trim($rawMeta) === '') {
        return [];
    }

    $decoded = json_decode($rawMeta, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return [];
    }

    return $decoded;
}

function gameAutoBetDelay($game, $default = 15) {
    $delay = (int) (gameMeta($game)['auto_bet_delay'] ?? $default);

    if ($delay <= 0) {
        $delay = (int) $default;
    }

    return max(5, min($delay, 120));
}

function siteLogo($type = null) {
    $name = $type ? "/logo_$type.png" : '/logo.png';
    return getImage(getFilePath('logoIcon') . $name);
}

function siteFavicon() {
    return getImage(getFilePath('logoIcon') . '/favicon.png');
}

function loadReCaptcha() {
    return Captcha::reCaptcha();
}

function loadCustomCaptcha($width = '100%', $height = 46, $bgColor = '#003') {
    return Captcha::customCaptcha($width, $height, $bgColor);
}

function verifyCaptcha() {
    return Captcha::verify();
}

function loadExtension($key) {
    $extension = Extension::where('act', $key)->where('status', Status::ENABLE)->first();
    return $extension ? $extension->generateScript() : '';
}

function getTrx($length = 12) {
    $characters       = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ123456789';
    $charactersLength = strlen($characters);
    $randomString     = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}

function getAmount($amount, $length = 2) {
    $amount = round($amount ?? 0, $length);
    return $amount + 0;
}

function showAmount($amount, $decimal = 2, $separate = true, $exceptZeros = false, $currencyFormat = true) {
    $separator = '';
    if ($separate) {
        $separator = ',';
    }
    $printAmount = number_format($amount, $decimal, '.', $separator);
    if ($exceptZeros) {
        $exp = explode('.', $printAmount);
        if ($exp[1] * 1 == 0) {
            $printAmount = $exp[0];
        } else {
            $printAmount = rtrim($printAmount, '0');
        }
    }
    if ($currencyFormat) {
        if (gs('currency_format') == Status::CUR_BOTH) {
            return gs('cur_sym') . $printAmount . ' ' . __(gs('cur_text'));
        } else if (gs('currency_format') == Status::CUR_TEXT) {
            return $printAmount . ' ' . __(gs('cur_text'));
        } else {
            return gs('cur_sym') . $printAmount;
        }
    }
    return $printAmount;
}

/**
 * Format bet amount for display in chips
 */
function formatBetAmount($amount) {
    $amount = floatval($amount);
    if ($amount >= 1000) {
        $k = $amount / 1000;
        return (floor($k * 10) / 10) . 'K';
    }
    return number_format($amount, 0);
}

function removeElement($array, $value) {
    return array_diff($array, (is_array($value) ? $value : array($value)));
}

function cryptoQR($wallet) {
    return "https://api.qrserver.com/v1/create-qr-code/?data=$wallet&size=300x300&ecc=m";
}

function keyToTitle($text) {
    return ucfirst(preg_replace("/[^A-Za-z0-9 ]/", ' ', $text));
}

function titleToKey($text) {
    return strtolower(str_replace(' ', '_', $text));
}

function strLimit($title = null, $length = 10) {
    return Str::limit($title, $length);
}

function getIpInfo() {
    $ipInfo = ClientInfo::ipInfo();
    return $ipInfo;
}

function osBrowser() {
    $osBrowser = ClientInfo::osBrowser();
    return $osBrowser;
}

function getTemplates() {
    return null;
}

function getPageSections($arr = false) {
    $jsonUrl  = resource_path('views/') . str_replace('.', '/', activeTemplate()) . 'sections.json';
    $sections = json_decode(file_get_contents($jsonUrl));
    if ($arr) {
        $sections = json_decode(file_get_contents($jsonUrl), true);
        ksort($sections);
    }
    return $sections;
}

function getImage($image, $size = null) {
    $clean = '';
    if (file_exists($image) && is_file($image)) {
        return asset($image) . $clean;
    }
    if ($size) {
        return route('placeholder.image', $size);
    }
    return asset('assets/images/default.png');
}

function notify($user, $templateName, $shortCodes = null, $sendVia = null, $createLog = true, $pushImage = null) {
    $globalShortCodes = [
        'site_name'       => gs('site_name'),
        'site_currency'   => gs('cur_text'),
        'currency_symbol' => gs('cur_sym'),
    ];

    if (gettype($user) == 'array') {
        $user = (object) $user;
    }

    $shortCodes = array_merge($shortCodes ?? [], $globalShortCodes);

    $notify               = new Notify($sendVia);
    $notify->templateName = $templateName;
    $notify->shortCodes   = $shortCodes;
    $notify->user         = $user;
    $notify->createLog    = $createLog;
    $notify->pushImage    = $pushImage;
    $notify->userColumn   = isset($user->id) ? $user->getForeignKey() : 'user_id';
    $notify->send();
}

function getPaginate($paginate = null) {
    if (!$paginate) {
        $paginate = gs('paginate_number');
    }
    return $paginate;
}

function paginateLinks($data, $view = null) {
    return $data->appends(request()->all())->links($view);
}

function menuActive($routeName, $type = null, $param = null) {
    if ($type == 3) {
        $class = 'side-menu--open';
    } else if ($type == 2) {
        $class = 'sidebar-submenu__open';
    } else {
        $class = 'active';
    }

    if (is_array($routeName)) {
        foreach ($routeName as $key => $value) {
            if (request()->routeIs($value)) {
                return $class;
            }

        }
    } else if (request()->routeIs($routeName)) {
        $routeParam = array_values(isset(request()->route()->parameters) ? request()->route()->parameters : []);
        $firstParam = $routeParam[0] ?? null;
        if ($param) {
            if (is_string($firstParam) && is_string($param) && strcasecmp($firstParam, $param) === 0) {
                return $class;
            }
        } else {
            return $class;
        }
    }
}

function fileUploader($file, $location, $size = null, $old = null, $thumb = null, $filename = null) {
    $fileManager           = new FileManager($file);
    $fileManager->path     = $location;
    $fileManager->size     = $size;
    $fileManager->old      = $old;
    $fileManager->thumb    = $thumb;
    $fileManager->filename = $filename;
    $fileManager->upload();
    return $fileManager->filename;
}

function fileManager() {
    return new FileManager();
}

function getFilePath($key) {
    return fileManager()->$key()->path;
}

function getFileSize($key) {
    return fileManager()->$key()->size;
}

function getFileExt($key) {
    return fileManager()->$key()->extensions;
}

function diffForHumans($date) {
    $lang = session()->get('lang');
    if (!$lang) {
        $lang = getDefaultLang();
    }
    Carbon::setlocale($lang);
    return Carbon::parse($date)->diffForHumans();
}

function showDateTime($date, $format = 'Y-m-d h:i A') {
    if (!$date) {
        return '-';
    }
    $lang = session()->get('lang');
    if (!$lang) {
        $lang = getDefaultLang();
    }
    Carbon::setlocale($lang);
    return Carbon::parse($date)->translatedFormat($format);
}

function safeFrontendDataObject($data = null): object {
    if (is_object($data) && method_exists($data, '__isSafeFrontendProxy')) {
        return $data;
    }

    $payload = [];
    if (is_object($data)) {
        $payload = get_object_vars($data);
    } elseif (is_array($data)) {
        $payload = $data;
    }

    return new class($payload) {
        private array $payload;

        public function __construct(array $payload) {
            $this->payload = $payload;
        }

        public function __isSafeFrontendProxy(): bool {
            return true;
        }

        public function __get($name) {
            $value = $this->payload[$name] ?? null;
            if (is_array($value) || is_object($value)) {
                return safeFrontendDataObject($value);
            }
            return $value;
        }

        public function __isset($name) {
            return array_key_exists($name, $this->payload);
        }

        public function __toString() {
            return '';
        }
    };
}

function frontendContentFallback(): object {
    $fallback = new stdClass();
    $fallback->data_values = safeFrontendDataObject();
    $fallback->seo_content = safeFrontendDataObject();
    $fallback->slug = '';
    $fallback->tempname = activeTemplateName();
    $fallback->data_keys = '';
    return $fallback;
}

function defaultGeneralSettingObject(): object {
    $default = new stdClass();
    $default->site_name = 'MyGames';
    $default->cur_text = 'USD';
    $default->cur_sym = '$';
    $default->currency_format = Status::CUR_SYM;
    $default->active_template = 'parimatch';
    $default->paginate_number = 20;
    return $default;
}

function getDefaultLang() {
    $defaultLanguage = Language::where('is_default', Status::YES)->first();
    return $defaultLanguage->code ?? 'en';
}

function getContent($dataKeys, $singleQuery = false, $limit = null, $orderById = false) {

    $templateName = activeTemplateName();
    if ($singleQuery) {
        $content = Frontend::where('tempname', $templateName)->where('data_keys', $dataKeys)->orderBy('id', 'desc')->first();
        if (!$content) {
            return frontendContentFallback();
        }
        $content->data_values = safeFrontendDataObject($content->data_values ?? null);
        $content->seo_content = safeFrontendDataObject($content->seo_content ?? null);
    } else {
        $article = Frontend::where('tempname', $templateName);
        $article->when($limit != null, function ($q) use ($limit) {
            return $q->limit($limit);
        });
        if ($orderById) {
            $content = $article->where('data_keys', $dataKeys)->orderBy('id')->get();
        } else {
            $content = $article->where('data_keys', $dataKeys)->orderBy('id', 'desc')->get();
        }

        $content->transform(function ($item) {
            $item->data_values = safeFrontendDataObject($item->data_values ?? null);
            $item->seo_content = safeFrontendDataObject($item->seo_content ?? null);
            return $item;
        });
    }
    return $content;
}

function verifyG2fa($user, $code, $secret = null) {
    $authenticator = new GoogleAuthenticator();
    if (!$secret) {
        $secret = $user->tsc;
    }
    $oneCode  = $authenticator->getCode($secret);
    $userCode = $code;
    if ($oneCode == $userCode) {
        $user->tv = Status::YES;
        $user->save();
        return true;
    } else {
        return false;
    }
}

function urlPath($routeName, $routeParam = null) {
    if ($routeParam == null) {
        $url = route($routeName);
    } else {
        $url = route($routeName, $routeParam);
    }
    $basePath = route('home');
    $path     = str_replace($basePath, '', $url);
    return $path;
}

function showMobileNumber($number) {
    $length = strlen($number);
    return substr_replace($number, '***', 2, $length - 4);
}

function showEmailAddress($email) {
    $endPosition = strpos($email, '@') - 1;
    return substr_replace($email, '***', 1, $endPosition);
}

function getRealIP() {
    try {
        if (app()->bound('request')) {
            $ip = request()->ip();
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip == '::1' ? '127.0.0.1' : $ip;
            }
        }
    } catch (\Throwable $e) {
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if ($ip == '::1') {
        $ip = '127.0.0.1';
    }

    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
}

function cronJobKey() {
    $appKey = (string) config('app.key');

    if (str_starts_with($appKey, 'base64:')) {
        $decodedKey = base64_decode(substr($appKey, 7), true);
        if ($decodedKey !== false) {
            $appKey = $decodedKey;
        }
    }

    return hash_hmac('sha256', 'cron-job-execution', (string) $appKey);
}

function safeDownloadPath($filePath, array $allowedDirectories = []) {
    if (!is_string($filePath) || $filePath === '') {
        return false;
    }

    $projectRoot = dirname(base_path());
    $normalized  = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($filePath));
    $isAbsolute  = preg_match('/^[A-Za-z]:[\\\\\\/]/', $normalized) || str_starts_with($normalized, DIRECTORY_SEPARATOR);
    $absolute    = $isAbsolute ? $normalized : $projectRoot . DIRECTORY_SEPARATOR . ltrim($normalized, DIRECTORY_SEPARATOR);
    $realPath    = realpath($absolute);

    if (!$realPath || !is_file($realPath)) {
        return false;
    }

    foreach ($allowedDirectories as $allowedDirectory) {
        if (!$allowedDirectory || !is_string($allowedDirectory)) {
            continue;
        }

        $allowedDirectory = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($allowedDirectory));
        $allowedAbsolute  = preg_match('/^[A-Za-z]:[\\\\\\/]/', $allowedDirectory) || str_starts_with($allowedDirectory, DIRECTORY_SEPARATOR)
            ? $allowedDirectory
            : $projectRoot . DIRECTORY_SEPARATOR . ltrim($allowedDirectory, DIRECTORY_SEPARATOR);
        $allowedReal = realpath($allowedAbsolute);

        if (!$allowedReal || !is_dir($allowedReal)) {
            continue;
        }

        $allowedPrefix = rtrim($allowedReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (str_starts_with($realPath, $allowedPrefix)) {
            return $realPath;
        }
    }

    return false;
}

function appendQuery($key, $value) {
    return request()->fullUrlWithQuery([$key => $value]);
}

function dateSort($a, $b) {
    return strtotime($a) - strtotime($b);
}

function dateSorting($arr) {
    usort($arr, "dateSort");
    return $arr;
}

function gs($key = null) {
    try {
        $general = Cache::get('GeneralSetting');
    } catch (\Throwable $e) {
        $general = null;
    }

    if (!$general) {
        try {
            $general = GeneralSetting::first();
            if ($general) {
                Cache::put('GeneralSetting', $general);
            }
        } catch (\Throwable $e) {
            $general = null;
        }
    }

    if (!$general) {
        $general = defaultGeneralSettingObject();
    }

    if ($key) {
        return @$general->$key;
    }
    return $general;
}
function isImage($string) {
    $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif');
    $fileExtension     = pathinfo($string, PATHINFO_EXTENSION);
    if (in_array($fileExtension, $allowedExtensions)) {
        return true;
    } else {
        return false;
    }
}

function isHtml($string) {
    if (preg_match('/<.*?>/', $string)) {
        return true;
    } else {
        return false;
    }
}

function convertToReadableSize($size) {
    preg_match('/^(\d+)([KMG])$/', $size, $matches);
    $size = (int) $matches[1];
    $unit = $matches[2];

    if ($unit == 'G') {
        return $size . 'GB';
    }
    if ($unit == 'M') {
        return $size . 'MB';
    }
    if ($unit == 'K') {
        return $size . 'KB';
    }
    return $size . $unit;
}

function frontendImage($sectionName, $image, $size = null, $seo = false) {
    if ($seo) {
        return getImage('assets/images/frontend/' . $sectionName . '/seo/' . $image, $size);
    }
    return getImage('assets/images/frontend/' . $sectionName . '/' . $image, $size);
}

function levelCommission($id, $amount, $commissionType = '') {
    $usr   = $id;
    $i     = 1;
    $gnl   = gs();
    $level = Referral::count();

    while ($usr != "" || $usr != "0" || $i < $level) {
        $me    = User::find($usr);
        $refer = User::find($me->ref_by);
        if ($refer == "") {
            break;
        }

        $commission = Referral::where('level', $i)->first();
        if ($commission == null) {
            break;
        }

        $com                  = ($amount * $commission->percent) / 100;
        $referWallet          = User::where('id', $refer->id)->first();
        $newBal               = getAmount($referWallet->balance + $com);
        $referWallet->balance = $newBal;
        $referWallet->save();
        $trx = getTrx();

        $transaction               = new Transaction();
        $transaction->user_id      = $refer->id;
        $transaction->amount       = getAmount($com);
        $transaction->charge       = 0;
        $transaction->trx_type     = '+';
        $transaction->remark       = 'commission';
        $transaction->details      = $i . ' level Referral Commission';
        $transaction->trx          = $trx;
        $transaction->post_balance = $newBal;
        $transaction->save();

        $commission           = new CommissionLog();
        $commission->user_id  = $refer->id;
        $commission->who      = $id;
        $commission->level    = $i . ' level Referral Commission';
        $commission->amount   = getAmount($com);
        $commission->main_amo = $newBal;
        $commission->title    = $commissionType;
        $commission->trx      = $trx;
        $commission->save();

        notify($refer, 'REFERRAL_COMMISSION', [
            'amount'       => getAmount($com),
            'post_balance' => $newBal,
            'trx'          => $trx,
            'level'        => $i . ' level Referral Commission',
            'currency'     => $gnl->cur_text,
        ]);
        $usr = $refer->id;
        $i++;
    }
    return 0;
}

function buildResponse($remark, $status, $notify, $data = null) {
    $response = [
        'remark' => $remark,
        'status' => $status,
    ];

    $message = [];

    if ($notify instanceof \Illuminate\Support\MessageBag) {
        $message['error'] = collect($notify)->map(function ($item) {
            return $item[0];
        })->values()->toArray();
    } else {
        $message = [$status => collect($notify)->map(function ($item) {
            if (is_string($item)) {
                return $item;
            }
            if (count($item) > 1) {
                return $item[1];
            }
            return $item[0];
        })->toArray()];
    }

    $response['message'] = $message;
    if ($data) {
        $response['data'] = $data;
    }
    return response()->json($response);
}

function responseSuccess($remark, $notify, $data = null) {
    return buildResponse($remark, 'success', $notify, $data);
}

function responseError($remark, $notify, $data = null) {
    return buildResponse($remark, 'error', $notify, $data);
}
