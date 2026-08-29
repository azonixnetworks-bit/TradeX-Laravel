<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('prd_uuid_uq');
            $table->string('code', 50)->unique('prd_code_uq');
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('billing_type', 40);
            $table->string('currency', 10);
            $table->unsignedBigInteger('amount_minor');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();
            $table->foreignId('updated_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['is_active', 'code'],
                'prd_active_code_idx'
            );
        });

        DB::table('products')->insert([
            'uuid' => (string) Str::uuid(),
            'code' => 'EDUCATION_PREMIUM',
            'name' => 'TradeX Education Premium',
            'description' =>
                'One-time lifetime premium education access.',
            'billing_type' => 'ONE_TIME_LIFETIME',
            'currency' => 'USD',
            'amount_minor' => 7900,
            'is_active' => true,
            'created_by_admin_id' => null,
            'updated_by_admin_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
