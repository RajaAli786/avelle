<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{
 Schema::create('promotions',function(Blueprint $t){$t->id();$t->string('code')->unique();$t->string('kind')->default('coupon');$t->string('owner_email')->nullable();$t->string('type')->default('percent');$t->unsignedInteger('value');$t->unsignedInteger('minimum')->default(0);$t->unsignedInteger('maximum')->nullable();$t->string('scope')->default('store');$t->json('targets')->nullable();$t->unsignedInteger('usage_limit')->nullable();$t->unsignedInteger('used')->default(0);$t->timestamp('expires_at')->nullable();$t->boolean('active')->default(true);$t->timestamps();});
 Schema::table('orders',function(Blueprint $t){$t->unsignedInteger('discount')->default(0);$t->string('code')->nullable();});
 }
 public function down():void{Schema::table('orders',function(Blueprint $t){$t->dropColumn(['discount','code']);});Schema::dropIfExists('promotions');}
};