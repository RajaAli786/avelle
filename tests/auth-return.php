<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use App\Support\AuthReturn;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\StoreController;

function check($pass, $label) {
    if (!$pass) throw new RuntimeException($label);
    echo "PASS $label\n";
}
config(['session.driver'=>'array']);
app('session')->forgetDrivers();
Auth::forgetGuards();
$session=app('session')->driver();
$session->start();
view()->share('errors',new Illuminate\Support\ViewErrorBag);
Mail::fake();
function authRequest(string $uri,array $data=[],string $method='GET') {
    global $session;
    $r=Request::create($uri,$method,$data);
    $r->setLaravelSession($session);
    $r->setUserResolver(fn()=>Auth::user());
    app()->instance('request',$r);
    app('url')->setRequest($r);
    return $r;
}

DB::beginTransaction();
try {
    Auth::logout();
    $r=authRequest('/checkout?step=delivery');
    $response=(new MarketplaceController)->checkout();
    check(str_ends_with($response->getTargetUrl(),'/account/login')&&str_contains($session->get('url.intended'),'/checkout?step=delivery'),'Guest checkout asks for login and remembers checkout URL');
    $users=DB::table('users')->count();
    $orders=DB::table('orders')->count();
    $api=(new StoreController)->checkout(authRequest('/api/orders',[],'POST'));
    check($api->getStatusCode()===401&&DB::table('users')->count()===$users&&DB::table('orders')->count()===$orders,'Anonymous order API cannot create an account or order');
    check(str_contains($api->getData(true)['login_url'],'return_to='),'Anonymous API provides the sign-in destination');

    $account=new AccountController;
    $target=url('/shop').'?q=dress&page=2#filters';
    $r=authRequest('/account/login',['return_to'=>$target]);
    $account->form($r);
    $account->registerForm(authRequest('/account/register'));
    check($session->get('url.intended')===$target,'Switching login and registration preserves original page');
    $password='QaReturnOnly!12345';
    $user=App\Models\User::create(['name'=>'Return QA','email'=>'auth-return-'.uniqid().'@example.test','password'=>$password]);
    $account->login(authRequest('/account/login',['email'=>$user->email,'password'=>'wrong'],'POST'));
    check(!Auth::check()&&$session->get('url.intended')===$target,'Failed sign-in preserves the return URL');
    $login=$account->login(authRequest('/account/login',['email'=>$user->email,'password'=>$password],'POST'));
    check($login->getTargetUrl()===$target&&Auth::id()===$user->id,'Successful sign-in returns to the same path, query and fragment');
    check(!$session->has('url.intended'),'Successful sign-in consumes the intended destination');

    Auth::logout();
    $registerTarget=url('/products/sample-product').'#reviews';
    $r=authRequest('/account/register',['return_to'=>$registerTarget]);
    $account->registerForm($r);
    $registered=$account->register(authRequest('/account/register',[
        'name'=>'New Return QA','email'=>'new-return-'.uniqid().'@example.test',
        'password'=>$password,'password_confirmation'=>$password,
    ],'POST'));
    check($registered->getTargetUrl()===$registerTarget&&Auth::check(),'Account creation signs in and returns to the originating page');
    Auth::user()->markEmailAsVerified();
    $view=(new MarketplaceController)->checkout();
    check(str_contains($view->render(),'checkoutForm'),'Authenticated customer can continue to checkout');

    authRequest('/account/login');
    foreach(['https://evil.example/path','//evil.example/path','javascript:alert(1)','http://name:secret@localhost/checkout'] as $unsafe) {
        check(AuthReturn::safe($unsafe)===null,'External or unsafe redirect is rejected');
    }
    check(AuthReturn::safe(url('/account/login'))===null,'Auth page return loops are rejected');
    Auth::logout();
    $session->forget('url.intended');
    $fallback=$account->login(authRequest('/account/login',[
        'email'=>$user->email,'password'=>$password,'return_to'=>'https://evil.example/path',
    ],'POST'));
    check(str_ends_with($fallback->getTargetUrl(),'/account'),'Invalid return targets fall back to the account');
    Auth::logout();
    $session->forget('url.intended');
    $private=authRequest('/account/orders');
    $account->index($private);
    check(str_ends_with($session->get('url.intended'),'/account/orders'),'Protected account pages remember their own URL');
    echo "Authentication return checks passed. Fixtures rolled back; no external emails sent.\n";
} finally {
    DB::rollBack();
    Auth::logout();
}
