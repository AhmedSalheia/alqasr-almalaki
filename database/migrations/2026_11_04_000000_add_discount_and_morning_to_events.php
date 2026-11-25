<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('events', 'discount')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            // A fixed amount (₪) taken off the total cost — not a percentage. Existing reservations get 0.
            $table->decimal('discount', 12, 2)->default(0)->after('total_cost');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('discount');
        });
    }
};
