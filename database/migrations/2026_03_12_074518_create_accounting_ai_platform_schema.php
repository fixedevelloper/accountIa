<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // =====================================================
        // USERS & AUTH
        // =====================================================

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('email_verification_token')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // =====================================================
        // COMPANIES (MULTI TENANT CORE)
        // =====================================================

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('country')->nullable();
            $table->string('currency', 10)->default('XAF');
            $table->timestamps();
            $table->softDeletes();

            $table->index('country');
        });

        Schema::create('company_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
        });

        // =====================================================
        // ACCOUNTING CORE
        // =====================================================

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->string('type')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index('company_id');
        });

        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('code', 20);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->nullable();
            $table->date('entry_date');
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->index(['company_id', 'entry_date']);
            $table->index('status');
        });

        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index('account_id');
        });

        // =====================================================
        // PARTNERS (CLIENTS / FOURNISSEURS)
        // =====================================================

        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('tax_number')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'type']);
        });

        // =====================================================
        // DOCUMENT MANAGEMENT
        // =====================================================

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->string('type')->nullable();
            $table->text('file_path');
            $table->string('status')->default('uploaded');
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->text('file_path');
            $table->timestamps();
        });

        // =====================================================
        // AI EXTRACTION
        // =====================================================

        Schema::create('document_extractions', function (Blueprint $table) {
            $table->id();

            // 🔗 Relations
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();

            // 📄 Infos document
            $table->string('type_document')->nullable(); // facture, reçu, etc.
            $table->string('supplier_name')->nullable();
            $table->string('client_name')->nullable();

            // 🔢 Facture
            $table->string('category')->nullable(); // telecom, restaurant...
            $table->string('invoice_number')->nullable()->index();
            $table->date('invoice_date')->nullable()->index();
            $table->date('due_date')->nullable();
            $table->string('payment_status')->default('unpaid');

            // 💰 Montants (TRÈS IMPORTANT)
            $table->decimal('amount_ht', 15, 2)->nullable();
            $table->decimal('vat_amount', 15, 2)->nullable();
            $table->decimal('total_amount', 15, 2)->nullable();

            // 🌍 Devise
            $table->string('currency', 10)->default('XAF')->index();

            // 🤖 OCR
            $table->float('confidence')->nullable(); // score IA
            $table->string('status')->default('extracted'); // extracted, validated, corrected

            // 🧾 Données brutes
            $table->json('raw_json')->nullable();

            // 🧠 Versioning
            $table->boolean('is_validated')->default(false);

            $table->timestamps();

            // ⚡ Index performance
            $table->index(['document_id', 'invoice_number']);
            $table->index(['supplier_name']);
        });

        Schema::create('ai_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('predicted_account')->nullable();
            $table->float('confidence')->nullable();
            $table->timestamps();
        });

        // =====================================================
        // BANKING
        // =====================================================

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name')->nullable();
            $table->string('iban')->nullable();
            $table->timestamps();
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->date('transaction_date');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            $table->index('transaction_date');
        });

        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained('bank_transactions')->cascadeOnDelete();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->timestamps();
        });

        // =====================================================
        // TAXES
        // =====================================================

        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('rate', 5, 2);
            $table->timestamps();
            $table->index('company_id');
        });

        Schema::create('tax_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_line_id')->constrained('journal_entry_lines')->cascadeOnDelete();
            $table->foreignId('tax_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });

        // =====================================================
        // REPORTING
        // =====================================================

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->timestamp('generated_at')->nullable();

            $table->index(['company_id', 'type']);
        });

        Schema::create('report_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->text('file_path');
        });


        // =====================================================
        // AUDIT LOGS
        // =====================================================

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
        });
        // =====================================================
        // INVOICING (CLIENT / FOURNISSEUR)
        // =====================================================

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('invoice_number');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('total',15,2);
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->index(['company_id','invoice_date']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity',10,2);
            $table->decimal('unit_price',15,2);
            $table->decimal('total',15,2);
        });

        // =====================================================
        // VAT DECLARATIONS
        // =====================================================

        Schema::create('vat_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('vat_collected',15,2)->default(0);
            $table->decimal('vat_deductible',15,2)->default(0);
            $table->decimal('vat_payable',15,2)->default(0);
            $table->timestamps();

            $table->index(['company_id','period_start']);
        });

        // =====================================================
        // AI TRAINING DATA
        // =====================================================

        Schema::create('ai_training_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('expected_account')->nullable();
            $table->boolean('validated')->default(false);
            $table->timestamps();
        });

        Schema::create('ai_training_logs', function (Blueprint $table) {
            $table->id();
            $table->string('model_name');
            $table->integer('documents_used');
            $table->timestamp('trained_at');
        });

        // =====================================================
        // ACCOUNTING REPORTS STRUCTURE
        // =====================================================

        Schema::create('trial_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('generated_for');
            $table->timestamps();
        });

        Schema::create('general_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamps();
        });

        Schema::create('income_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamps();
        });

        Schema::create('balance_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('generated_at');
            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('report_files');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('tax_lines');
        Schema::dropIfExists('taxes');
        Schema::dropIfExists('bank_reconciliations');
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('ai_classifications');
        Schema::dropIfExists('document_extractions');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('partners');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('journals');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('company_users');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('users');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('balance_sheets');
        Schema::dropIfExists('income_statements');
        Schema::dropIfExists('general_ledgers');
        Schema::dropIfExists('trial_balances');
        Schema::dropIfExists('ai_training_logs');
        Schema::dropIfExists('ai_training_documents');
        Schema::dropIfExists('vat_declarations');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
