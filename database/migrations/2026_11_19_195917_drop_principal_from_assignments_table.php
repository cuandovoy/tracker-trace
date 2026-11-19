<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $trucks = DB::table('assignments')->whereNull('ended_at')->groupBy('truck_id')->havingRaw('count(*) > 1')->pluck('truck_id');
        foreach ($trucks as $truckId) {
            $keep = DB::table('assignments')->where('truck_id', $truckId)->whereNull('ended_at')
                ->orderByDesc('principal')->orderByDesc('started_at')->orderByDesc('id')->value('id');
            DB::table('assignments')->where('truck_id', $truckId)->whereNull('ended_at')->where('id', '!=', $keep)
                ->update(['ended_at' => $now, 'ended_by' => null, 'updated_at' => $now]);
        }

        $drivers = DB::table('assignments')->whereNull('ended_at')->groupBy('driver_id')->havingRaw('count(*) > 1')->pluck('driver_id');
        foreach ($drivers as $driverId) {
            $keep = DB::table('assignments')->where('driver_id', $driverId)->whereNull('ended_at')
                ->orderByDesc('started_at')->orderByDesc('id')->value('id');
            DB::table('assignments')->where('driver_id', $driverId)->whereNull('ended_at')->where('id', '!=', $keep)
                ->update(['ended_at' => $now, 'ended_by' => null, 'updated_at' => $now]);
        }

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('principal');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->boolean('principal')->default(false);
        });
        DB::table('assignments')->whereNull('ended_at')->update(['principal' => true]);
    }
};
