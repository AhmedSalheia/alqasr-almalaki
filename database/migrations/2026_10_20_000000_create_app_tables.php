<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Local (on-device) copy of the reservations. Firestore is the shared source of truth;
        // this table lets the app work fully offline and is kept in sync by App\Services\SyncService.
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('groom_name');
            $table->date('date')->index();
            $table->string('start_time', 5);          // HH:MM (venue local time)
            $table->string('end_time', 5)->nullable(); // HH:MM
            $table->string('dj_name')->nullable();
            $table->string('phone', 40)->nullable();
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->string('updated_by_name')->nullable();
            $table->boolean('deleted')->default(false)->index();
            // pending = changed locally and not yet pushed to Firebase
            $table->string('sync_status', 10)->default('pending')->index();
            $table->string('remote_updated_at')->nullable(); // Firestore server timestamp (RFC3339)
            $table->timestamps();
        });

        // Small key/value store for device-local state (session tokens, device id, preferences, sync cursors).
        Schema::create('app_state', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_state');
        Schema::dropIfExists('events');
    }
};
