<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->decimal('value_amount', 12, 2)->nullable()->after('planned_km');
            $table->boolean('collected')->default(false)->after('value_amount');
            $table->unsignedSmallInteger('viaticos_qty')->default(0)->after('collected');
            $table->decimal('commission_rate', 5, 2)->nullable()->after('viaticos_qty');
            $table->decimal('viatico_unit_amount', 12, 2)->nullable()->after('commission_rate');
        });

        DB::table('trips')->update([
            'commission_rate' => config('fleet.commission_rate'),
            'viatico_unit_amount' => config('fleet.viatico_unit_amount'),
        ]);

        Schema::table('trips', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->nullable(false)->change();
            $table->decimal('viatico_unit_amount', 12, 2)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['value_amount', 'collected', 'viaticos_qty', 'commission_rate', 'viatico_unit_amount']);
        });
    }
};
