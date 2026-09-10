<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('customer_code');
            $table->string('name');
            $table->string('type');
            $table->string('status')->default('active');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('service_code');
            $table->string('name');
            $table->string('type');
            $table->string('status')->default('active');
            $table->text('description')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('terminated_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_type_check CHECK (type IN ('individual', 'organization'))");
        DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_status_check CHECK (status IN ('active', 'inactive', 'retired'))");
        DB::statement("ALTER TABLE services ADD CONSTRAINT services_type_check CHECK (type IN ('internet', 'voice', 'iptv', 'other'))");
        DB::statement("ALTER TABLE services ADD CONSTRAINT services_status_check CHECK (status IN ('active', 'inactive', 'retired'))");
        DB::statement("ALTER TABLE customer_services ADD CONSTRAINT customer_services_status_check CHECK (status IN ('pending', 'active', 'suspended', 'terminated', 'cancelled'))");
        DB::statement('CREATE UNIQUE INDEX customers_live_company_code_unique ON customers (company_id, customer_code) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX services_live_company_code_unique ON services (company_id, service_code) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX customers_company_status_index ON customers (company_id, status) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX services_company_status_index ON services (company_id, status) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX customer_services_customer_status_index ON customer_services (customer_id, status) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX customer_services_service_status_index ON customer_services (service_id, status) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX customer_services_company_status_index ON customer_services (company_id, status) WHERE deleted_at IS NULL');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION bss001_protect_identity_history() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'BSS history cannot be hard deleted' USING ERRCODE = '23503';
                END IF;
                IF OLD.deleted_at IS NOT NULL AND NEW.deleted_at IS NULL THEN
                    RAISE EXCEPTION 'BSS history cannot be restored; create a new identity' USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION bss001_validate_customer_identity() RETURNS trigger AS $$
            BEGIN
                IF (OLD.company_id, OLD.customer_code) IS DISTINCT FROM (NEW.company_id, NEW.customer_code) THEN
                    RAISE EXCEPTION 'customer company and code are immutable' USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION bss001_validate_service_identity() RETURNS trigger AS $$
            BEGIN
                IF (OLD.company_id, OLD.service_code) IS DISTINCT FROM (NEW.company_id, NEW.service_code) THEN
                    RAISE EXCEPTION 'service company and code are immutable' USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION bss001_validate_customer_service_identity() RETURNS trigger AS $$
            BEGIN
                IF (OLD.company_id, OLD.customer_id, OLD.service_id) IS DISTINCT FROM (NEW.company_id, NEW.customer_id, NEW.service_id) THEN
                    RAISE EXCEPTION 'customer service identity is immutable' USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION bss001_validate_customer_service() RETURNS trigger AS $$
            DECLARE customer_company bigint; service_company bigint;
            BEGIN
                SELECT company_id INTO customer_company FROM customers WHERE id = NEW.customer_id AND deleted_at IS NULL;
                SELECT company_id INTO service_company FROM services WHERE id = NEW.service_id AND deleted_at IS NULL;
                IF customer_company IS NULL OR service_company IS NULL OR NEW.company_id <> customer_company OR NEW.company_id <> service_company THEN
                    RAISE EXCEPTION 'customer service must belong to the same live company as its customer and service' USING ERRCODE = '23503';
                END IF;
                IF TG_OP = 'UPDATE' AND OLD.status IS DISTINCT FROM NEW.status AND NOT (
                    (OLD.status = 'pending' AND NEW.status IN ('active', 'cancelled')) OR
                    (OLD.status = 'active' AND NEW.status IN ('suspended', 'terminated')) OR
                    (OLD.status = 'suspended' AND NEW.status IN ('active', 'terminated'))
                ) THEN
                    RAISE EXCEPTION 'invalid customer service status transition' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER bss001_customers_history BEFORE UPDATE OR DELETE ON customers FOR EACH ROW EXECUTE FUNCTION bss001_protect_identity_history();
            CREATE TRIGGER bss001_services_history BEFORE UPDATE OR DELETE ON services FOR EACH ROW EXECUTE FUNCTION bss001_protect_identity_history();
            CREATE TRIGGER bss001_customer_services_history BEFORE UPDATE OR DELETE ON customer_services FOR EACH ROW EXECUTE FUNCTION bss001_protect_identity_history();
            CREATE TRIGGER bss001_customers_identity BEFORE UPDATE ON customers FOR EACH ROW EXECUTE FUNCTION bss001_validate_customer_identity();
            CREATE TRIGGER bss001_services_identity BEFORE UPDATE ON services FOR EACH ROW EXECUTE FUNCTION bss001_validate_service_identity();
            CREATE TRIGGER bss001_customer_services_identity BEFORE UPDATE ON customer_services FOR EACH ROW EXECUTE FUNCTION bss001_validate_customer_service_identity();
            CREATE TRIGGER bss001_customer_services_validate BEFORE INSERT OR UPDATE ON customer_services FOR EACH ROW EXECUTE FUNCTION bss001_validate_customer_service();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS bss001_customer_services_validate ON customer_services;
            DROP TRIGGER IF EXISTS bss001_customer_services_identity ON customer_services;
            DROP TRIGGER IF EXISTS bss001_services_identity ON services;
            DROP TRIGGER IF EXISTS bss001_customers_identity ON customers;
            DROP TRIGGER IF EXISTS bss001_customer_services_history ON customer_services;
            DROP TRIGGER IF EXISTS bss001_services_history ON services;
            DROP TRIGGER IF EXISTS bss001_customers_history ON customers;
            DROP FUNCTION IF EXISTS bss001_validate_customer_service();
            DROP FUNCTION IF EXISTS bss001_validate_customer_service_identity();
            DROP FUNCTION IF EXISTS bss001_validate_service_identity();
            DROP FUNCTION IF EXISTS bss001_validate_customer_identity();
            DROP FUNCTION IF EXISTS bss001_protect_identity_history();
            SQL);
        Schema::dropIfExists('customer_services');
        Schema::dropIfExists('services');
        Schema::dropIfExists('customers');
    }
};
