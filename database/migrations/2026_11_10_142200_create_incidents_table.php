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
        Schema::create('incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('status')->default('investigating')->index();
            $table->string('severity')->default('major')->index();
            $table->text('description')->nullable();
            $table->text('root_cause_analysis')->nullable();
            $table->timestamp('started_at')->index();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['monitor_id', 'resolved_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
