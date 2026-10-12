<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('name', 180);
            $t->string('tax_id', 60)->nullable();
            $t->string('contact_name', 180)->nullable();
            $t->string('email')->nullable();
            $t->string('phone', 60)->nullable();
            $t->string('address')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'id']);
            $t->index(['company_id', 'name']);
        });
        Schema::create('locations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->string('name', 180);
            $t->string('address', 180);
            $t->string('contact_name', 180)->nullable();
            $t->string('phone', 60)->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'id']);
            $t->index(['company_id', 'name']);
            $t->foreign(['company_id', 'customer_id'])->references(['company_id', 'id'])->on('customers')->restrictOnDelete();
        });
        Schema::table('trips', function (Blueprint $t) {
            $t->unique(['company_id', 'id']);
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->string('customer_name', 180)->nullable();
            $t->unsignedBigInteger('origin_location_id')->nullable();
            $t->unsignedBigInteger('destination_location_id')->nullable();
            $t->foreign(['company_id', 'customer_id'])->references(['company_id', 'id'])->on('customers')->restrictOnDelete();
            foreach (['origin_location_id', 'destination_location_id'] as $column) {
                $t->foreign(['company_id', $column])->references(['company_id', 'id'])->on('locations')->restrictOnDelete();
            }
        });
        Schema::create('trip_expenses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('trip_id');
            $t->date('date');
            $t->string('category', 30);
            $t->string('description', 250);
            $t->decimal('amount', 12, 2);
            $t->char('currency', 3);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->foreign(['company_id', 'trip_id'])->references(['company_id', 'id'])->on('trips')->restrictOnDelete();
            $t->index(['company_id', 'trip_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_expenses');
        Schema::table('trips', function (Blueprint $t) {
            foreach (['customer_id', 'origin_location_id', 'destination_location_id'] as $column) {
                $t->dropForeign(['company_id', $column]);
            }
            $t->dropColumn(['customer_id', 'customer_name', 'origin_location_id', 'destination_location_id']);
            $t->dropUnique(['company_id', 'id']);
        });
        Schema::dropIfExists('locations');
        Schema::dropIfExists('customers');
    }
};
