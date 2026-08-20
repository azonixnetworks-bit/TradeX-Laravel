<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_permissions', function (Blueprint $table) {
            $table->id();

            // Example:
            // users.view
            // users.manage
            // trade_ideas.publish
            $table->string('code', 120)->unique();

            $table->string('name', 160);

            // Example: USERS, PAYMENTS, KYC
            $table->string('module', 100);

            $table->string('description', 500)->nullable();

            $table->timestamps();

            $table->index('module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_permissions');
    }
};