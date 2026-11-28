<?php

declare(strict_types=1);

namespace SimoneBianco\LaravelKeyRotator\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CreateChatGptConnections extends Migration
{
    public function getConnection()
    {
        return config('laravel-key-rotator.database_connection') ?? parent::getConnection();
    }

    public function up(): void
    {
        Schema::create('rotable_chatgpt_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('client_id')->unique();
            $table->string('subject');
            $table->uuid('host_id');
            $table->string('status');
            $table->unsignedInteger('authorization_version')->default(1);
            $table->unsignedBigInteger('token_version')->default(1);
            $table->text('tokens')->nullable();
            $table->json('scopes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('retry_after')->nullable();
            $table->timestamp('refresh_retry_after')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedBigInteger('requests_started')->default(0);
            $table->unsignedBigInteger('requests_completed')->default(0);
            $table->unsignedBigInteger('requests_failed')->default(0);
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rotable_chatgpt_connections');
    }
}
