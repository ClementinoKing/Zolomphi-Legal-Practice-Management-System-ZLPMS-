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
        Schema::create('matter_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->string('visibility')->default('internal')->index();
            $table->timestamps();

            $table->index(['matter_id', 'created_at']);
        });

        Schema::create('matter_emails', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_email');
            $table->json('to_recipients');
            $table->json('cc_recipients')->nullable();
            $table->string('subject');
            $table->longText('body');
            $table->string('status')->default('queued')->index();
            $table->timestamp('sent_at')->nullable()->index();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['matter_id', 'created_at']);
        });

        Schema::create('matter_email_document', function (Blueprint $table): void {
            $table->foreignId('matter_email_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();

            $table->unique(['matter_email_id', 'document_id']);
        });

        Schema::create('matter_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('draft')->index();
            $table->date('charged_at')->index();
            $table->timestamps();

            $table->index(['matter_id', 'status']);
        });

        Schema::create('appointment_user', function (Blueprint $table): void {
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unique(['appointment_id', 'user_id']);
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->foreignId('expense_category_id')
                ->nullable()
                ->after('matter_id')
                ->constrained()
                ->nullOnDelete();
            $table->string('receipt_disk')->nullable()->after('receipt_path');
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->foreignId('matter_charge_id')
                ->nullable()
                ->unique()
                ->after('time_entry_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('matter_charge_id');
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('expense_category_id');
            $table->dropColumn('receipt_disk');
        });

        Schema::dropIfExists('appointment_user');
        Schema::dropIfExists('matter_charges');
        Schema::dropIfExists('matter_email_document');
        Schema::dropIfExists('matter_emails');
        Schema::dropIfExists('matter_notes');
    }
};
