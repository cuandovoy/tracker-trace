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
            $table->decimal('tons', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('trips')->whereNull('tons')->update(['tons' => 0]);

        Schema::table('trips', function (Blueprint $table) {
            $table->decimal('tons', 10, 2)->nullable(false)->change();
        });
    }
};
