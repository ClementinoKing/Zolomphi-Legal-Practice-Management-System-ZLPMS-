<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the core operational records for the legal practice.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone')->nullable();
            $table->string('department')->nullable();
            $table->string('status')->default('active')->index();
            $table->string('avatar_path')->nullable();
            $table->json('notification_settings')->nullable();
            $table->unsignedSmallInteger('session_timeout')->default(30);
            $table->timestamp('password_changed_at')->nullable();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->foreignId('role_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'user_id']);
        });

        Schema::create('general_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('firm_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('license_number')->nullable();
            $table->string('timezone')->default('Africa/Blantyre');
            $table->string('currency', 3)->default('MWK');
            $table->timestamps();
        });

        Schema::create('practice_areas', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->string('client_number')->unique();
            $table->string('type')->default('individual')->index();
            $table->string('full_name')->nullable()->index();
            $table->string('organization_name')->nullable()->index();
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable()->index();
            $table->string('alternate_phone')->nullable();
            $table->string('tax_number')->nullable()->index();
            $table->string('registration_number')->nullable()->index();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('status')->default('active')->index();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('matters', function (Blueprint $table): void {
            $table->id();
            $table->string('matter_number')->unique();
            $table->foreignId('client_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignId('opened_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('open')->index();
            $table->string('priority')->default('normal')->index();
            $table->string('billing_type')->default('hourly')->index();
            $table->decimal('default_hourly_rate', 15, 2)->nullable();
            $table->date('opened_at')->index();
            $table->date('closed_at')->nullable()->index();
            $table->text('closure_summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status']);
            $table->index(['responsible_user_id', 'status']);
        });

        Schema::create('matter_practice_area', function (Blueprint $table): void {
            $table->foreignId('matter_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('practice_area_id')->index()->constrained()->restrictOnDelete();

            $table->unique(['matter_id', 'practice_area_id']);
        });

        Schema::create('matter_parties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('matter_id')->index()->constrained()->cascadeOnDelete();
            $table->string('role')->index();
            $table->string('type')->default('individual')->index();
            $table->string('name');
            $table->string('organization_name')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('legal_representative')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['matter_id', 'role']);
        });

        Schema::create('court_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('matter_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('case_number')->nullable()->index();
            $table->string('court_name')->nullable()->index();
            $table->string('court_division')->nullable();
            $table->string('judge_name')->nullable();
            $table->string('case_status')->default('active')->index();
            $table->date('filed_at')->nullable()->index();
            $table->date('next_hearing_at')->nullable()->index();
            $table->text('relief_sought')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('matter_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('organizer_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('meeting')->index();
            $table->string('status')->default('scheduled')->index();
            $table->string('location')->nullable();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            $table->boolean('is_all_day')->default(false);
            $table->timestamps();

            $table->index(['organizer_user_id', 'starts_at']);
        });

        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('matter_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('priority')->default('normal')->index();
            $table->date('due_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['matter_id', 'status']);
        });

        Schema::create('task_user', function (Blueprint $table): void {
            $table->foreignId('task_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();

            $table->unique(['task_id', 'user_id']);
        });

        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('matter_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('category')->default('general')->index();
            $table->string('visibility')->default('internal')->index();
            $table->string('disk')->default('local');
            $table->string('path')->unique();
            $table->string('original_filename');
            $table->string('mime_type', 127)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['matter_id', 'category']);
            $table->index(['client_id', 'category']);
        });

        Schema::create('time_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('matter_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->date('entry_date')->index();
            $table->text('description');
            $table->decimal('hours', 8, 2);
            $table->decimal('hourly_rate', 15, 2)->nullable();
            $table->boolean('is_billable')->default(true)->index();
            $table->string('status')->default('draft')->index();
            $table->timestamps();

            $table->index(['matter_id', 'entry_date']);
            $table->index(['user_id', 'entry_date']);
            $table->index(['status', 'is_billable']);
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('client_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('matter_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->string('currency', 3)->default('MWK');
            $table->date('issued_at')->nullable()->index();
            $table->date('due_at')->nullable()->index();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['status', 'due_at']);
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('time_entry_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('amount', 15, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('payment_number')->unique();
            $table->string('method')->index();
            $table->string('currency', 3)->default('MWK');
            $table->decimal('amount', 15, 2);
            $table->date('received_at')->index();
            $table->string('reference')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('matter_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('expense_number')->unique();
            $table->string('category')->index();
            $table->string('vendor')->nullable()->index();
            $table->string('currency', 3)->default('MWK');
            $table->decimal('amount', 15, 2);
            $table->date('incurred_at')->index();
            $table->boolean('is_billable')->default(false)->index();
            $table->string('status')->default('recorded')->index();
            $table->text('description')->nullable();
            $table->string('receipt_path')->nullable();
            $table->timestamps();

            $table->index(['matter_id', 'incurred_at']);
            $table->index(['status', 'is_billable']);
        });

        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('matter_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->nullableMorphs('subject');
            $table->string('event')->index();
            $table->text('description')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['matter_id', 'created_at']);
            $table->index(['client_id', 'created_at']);
        });
    }

    /**
     * Remove legal practice tables in dependency order.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('task_user');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('court_cases');
        Schema::dropIfExists('matter_parties');
        Schema::dropIfExists('matter_practice_area');
        Schema::dropIfExists('matters');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('practice_areas');
        Schema::dropIfExists('general_settings');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn([
                'phone',
                'department',
                'status',
                'avatar_path',
                'notification_settings',
                'session_timeout',
                'password_changed_at',
            ]);
        });
    }
};
