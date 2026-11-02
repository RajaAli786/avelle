<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
class ShoppingController{
 private function guard(){abort_unless(Auth::check(),401);}
 public function cart(){ $this->guard();return response()->json(['items'=>json_decode(DB::table('carts')->where('user_id',Auth::id())->value('items')??'[]',true)]);}
 public function saveCart(Request $r){$this->guard();$d=$r->validate(['items'=>'required|array|max:30','items.*.id'=>'required|integer|min:1','items.*.size'=>'required|string|max:30','items.*.variant_id'=>'nullable|integer|min:1','items.*.quantity'=>'required|integer|min:1|max:10']);
 $clean=[];foreach($d['items'] as $i){$key=$i['id'].'-'.($i['variant_id']??0).'-'.$i['size'];if(isset($clean[$key]))$clean[$key]['quantity']=min(10,$clean[$key]['quantity']+$i['quantity']);else$clean[$key]=$i;}
 DB::table('carts')->updateOrInsert(['user_id'=>Auth::id()],['items'=>json_encode(array_values($clean)),'updated_at'=>now(),'created_at'=>now()]);return response()->json(['items'=>array_values($clean)]);
 }
 public function wishlist(){ $this->guard();return response()->json(DB::table('wishlists')->where('user_id',Auth::id())->pluck('product_id'));}
 public function wish(Request $r){$this->guard();$d=$r->validate(['product_id'=>'required|integer|exists:products,id','saved'=>'required|boolean']);if($r->boolean('saved'))DB::table('wishlists')->updateOrInsert(['user_id'=>Auth::id(),'product_id'=>$d['product_id']],['created_at'=>now(),'updated_at'=>now()]);else DB::table('wishlists')->where('user_id',Auth::id())->where('product_id',$d['product_id'])->delete();return $this->wishlist();}
 public function address(Request $r){$this->guard();$d=$r->validate(['id'=>'nullable|integer','label'=>'required|string|max:30','name'=>'required|string|max:100','phone'=>['required','regex:/^[6-9][0-9]{9}$/'],'address'=>'required|string|max:500','city'=>'required|string|max:100','state'=>'required|string|max:100','pincode'=>['required','regex:/^[1-9][0-9]{5}$/'],'is_default'=>'nullable|boolean']);$id=$d['id']??null;unset($d['id']);if($id)abort_unless(DB::table('addresses')->where('id',$id)->where('user_id',Auth::id())->exists(),404);
 DB::transaction(function()use($r,$d,$id){$first=!DB::table('addresses')->where('user_id',Auth::id())->exists();$default=$first||$r->boolean('is_default');if($default)DB::table('addresses')->where('user_id',Auth::id())->update(['is_default'=>false]);$values=array_merge($d,['is_default'=>$default,'updated_at'=>now()]);if($id)DB::table('addresses')->where('id',$id)->where('user_id',Auth::id())->update($values);else DB::table('addresses')->insert($values+['user_id'=>Auth::id(),'created_at'=>now()]);});return back()->with('success','Address saved.');
 }
 public function deleteAddress(int $id){$this->guard();DB::transaction(function()use($id){$a=DB::table('addresses')->where('id',$id)->where('user_id',Auth::id())->lockForUpdate()->first();abort_unless($a,404);DB::table('addresses')->where('id',$id)->delete();if($a->is_default){$next=DB::table('addresses')->where('user_id',Auth::id())->first();if($next)DB::table('addresses')->where('id',$next->id)->update(['is_default'=>true]);}});return back()->with('success','Address removed.');}
 public function review(Request $r,int $id){$this->guard();$d=$r->validate(['rating'=>'required|integer|between:1,5','title'=>'required|string|max:120','body'=>'required|string|min:10|max:2000']);
 $verified=DB::table('orders')->where('user_id',Auth::id())->where('status','delivered')->get()->contains(fn($o)=>collect(json_decode($o->items,true))->contains(fn($i)=>(int)$i['id']===$id));if(!$verified)throw \Illuminate\Validation\ValidationException::withMessages(['review'=>'Only a customer with a delivered order for this product can review it.']);
 DB::table('reviews')->updateOrInsert(['user_id'=>Auth::id(),'product_id'=>$id],$d+['status'=>'pending','created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Your verified-purchase review has been submitted for moderation.');
 }
}