<?php
require __DIR__.'/../../packages/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$request = Illuminate\Http\Request::create('http://127.0.0.1:8095/onboarding/password');
$request->setLaravelSession($app['session']->driver());
$app->instance('request', $request);
Illuminate\Support\Facades\View::share('errors', new Illuminate\Support\ViewErrorBag());
file_put_contents(__DIR__.'/../../tmp/auth-onboarding-preview.html', view('auth.change-temporary-password')->render());


