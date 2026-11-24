<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('wallet_spent')->default(0);
        });
        Schema::create('wallet_accounts', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->bigInteger('balance')->default(0);
            $table->string('referral_code', 30)->unique();
            $table->timestamps();
        });
        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('referrer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->string('code', 40);
            $table->unsignedInteger('eligible_amount');
            $table->string('reward_type', 12);
            $table->unsignedInteger('reward_value');
            $table->unsignedInteger('amount');
            $table->string('status', 16)->default('pending')->index();
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();
        });
        Schema::create('wallet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entry_key', 120)->unique();
            $table->string('kind', 24);
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->string('note', 255);
            $table->timestamps();
        });
        Schema::create('store_mail_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('recipient');
            $table->string('subject');
            $table->longText('payload')->nullable();
            $table->string('status', 16)->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('locked_at')->nullable();
            $table->string('lock_token', 64)->nullable();
            $table->string('error_type', 200)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_mail_deliveries');
        Schema::dropIfExists('wallet_entries');
        Schema::dropIfExists('referral_rewards');
        Schema::dropIfExists('wallet_accounts');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('wallet_spent'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('email_verified_at'));
    }
};
