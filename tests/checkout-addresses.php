<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use App\Support\AddressBook;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\ShoppingController;
use App\Http\Controllers\MarketplaceController;

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
function requestFor(array $data=[],string $uri='/api/orders') {
    global $session;
    $r=Request::create($uri,'POST',$data);
    $r->setLaravelSession($session);
    $r->setUserResolver(fn()=>Auth::user());
    app()->instance('request',$r);
    app('url')->setRequest($r);
    return $r;
}

DB::beginTransaction();
try {
    $user=App\Models\User::create(['name'=>'Anita Preview','email'=>'address-'.uniqid().'@example.test','password'=>bin2hex(random_bytes(16))]);
    $other=App\Models\User::create(['name'=>'Other Preview','email'=>'other-'.uniqid().'@example.test','password'=>bin2hex(random_bytes(16))]);
    Auth::login($user);
    requestFor();
    $address=['label'=>'Home','name'=>'Anita Preview','phone'=>'9876543210','address'=>'Sample address for checkout','city'=>'Delhi','state'=>'Delhi','pincode'=>'110001'];
    $home=AddressBook::save($user->id,$address);
    check($home->is_default,'First saved address becomes default');
    $same=AddressBook::save($user->id,$address);
    check($same->id===$home->id,'Repeated address saves do not create duplicates');
    $office=AddressBook::save($user->id,array_merge($address,['label'=>'Office','address'=>'Sample office address']));
    $edited=AddressBook::save($user->id,array_merge($address,['id'=>$home->id,'address'=>'Edited home address']));
    check($edited->is_default&&$edited->address==='Edited home address','Editing an existing address keeps its default state');
    $foreign=AddressBook::save($other->id,$address);
    try {
        AddressBook::save($user->id,array_merge($address,['id'=>$foreign->id]));
        throw new RuntimeException('Foreign address accepted');
    } catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
        check($e->getStatusCode()===404,'A customer cannot change another customer address');
    }
    $r=requestFor(array_merge($address,['id'=>$office->id,'address'=>'Updated office address']),'/account/addresses');
    $r->headers->set('Accept','application/json');
    $response=(new ShoppingController)->address($r)->getData(true);
    check($response['address']['address']==='Updated office address','Checkout address editor returns saved address JSON');

    $html=(new MarketplaceController)->checkout()->render();
    check(str_contains($html,'name="saved_address_id" value="'.$home->id.'"')&&str_contains($html,'Edited home address'),'Checkout preloads the default saved address');
    check(str_contains($html,'checkoutAddressEditor')&&str_contains($html,'data-edit-address'),'Checkout renders new and edit address controls');
    check(str_contains($html,'>Anita</span>'),'Header displays customer first name');

    $p=DB::table('products')->where('active',true)->where('stock','>',2)->first();
    $variant=DB::table('product_variants')->where('product_id',$p->id)->where('active',true)->where('stock','>',1)->first();
    $item=['id'=>$p->id,'size'=>$variant?->size??json_decode($p->sizes,true)[0],'quantity'=>1,'variant_id'=>$variant?->id];
    $payload=array_merge($address,['email'=>$user->email,'items'=>[$item],'saved_address_id'=>$home->id,'address_label'=>'Home','address'=>'Changed at checkout','checkout_key'=>(string) Illuminate\Support\Str::uuid()]);
    $store=new StoreController;
    $result=$store->checkout(requestFor($payload))->getData(true);
    check(DB::table('addresses')->where('id',$home->id)->value('address')==='Changed at checkout','Placing an order persists edited delivery details');
    $count=DB::table('addresses')->where('user_id',$user->id)->count();
    $store->checkout(requestFor($payload));
    check(DB::table('addresses')->where('user_id',$user->id)->count()===$count,'Repeated checkout keeps saved addresses consistent');
    $snapshot=DB::table('orders')->where('reference',$result['reference'])->value('address');
    AddressBook::save($user->id,array_merge($address,['id'=>$home->id,'address'=>'Changed later']));
    check(DB::table('orders')->where('reference',$result['reference'])->value('address')===$snapshot,'Editing saved addresses preserves earlier order delivery snapshots');

    $before=DB::table('orders')->count();
    $payload['saved_address_id']=$foreign->id;
    $payload['checkout_key']=(string) Illuminate\Support\Str::uuid();
    try {
        $store->checkout(requestFor($payload));
        throw new RuntimeException('Foreign checkout address accepted');
    } catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
        check($e->getStatusCode()===404&&DB::table('orders')->count()===$before,'Cross-account checkout address is rejected without creating an order');
    }
    echo "Checkout address checks passed. Fixtures rolled back; no external emails sent.\n";
} finally {
    DB::rollBack();
    Auth::logout();
}
