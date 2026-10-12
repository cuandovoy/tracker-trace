<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('tax_id')->nullable();
            $t->string('address')->nullable();
            $t->string('phone')->nullable();
            $t->string('timezone')->default('America/Montevideo');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('role')->default('operator');
            $t->boolean('active')->default(true);
        });
        Schema::create('trucks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('plate', 32);
            $t->string('brand');
            $t->string('model');
            $t->unsignedSmallInteger('year');
            $t->decimal('capacity_tons', 10, 2);
            $t->decimal('odometer', 12, 1)->default(0);
            $t->string('fuel')->default('diesel');
            $t->boolean('maintenance')->default(false);
            $t->date('last_service')->nullable();
            $t->date('next_service')->nullable();
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'plate']);
            $t->unique(['company_id', 'id']);
            $t->index(['company_id', 'archived_at']);
        });
        Schema::create('drivers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('license');
            $t->string('category', 60);
            $t->string('phone', 60)->nullable();
            $t->date('hired_on');
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'id']);
            $t->index(['company_id', 'archived_at']);
        });
        Schema::create('assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('truck_id');
            $t->unsignedBigInteger('driver_id');
            $t->boolean('principal')->default(false);
            $t->timestamp('started_at');
            $t->timestamp('ended_at')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('ended_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->foreign(['company_id', 'truck_id'])->references(['company_id', 'id'])->on('trucks')->restrictOnDelete();
            $t->foreign(['company_id', 'driver_id'])->references(['company_id', 'id'])->on('drivers')->restrictOnDelete();
            $t->index(['company_id', 'driver_id', 'ended_at']);
            $t->index(['company_id', 'truck_id', 'ended_at']);
        });
        Schema::create('trips', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('truck_id');
            $t->unsignedBigInteger('driver_id');
            $t->date('date');
            $t->string('origin');
            $t->string('destination');
            $t->string('cargo');
            $t->decimal('tons', 10, 2);
            $t->decimal('planned_km', 12, 1);
            $t->decimal('actual_km', 12, 1)->nullable();
            $t->text('notes')->nullable();
            $t->string('status')->default('programado');
            $t->boolean('historical')->default(false);
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->text('cancellation_reason')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->foreign(['company_id', 'truck_id'])->references(['company_id', 'id'])->on('trucks')->restrictOnDelete();
            $t->foreign(['company_id', 'driver_id'])->references(['company_id', 'id'])->on('drivers')->restrictOnDelete();
            $t->index(['company_id', 'date', 'status']);
            $t->index(['company_id', 'truck_id', 'status']);
            $t->index(['company_id', 'driver_id', 'status']);
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->string('action');
            $t->json('data')->nullable();
            $t->timestamp('created_at');
            $t->index(['company_id', 'subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        foreach (['audit_events', 'trips', 'assignments', 'drivers', 'trucks'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('company_id');
            $t->dropColumn(['role', 'active']);
        });
        Schema::dropIfExists('companies');
    }
};
