<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_roles', function (Blueprint $table) {
            $table->id();

            // Example: SUPER_ADMIN, FINANCE_ADMIN
            $table->string('code', 60)->unique();

            $table->string('name', 120);

            $table->string('description', 500)->nullable();

            // System roles cannot normally be deleted from the UI.
            $table->boolean('is_system')->default(false);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_roles');
    }
};