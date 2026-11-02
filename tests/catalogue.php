<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Models\User;
use App\Support\StoreOffers;
function check($condition,$message){if(!$condition)throw new RuntimeException($message);echo "PASS ".$message.PHP_EOL;}
function rejects($fn,$message){try{$fn();}catch(ValidationException $e){check(true,$message);return;}throw new RuntimeException('Expected validation rejection: '.$message);}
function denied($fn,$message){try{$fn();}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===403,$message);return;}throw new RuntimeException('Expected access rejection: '.$message);}
config(['session.driver'=>'array']);app('session')->forgetDrivers();Auth::forgetGuards();$session=app('session')->driver();$session->start();
function req($data=[]){$r=Request::create('/','POST',$data);$r->setLaravelSession(app('session')->driver());$r->setUserResolver(fn()=>Auth::user());return $r;}
view()->share('errors',new Illuminate\Support\ViewErrorBag);

use App\Http\Controllers\CatalogAdminController;
use App\Support\Inventory;
use App\Support\Shop;
$files=[];DB::beginTransaction();
try {
$owner=User::create(['name'=>'Catalogue QA','email'=>'catalogue-qa-'.uniqid().'@example.test','password'=>Hash::make('TestOnly123456!'),'role'=>'admin','is_active'=>true]);$owner->forceFill(['role'=>'admin'])->save();Auth::login($owner);
$c=new CatalogAdminController;
$c->category(req(['name'=>'QA Category '.uniqid(),'sort_order'=>0,'active'=>1,'description'=>'Catalogue fixture']));$cat=DB::table('categories')->latest('id')->first();
check((bool)$cat->active,'Admin creates category');
$img=function(){ $tmp=tempnam(sys_get_temp_dir(),'avelle');file_put_contents($tmp,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j7l8AAAAASUVORK5CYII='));return new Illuminate\Http\UploadedFile($tmp,'test.png','image/png',null,true);};
$r=req(['name'=>'QA Gallery Product','category_id'=>$cat->id,'audience'=>'girls','description'=>'A detailed product description for the catalogue verification.','highlights'=>"Comfortable\nEveryday style",'spec_keys'=>['Fabric','Care'],'spec_values'=>['Cotton','Hand wash'],'price'=>500,'old'=>700,'color'=>'Pink','sizes'=>'S, M','stock'=>8,'active'=>1,'featured'=>1]);$r->files->set('images',[$img(),$img()]);$c->product($r);$p=DB::table('products')->latest('id')->first();
$images=DB::table('product_images')->where('product_id',$p->id)->get();foreach($images as $i)$files[]=public_path($i->path);
check($images->count()===2&&is_file($files[0])&&is_file($files[1]),'Multiple product images uploaded and stored');
check(json_decode($p->specifications,true)[0]['value']==='Cotton'&&str_contains($p->description,'detailed'),'Product description and additional information persist');
$c->mediaOrder(req(['order'=>$images->pluck('id')->reverse()->values()->all()]),'product',$p->id);check(Shop::decorate($p)->images->first()->id===$images->last()->id,'Image order changes the product cover');
$c->variants(req(['variants'=>[['size'=>'S','color'=>'Pink','sku'=>'QA-'.uniqid(),'price'=>450,'stock'=>2,'active'=>1],['size'=>'M','color'=>'Pink','sku'=>'QA-'.uniqid(),'price'=>null,'stock'=>3,'active'=>1]]]),$p->id);
check(DB::table('products')->where('id',$p->id)->value('stock')===5,'Variant stocks update total inventory');
$variant=DB::table('product_variants')->where('product_id',$p->id)->where('size','S')->first();$bag=Inventory::cart(['items'=>[['id'=>$p->id,'variant_id'=>$variant->id,'size'=>'S','quantity'=>2]]],false);check($bag[1]===900,'Variant price calculated on the server');
rejects(fn()=>Inventory::cart(['items'=>[['id'=>$p->id,'variant_id'=>$variant->id,'size'=>'S','quantity'=>2],['id'=>$p->id,'variant_id'=>$variant->id,'size'=>'S','quantity'=>1]]],false),'Duplicate variants cannot exceed stock');
$c->category(req(['id'=>$cat->id,'name'=>$cat->name,'sort_order'=>0,'active'=>0]));check(!Shop::productQuery()->where('products.id',$p->id)->exists(),'Hidden category hides its products');
$r=req(['id'=>$cat->id,'name'=>$cat->name,'sort_order'=>0,'active'=>1]);$r->files->set('images',[$img(),$img()]);$c->category($r);$ci=DB::table('category_images')->where('category_id',$cat->id)->get();foreach($ci as $i)$files[]=public_path($i->path);check($ci->count()===2,'Multiple category images uploaded');
rejects(fn()=>$c->category(req(['id'=>$cat->id,'name'=>$cat->name,'parent_id'=>$cat->id,'sort_order'=>0,'active'=>1])),'Category cannot become its own parent');
$html=(new AdminController)->index(req())->render();check(str_contains($html,'Additional information')&&str_contains($html,'Upload multiple product images')&&str_contains($html,'Category images'),'Admin gallery and specification forms render');
echo "Catalogue checks passed. Fixtures rolled back.\n";
}finally {DB::rollBack();foreach($files as $file)if(is_file($file))unlink($file);Auth::logout();}
