<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite cannot add a NOT NULL column without a default, so add it nullable first.
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('brand_color')->default('#4f46e5');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['name', 'logo_path', 'brand_color']);
        });
    }
};
