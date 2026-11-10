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
        Schema::create('monitor_check_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->boolean('is_successful')->index();
            $table->text('error_message')->nullable();
            $table->boolean('ssl_is_valid')->nullable();
            $table->integer('ssl_days_remaining')->nullable();
            $table->timestamp('checked_at')->index();
            $table->timestamps();

            $table->index(['tenant_id', 'checked_at']);
            $table->index(['monitor_id', 'checked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitor_check_results');
    }
};
