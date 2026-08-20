<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_user_roles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('admin_user_id')
                ->constrained('admin_users')
                ->cascadeOnDelete();

            $table->foreignId('admin_role_id')
                ->constrained('admin_roles')
                ->cascadeOnDelete();

            $table->timestamps();

            // Same role twice assign aaga koodathu.
            $table->unique([
                'admin_user_id',
                'admin_role_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_user_roles');
    }
};