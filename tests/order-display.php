<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use App\Support\OrderDisplay;
use App\Support\StoreMail;
use App\Mail\StoreMessage;

function check($pass, $label) {
    if (!$pass) throw new RuntimeException($label);
    echo "PASS $label\n";
}
config(['session.driver'=>'array']);
app('session')->forgetDrivers();
Auth::forgetGuards();
$session=app('session')->driver();
$session->start();
$r=Request::create('/account/orders','GET');
$r->setLaravelSession($session);
app()->instance('request',$r);
app('url')->setRequest($r);
view()->share('errors',new Illuminate\Support\ViewErrorBag);
Mail::fake();

DB::beginTransaction();
try {
    $user=App\Models\User::where('role','customer')->firstOrFail();
    $products=DB::table('products')->limit(2)->get();
    $items=$products->map(fn($p)=>[
        'id'=>$p->id,'name'=>$p->name,'size'=>'M','color'=>$p->color,
        'quantity'=>2,'price'=>500,'sku'=>$p->sku
    ])->all();
    $items[]=['name'=>'Archived sample item','size'=>'S','quantity'=>1,'price'=>200];
    $reference=(string) Illuminate\Support\Str::uuid();
    $id=DB::table('orders')->insertGetId([
        'user_id'=>$user->id,'reference'=>$reference,'name'=>'Demo Customer','email'=>'preview@example.test',
        'phone'=>'9876543210','address'=>'Sample delivery address','city'=>'Delhi','state'=>'Delhi',
        'pincode'=>'110001','items'=>json_encode($items),'subtotal'=>2200,'shipping'=>79,
        'discount'=>100,'total'=>2179,'status'=>'placed','payment_method'=>'cod',
        'created_at'=>now(),'updated_at'=>now()
    ]);
    $order=DB::table('orders')->find($id);
    $number=OrderDisplay::reference($order);
    check(OrderDisplay::referenceId($number)===$id,'Readable order number round trips without changing stored reference');
    $rows=OrderDisplay::items($order);
    check(count($rows)===3 && is_file($rows[0]['image_path']),'All order rows resolve with a local product image');
    check($rows[2]['image_path']===null,'Historical product without image has a safe fallback');

    StoreMail::order($reference);
    StoreMail::status($reference,'shipped');
    $mail=Mail::sent(StoreMessage::class)->first(fn($m)=>$m->heading==='Order confirmed');
    check(count($mail->orderDetails['items'])===3,'Confirmation email includes every product');
    $html=$mail->render();
    foreach($items as $item) check(str_contains($html,e($item['name'])),'Email renders product '.$item['name']);
    check((substr_count($html,'src="cid:') + substr_count($html,'src="data:image/'))>=2,'Local product photos are embedded in email');
    check(str_contains($html,$number)&&str_contains($html,'2,179'),'Email contains readable order number and accurate total');
    $status=Mail::sent(StoreMessage::class)->first(fn($m)=>$m->heading==='Order shipped');
    check(count($status->orderDetails['items'])===3,'Status email includes every product');

    Auth::login($user);
    $orders=new App\Http\Controllers\OrdersController;
    $view=$orders->show($number);
    $page=$view->render();
    check(str_contains($page,'order-details.css')&&substr_count($page,'class="ordered-item-image"')===3,'Order page renders thumbnails and readable styles');
    check($orders->show($reference)->getData()['order']->id===$id,'Existing order URLs still resolve');
    $track=Request::create('/track-order','POST',['reference'=>$number,'email'=>'preview@example.test']);
    $track->setLaravelSession($session);
    check($orders->track($track)->getData()['result']['reference']===$number,'Tracking accepts readable number with matching email');

    $dir=getenv('AVELLE_ORDER_PREVIEW');
    if ($dir) {
        $dir=rtrim($dir,'/\\');
        file_put_contents($dir.'/order-preview.html',$page);
        $html=preg_replace_callback('/src="cid:[^"]+"/',function($m)use(&$rows){
            foreach($rows as &$row) {
                if ($row['image_path'] && empty($row['_preview_used'])) {
                    $row['_preview_used']=true;
                    return 'src="data:image/jpeg;base64,'.base64_encode(file_get_contents($row['image_path'])).'"';
                }
            }
            return $m[0];
        },$html);
        file_put_contents($dir.'/email-preview.html',$html);
    }
    echo "Order display checks passed. Fixtures rolled back; no external emails sent.\n";
} finally {
    DB::rollBack();
    Auth::logout();
}
