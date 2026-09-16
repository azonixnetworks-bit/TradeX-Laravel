<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funded_account_partners', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 160);
            $table->string('website_url', 2048)->nullable();
            $table->string('affiliate_url', 2048)->nullable();
            $table->string('promo_code', 100)->nullable();
            $table->string('offer_text', 255)->nullable();
            $table->text('disclosure')->nullable();
            $table->json('supported_countries')->nullable();
            $table->json('account_sizes')->nullable();
            $table->json('challenge_types')->nullable();
            $table->string('logo_disk', 40)->nullable();
            $table->string('logo_path', 500)->nullable();
            $table->string('logo_mime_type', 100)->nullable();
            $table->unsignedBigInteger('logo_size_bytes')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->foreignId('created_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();
            $table->foreignId('updated_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'is_featured', 'priority']);
        });

        Schema::create('funded_advisor_reports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('customer_name', 160);
            $table->string('customer_email', 255)->nullable();
            $table->string('prop_firm_name', 160)->nullable();
            $table->foreignId('partner_id')
                ->nullable()
                ->constrained('funded_account_partners')
                ->nullOnDelete();
            $table->string('partner_name_snapshot', 160)->nullable();
            $table->string('affiliate_url_snapshot', 2048)->nullable();
            $table->string('promo_code_snapshot', 100)->nullable();
            $table->string('partner_offer_snapshot', 255)->nullable();
            $table->text('affiliate_disclosure_snapshot')->nullable();
            $table->json('inputs_json');
            $table->json('output_json');
            $table->foreignId('generated_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->index(['generated_by_admin_id', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funded_advisor_reports');
        Schema::dropIfExists('funded_account_partners');
    }
};
