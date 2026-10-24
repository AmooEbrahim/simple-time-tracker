<?php

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
        Schema::table('users', function (Blueprint $table) {
            $table->string('calendar', 20)->default('jalali');
            $table->unsignedTinyInteger('week_start')->default(6);
            $table->string('weekend_days', 50)->default('[4,5]');
            $table->boolean('show_holidays')->default(true);
            $table->string('holiday_country', 10)->default('IR');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'calendar',
                'week_start',
                'weekend_days',
                'show_holidays',
                'holiday_country',
            ]);
        });
    }
};
