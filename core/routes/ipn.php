<?php

use Illuminate\Support\Facades\Route;
$gatewayBasePath = app_path('Http/Controllers/Gateway');

$registerGatewayRoute = static function (string $httpMethod, string $uri, string $controller, string $routeName) use ($gatewayBasePath): void {
    [$controllerPath] = explode('@', $controller, 2);
    $filePath = $gatewayBasePath . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $controllerPath) . '.php';

    // Skip non-existent gateways in SaaS mode to avoid route/list boot failures.
    if (!is_file($filePath)) {
        return;
    }

    Route::{$httpMethod}($uri, $controller)->name($routeName);
};

$gatewayRoutes = [
    ['post', 'paypal', 'Paypal\ProcessController@ipn', 'Paypal'],
    ['get', 'paypal-sdk', 'PaypalSdk\ProcessController@ipn', 'PaypalSdk'],
    ['post', 'perfect-money', 'PerfectMoney\ProcessController@ipn', 'PerfectMoney'],
    ['post', 'stripe', 'Stripe\ProcessController@ipn', 'Stripe'],
    ['post', 'stripe-js', 'StripeJs\ProcessController@ipn', 'StripeJs'],
    ['post', 'stripe-v3', 'StripeV3\ProcessController@ipn', 'StripeV3'],
    ['post', 'skrill', 'Skrill\ProcessController@ipn', 'Skrill'],
    ['post', 'paytm', 'Paytm\ProcessController@ipn', 'Paytm'],
    ['post', 'payeer', 'Payeer\ProcessController@ipn', 'Payeer'],
    ['post', 'paystack', 'Paystack\ProcessController@ipn', 'Paystack'],
    ['get', 'flutterwave/{trx}/{type}', 'Flutterwave\ProcessController@ipn', 'Flutterwave'],
    ['post', 'razorpay', 'Razorpay\ProcessController@ipn', 'Razorpay'],
    ['post', 'instamojo', 'Instamojo\ProcessController@ipn', 'Instamojo'],
    ['get', 'blockchain', 'Blockchain\ProcessController@ipn', 'Blockchain'],
    ['post', 'coinpayments', 'Coinpayments\ProcessController@ipn', 'Coinpayments'],
    ['post', 'coinpayments-fiat', 'CoinpaymentsFiat\ProcessController@ipn', 'CoinpaymentsFiat'],
    ['post', 'coingate', 'Coingate\ProcessController@ipn', 'Coingate'],
    ['post', 'coinbase-commerce', 'CoinbaseCommerce\ProcessController@ipn', 'CoinbaseCommerce'],
    ['get', 'mollie', 'Mollie\ProcessController@ipn', 'Mollie'],
    ['post', 'cashmaal', 'Cashmaal\ProcessController@ipn', 'Cashmaal'],
    ['post', 'mercado-pago', 'MercadoPago\ProcessController@ipn', 'MercadoPago'],
    ['post', 'authorize', 'Authorize\ProcessController@ipn', 'Authorize'],
    ['get', 'nmi', 'NMI\ProcessController@ipn', 'NMI'],
    ['any', 'btc-pay', 'BTCPay\ProcessController@ipn', 'BTCPay'],
    ['post', 'now-payments-hosted', 'NowPaymentsHosted\ProcessController@ipn', 'NowPaymentsHosted'],
    ['post', 'now-payments-checkout', 'NowPaymentsCheckout\ProcessController@ipn', 'NowPaymentsCheckout'],
    ['post', '2checkout', 'TwoCheckout\ProcessController@ipn', 'TwoCheckout'],
    ['any', 'checkout', 'Checkout\ProcessController@ipn', 'Checkout'],
    ['post', 'sslcommerz', 'SslCommerz\ProcessController@ipn', 'SslCommerz'],
    ['post', 'aamarpay', 'Aamarpay\ProcessController@ipn', 'Aamarpay'],
    ['get', 'binance', 'Binance\ProcessController@ipn', 'Binance'],
    ['any', 'bkash', 'BKash\ProcessController@ipn', 'BKash'],
];

foreach ($gatewayRoutes as [$httpMethod, $uri, $controller, $routeName]) {
    $registerGatewayRoute($httpMethod, $uri, $controller, $routeName);
}
