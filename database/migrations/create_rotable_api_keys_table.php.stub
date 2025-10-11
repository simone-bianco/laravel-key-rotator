<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection()
    {
        return config('laravel-key-rotator.database_connection', parent::getConnection());
    }

    public function up(): void
    {
        Schema::create('rotable_api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('service')->index();
            $table->text('key');

            $table->enum('base_limit_type', ['fixed', 'unlimited', 'none'])->default('none');
            $table->decimal('max_base_usage', 16, 8)->nullable();
            $table->decimal('current_base_usage', 16, 8)->default(0);

            $table->enum('free_limit_type', ['daily', 'monthly', 'none'])->default('none');
            $table->decimal('max_free_usage', 16, 8)->nullable();
            $table->decimal('current_free_usage', 16, 8)->default(0);

            $table->timestamp('free_usage_resets_at')->nullable()->index();
            $table->timestamp('last_free_usage_reset_at')->nullable()->index();
            $table->string('reset_timezone')->default('UTC');

            $table->json('extra_data')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_depleted')->default(false);
            $table->timestamp('depleted_at')->nullable();

            $table->timestamps();
            $table->timestamp('last_used_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rotable_api_keys');
    }
};
