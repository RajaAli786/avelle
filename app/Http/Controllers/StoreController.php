<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class StoreController {
 public function index(){return view('store',['offers'=>\App\Support\StoreOffers::visible(),'accountData'=>\Illuminate\Support\Facades\Auth::user()?->only(['name','email','phone','address','city','pincode'])]);}
 public function offers(){return response()->json(\App\Support\StoreOffers::visible())->header('Cache-Control','no-store');}
 public function products(){return response()->json(DB::table('products')->get()->map(function($p){$p->sizes=json_decode($p->sizes);return $p;}));}
 private function cart(array $data,bool $lock=false):array{return \App\Support\Inventory::cart($data,$lock);}
 private function rules():array{return ['items'=>'required|array|min:1|max:30','items.*.id'=>'required|integer','items.*.size'=>'required|string|max:30','items.*.variant_id'=>'nullable|integer|min:1','items.*.quantity'=>'required|integer|min:1|max:10','code'=>'nullable|string|max:40'];}
 private function discount(?string $code,array $items,int $subtotal,?string $email=null,bool $lock=false,?int $customerId=null):array{
 if(!$code)return [0,null];$query=DB::table('promotions')->where('code',strtoupper(trim($code)));$p=($lock?$query->lockForUpdate():$query)->first();
 if(!$p||!$p->active||($p->expires_at&&now()->greaterThanOrEqualTo($p->expires_at))||($p->usage_limit!==null&&$p->used>=$p->usage_limit))throw ValidationException::withMessages(['code'=>'This code is unavailable or expired.']);
 if($subtotal<$p->minimum)throw ValidationException::withMessages(['code'=>'This code requires a minimum order of ₹'.$p->minimum.'.']);
 if($p->kind==='referral'&&$email){
 if(strtolower($email)===strtolower($p->owner_email??''))throw ValidationException::withMessages(['code'=>'You cannot use your own referral code.']);
 if(DB::table('orders')->where(function($q)use($email,$customerId){$q->whereRaw('lower(email) = ?', [strtolower($email)]);if($customerId)$q->orWhere('user_id',$customerId);})->exists())throw ValidationException::withMessages(['code'=>'Referral offers are for first orders only.']);
 }
 $targets=json_decode($p->targets??'[]',true);$eligible=0;
 foreach($items as $i){if($p->scope==='store'||($p->scope==='category'&&in_array($i['category'],$targets))||($p->scope==='product'&&in_array((string)$i['id'],$targets))){$eligible+=$i['price']*$i['quantity'];}}
 if(!$eligible)throw ValidationException::withMessages(['code'=>'This code does not apply to the products in your bag.']);
 $amount=$p->type==='percent'?(int)floor($eligible*$p->value/100):$p->value;
 if($p->maximum!==null)$amount=min($amount,$p->maximum);
 return [min($eligible,$amount),$p];
 }
 public function quote(Request $request){$data=$request->validate($this->rules());[$items,$subtotal]=$this->cart($data);[$discount,$p]=$this->discount($data['code']??null,$items,$subtotal);$s=\App\Support\Shop::settings();$shipping=$subtotal>=(int)$s['free_shipping_minimum']?0:(int)$s['shipping_fee'];return response()->json(['items'=>$items,'subtotal'=>$subtotal,'shipping'=>$shipping,'total'=>$subtotal+$shipping-$discount,'code'=>$p?->code,'discount'=>$discount,'note'=>$p?->kind==='referral'?'First-order eligibility is checked at checkout.':null]);}
 public function checkout(Request $request){
 $r=$request;
 if(!\Illuminate\Support\Facades\Auth::check()){$r->merge(['email'=>strtolower(trim($r->email??''))]);}
 $rules=array_merge($this->rules(),['name'=>'required|string|max:100','email'=>'required|email|max:200','phone'=>['required','regex:/^[6-9][0-9]{9}$/'],'address'=>'required|string|max:500','city'=>'required|string|max:100','pincode'=>['required','regex:/^[1-9][0-9]{5}$/'],'state'=>'nullable|string|max:100','checkout_key'=>'nullable|uuid']);
 if(!\Illuminate\Support\Facades\Auth::check()){$rules['email'].='|unique:users,email';$rules['password']=['required','confirmed',\Illuminate\Validation\Rules\Password::min(10)];}
 else{$r->merge(['email'=>\Illuminate\Support\Facades\Auth::user()->email]);}
 $data=$r->validate($rules);$newUser=null;
 if(!\App\Support\Shop::settings()['cod_enabled'])throw \Illuminate\Validation\ValidationException::withMessages(['payment'=>'Cash on delivery is currently unavailable.']);
 $result=DB::transaction(function()use($data,&$newUser){
 $user=\Illuminate\Support\Facades\Auth::user();
 if($user&&!empty($data['checkout_key'])){$existing=DB::table('orders')->where('checkout_key',$data['checkout_key'])->where('user_id',$user->id)->first();if($existing)return ['reference'=>$existing->reference,'total'=>$existing->total,'message'=>'Order already placed.','account_created'=>false];}
 [$items,$subtotal,$requested]=$this->cart($data,true);
 if(!$user){$newUser=$user=\App\Models\User::create(collect($data)->only(['name','email','password','phone','address','city','pincode'])->all());}
 [$discount,$promo]=$this->discount($data['code']??null,$items,$subtotal,$user->email,true,$user->id);
 foreach($requested as $line)\App\Support\Inventory::take($line);
 if($promo)DB::table('promotions')->where('id',$promo->id)->increment('used');
 $settings=\App\Support\Shop::settings();$shipping=$subtotal>=(int)$settings['free_shipping_minimum']?0:(int)$settings['shipping_fee'];$ref=(string)Str::uuid();$total=$subtotal+$shipping-$discount;
 $orderId=DB::table('orders')->insertGetId(collect($data)->only(['name','email','phone','address','city','pincode','state','checkout_key'])->merge(['user_id'=>$user->id,'reference'=>$ref,'items'=>json_encode($items),'subtotal'=>$subtotal,'shipping'=>$shipping,'discount'=>$discount,'code'=>$promo?->code,'total'=>$total,'status'=>'placed','payment_method'=>'cod','created_at'=>now(),'updated_at'=>now()])->all());
 DB::table('order_events')->insert(['order_id'=>$orderId,'status'=>'placed','note'=>'Order placed','created_at'=>now(),'updated_at'=>now()]);DB::table('carts')->where('user_id',$user->id)->delete();
 DB::afterCommit(function()use($ref,$newUser){if($newUser)\App\Support\StoreMail::welcome($newUser);\App\Support\StoreMail::order($ref);});
 return ['reference'=>$ref,'total'=>$total,'message'=>'Order placed. Pay on delivery.','account_created'=>(bool)$newUser];
 });
 if($newUser){\Illuminate\Support\Facades\Auth::login($newUser);$r->session()->regenerate();$r->session()->put('auth_version',$newUser->auth_version);}
 $result['csrf_token']=csrf_token();$result['account']=\Illuminate\Support\Facades\Auth::user()->only(['name','email','phone','address','city','pincode']);
 return response()->json($result,201);
 }
}
