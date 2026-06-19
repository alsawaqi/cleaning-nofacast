<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category')->default('cleaning');
            $table->text('description')->nullable();
            $table->string('pricing_type')->default('fixed');
            $table->unsignedInteger('base_price_halalas')->default(0);
            $table->unsignedTinyInteger('vat_rate')->default(15);
            $table->boolean('prices_include_vat')->default(false);
            $table->boolean('materials_included')->default(true);
            $table->json('allowed_frequencies')->nullable();
            $table->json('required_certificates')->nullable();
            $table->json('checklist_template')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('customer_type')->default('company');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('preferred_channel')->default('whatsapp');
            $table->string('preferred_locale', 8)->default('ar');
            $table->string('vat_number')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('customer_sites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('city')->default('Riyadh');
            $table->string('district')->nullable();
            $table->text('address')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->timestamps();
        });

        Schema::create('workers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_code')->unique();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('role_language', 8)->default('ar');
            $table->string('status')->default('available');
            $table->unsignedInteger('cost_rate_halalas')->default(0);
            $table->json('skills')->nullable();
            $table->json('certifications')->nullable();
            $table->timestamps();
        });

        Schema::create('worker_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->string('document_number')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();
        });

        Schema::create('training_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->string('course_name');
            $table->string('certificate_code')->nullable();
            $table->date('completed_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->timestamps();
        });

        Schema::create('contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_site_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('status')->default('draft');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('monthly_fee_halalas')->default(0);
            $table->unsignedTinyInteger('vat_rate')->default(15);
            $table->boolean('prices_include_vat')->default(false);
            $table->json('payment_plan')->nullable();
            $table->unsignedSmallInteger('notice_days')->default(30);
            $table->boolean('auto_renews')->default(true);
            $table->text('special_terms')->nullable();
            $table->timestamps();
        });

        Schema::create('contract_addendums', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('title');
            $table->text('summary');
            $table->date('effective_on');
            $table->timestamps();
        });

        Schema::create('assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedTinyInteger('share_percent')->default(100);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['worker_id', 'weekday', 'status']);
        });

        Schema::create('visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->date('scheduled_for');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('status')->default('scheduled');
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->text('issue_note')->nullable();
            $table->json('photos')->nullable();
            $table->timestamps();
            $table->unique(['assignment_id', 'scheduled_for']);
        });

        Schema::create('checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->boolean('is_required')->default(true);
            $table->string('status')->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number')->unique();
            $table->string('status')->default('draft');
            $table->date('issue_date');
            $table->date('due_date');
            $table->unsignedInteger('net_total_halalas')->default(0);
            $table->unsignedInteger('vat_total_halalas')->default(0);
            $table->unsignedInteger('gross_total_halalas')->default(0);
            $table->unsignedInteger('paid_total_halalas')->default(0);
            $table->unsignedTinyInteger('vat_rate')->default(15);
            $table->text('zatca_qr')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount_halalas');
            $table->string('method');
            $table->string('reference')->nullable();
            $table->timestamp('received_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('cheques', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cheque_number');
            $table->unsignedInteger('amount_halalas');
            $table->date('due_date');
            $table->date('cleared_date')->nullable();
            $table->string('status')->default('held');
            $table->timestamps();
        });

        Schema::create('credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('number')->unique();
            $table->unsignedInteger('amount_halalas');
            $table->string('status')->default('pending_approval');
            $table->text('reason');
            $table->timestamps();
        });

        Schema::create('worker_revenue_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('target_halalas');
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('service_interest')->nullable();
            $table->string('city')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('new');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('changes')->nullable();
            $table->timestamps();
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        collect([
            'audit_logs',
            'leads',
            'worker_revenue_targets',
            'credit_notes',
            'cheques',
            'payments',
            'invoices',
            'checklist_items',
            'visits',
            'assignments',
            'contract_addendums',
            'contracts',
            'training_records',
            'worker_documents',
            'workers',
            'customer_sites',
            'customers',
            'services',
        ])->each(fn (string $table) => Schema::dropIfExists($table));
    }
};
