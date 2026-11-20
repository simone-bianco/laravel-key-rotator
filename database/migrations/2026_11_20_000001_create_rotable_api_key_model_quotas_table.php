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
        Schema::create('rotable_api_key_model_quotas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rotable_api_key_id')
                ->constrained('rotable_api_keys')
                ->cascadeOnDelete();
            $table->string('model')->index();
            $table->enum('free_limit_type', ['daily', 'monthly', 'none'])->default('none');
            $table->decimal('max_free_usage', 16, 8)->nullable();
            $table->decimal('current_free_usage', 16, 8)->default(0);
            $table->timestamp('free_usage_resets_at')->nullable()->index();
            $table->timestamp('last_free_usage_reset_at')->nullable()->index();
            $table->string('reset_timezone')->default('UTC');
            $table->timestamps();

            $table->unique(['rotable_api_key_id', 'model'], 'rotable_key_model_quota_unique');
            $table->index(
                ['model', 'free_limit_type', 'free_usage_resets_at'],
                'rotable_key_model_quota_lookup_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rotable_api_key_model_quotas');
    }
};
