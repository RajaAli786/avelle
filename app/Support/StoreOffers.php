<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
class StoreOffers {
 public static function activeQuery(){return DB::table('promotions')->where('active',true)->where(fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>',now()))->where(fn($q)=>$q->whereNull('usage_limit')->orWhereColumn('used','<','usage_limit'));}
 public static function visible():array{
 return self::activeQuery()->where('kind','coupon')->where('show_banner',true)->orderByDesc('id')->get()->map(function($p){
 $targets=json_decode($p->targets??'[]',true);$scope=$p->scope==='store'?'Storewide':($p->scope==='category'?implode(', ',$targets):DB::table('products')->whereIn('id',$targets)->pluck('name')->implode(', '));
 $saving=$p->type==='percent'?$p->value.'% OFF':'₹'.$p->value.' OFF';
 return ['id'=>$p->id,'code'=>$p->code,'title'=>$p->banner_text?:$saving.' on '.($p->scope==='store'?'your favourites':$scope),'saving'=>$saving,'scope'=>$p->scope,'scope_label'=>$scope,'targets'=>$targets,'minimum'=>(int)$p->minimum,'maximum'=>$p->maximum===null?null:(int)$p->maximum,'expires_at'=>$p->expires_at?Carbon::parse($p->expires_at)->toIso8601String():null,'mode'=>$p->banner_mode];
 })->all();
 }
}