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
        Schema::table('invoices', fn (Blueprint $table) => $table->decimal('paid_amount', 15, 2)->default(0)->after('total_amount'));
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('payment_method_id')->nullable()->after('recorded_by_user_id')->constrained()->nullOnDelete();
            $table->foreignId('payment_plan_id')->nullable()->after('invoice_id')->constrained()->nullOnDelete();
        });
        Schema::create('payment_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('active')->index();
            $table->unsignedSmallInteger('installment_count');
            $table->decimal('planned_amount', 15, 2);
            $table->date('first_due_at');
            $table->timestamps();
        });
        Schema::create('payment_plan_installments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->decimal('amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->date('due_at')->index();
            $table->string('status')->default('pending')->index();
            $table->timestamps();
            $table->unique(['payment_plan_id', 'installment_number']);
        });
        Schema::create('payment_installment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_plan_installment_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            $table->unique(['payment_id', 'payment_plan_installment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_installment_allocations');
        Schema::dropIfExists('payment_plan_installments');
        Schema::dropIfExists('payment_plans');
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_plan_id');
            $table->dropConstrainedForeignId('payment_method_id');
        });
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('paid_amount'));
    }
};
