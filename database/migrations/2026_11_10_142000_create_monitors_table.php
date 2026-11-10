<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('monitors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->string('method')->default('GET');
            $table->unsignedInteger('check_interval_seconds')->default(60);
            $table->unsignedInteger('timeout_seconds')->default(10);
            $table->unsignedInteger('expected_status_code')->default(200);
            $table->unsignedInteger('consecutive_failure_threshold')->default(3);
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->string('status')->default('healthy')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('ssl_check_enabled')->default(true);
            $table->timestamp('ssl_expires_at')->nullable();
            $table->timestamp('last_checked_at')->nullable()->index();
            $table->timestamp('last_status_change_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['is_active', 'last_checked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitors');
    }
};
