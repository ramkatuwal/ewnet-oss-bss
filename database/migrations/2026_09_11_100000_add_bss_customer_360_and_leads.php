<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bss_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('bss_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('kind', 16); // tag and flag use the same controlled vocabulary.
            $table->string('name', 64);
            $table->string('color', 16)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('kind', 16);
            $table->string('value', 255);
            $table->boolean('is_primary')->default(false);
            $table->boolean('verified')->default(false);
            $table->timestampTz('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('customer_contact_persons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('role', 128)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 64)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('kind', 16)->default('billing');
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city', 128)->nullable();
            $table->string('state', 128)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('customer_business_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('legal_name')->nullable();
            $table->string('registration_number', 128)->nullable();
            $table->string('tax_number', 128)->nullable();
            $table->string('industry', 128)->nullable();
            $table->timestamps();
        });
        Schema::create('customer_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('kind', 64);
            $table->string('status', 16);
            $table->string('reference', 128)->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('verified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('customer_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('customer_tag_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('tag_id')->constrained('bss_tags')->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('bss_sources')->restrictOnDelete();
            $table->string('lead_code', 64);
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('status', 16)->default('new');
            $table->jsonb('qualification')->nullable();
            $table->foreignId('converted_customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->timestampTz('converted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('lead_lifecycle_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->jsonb('context')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE bss_tags ADD CONSTRAINT bss_tags_kind_check CHECK (kind IN ('tag', 'flag'));
            ALTER TABLE customer_contacts ADD CONSTRAINT customer_contacts_kind_check CHECK (kind IN ('email', 'phone', 'other'));
            ALTER TABLE customer_addresses ADD CONSTRAINT customer_addresses_kind_check CHECK (kind IN ('billing', 'service', 'other'));
            ALTER TABLE customer_verifications ADD CONSTRAINT customer_verifications_status_check CHECK (status IN ('pending', 'verified', 'rejected'));
            ALTER TABLE leads ADD CONSTRAINT leads_status_check CHECK (status IN ('new', 'qualified', 'converted', 'lost'));
            CREATE UNIQUE INDEX bss_sources_live_company_code_unique ON bss_sources (company_id, code) WHERE deleted_at IS NULL;
            CREATE UNIQUE INDEX bss_tags_live_company_kind_name_unique ON bss_tags (company_id, kind, name) WHERE deleted_at IS NULL;
            CREATE UNIQUE INDEX customer_contacts_live_unique ON customer_contacts (customer_id, kind, value) WHERE deleted_at IS NULL;
            CREATE UNIQUE INDEX customer_tag_assignments_unique ON customer_tag_assignments (customer_id, tag_id);
            CREATE UNIQUE INDEX leads_live_company_code_unique ON leads (company_id, lead_code) WHERE deleted_at IS NULL;
            CREATE INDEX leads_company_status_index ON leads (company_id, status) WHERE deleted_at IS NULL;
            CREATE INDEX customer_notes_customer_created_index ON customer_notes (customer_id, created_at DESC);
            CREATE OR REPLACE FUNCTION bss002_validate_customer_child() RETURNS trigger AS $$
            DECLARE customer_company bigint;
            BEGIN
                SELECT company_id INTO customer_company FROM customers WHERE id = NEW.customer_id AND deleted_at IS NULL;
                IF customer_company IS NULL OR customer_company <> NEW.company_id THEN
                    RAISE EXCEPTION 'BSS customer child must use its live customer company' USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END; $$ LANGUAGE plpgsql;
            CREATE OR REPLACE FUNCTION bss002_validate_lead() RETURNS trigger AS $$
            DECLARE source_company bigint; customer_company bigint;
            BEGIN
                IF NEW.source_id IS NOT NULL THEN SELECT company_id INTO source_company FROM bss_sources WHERE id = NEW.source_id AND deleted_at IS NULL; IF source_company IS NULL OR source_company <> NEW.company_id THEN RAISE EXCEPTION 'lead source must belong to lead company' USING ERRCODE = '23503'; END IF; END IF;
                IF NEW.converted_customer_id IS NOT NULL THEN SELECT company_id INTO customer_company FROM customers WHERE id = NEW.converted_customer_id; IF customer_company <> NEW.company_id THEN RAISE EXCEPTION 'converted customer must belong to lead company' USING ERRCODE = '23503'; END IF; END IF;
                RETURN NEW;
            END; $$ LANGUAGE plpgsql;
            CREATE OR REPLACE FUNCTION bss002_validate_customer_tag() RETURNS trigger AS $$
            DECLARE tag_company bigint;
            BEGIN
                SELECT company_id INTO tag_company FROM bss_tags WHERE id = NEW.tag_id AND deleted_at IS NULL;
                IF tag_company IS NULL OR tag_company <> NEW.company_id THEN RAISE EXCEPTION 'customer tag must use the customer company tag' USING ERRCODE = '23503'; END IF;
                RETURN NEW;
            END; $$ LANGUAGE plpgsql;
            CREATE TRIGGER bss002_contacts_company BEFORE INSERT OR UPDATE ON customer_contacts FOR EACH ROW EXECUTE FUNCTION bss002_validate_customer_child();
            CREATE TRIGGER bss002_people_company BEFORE INSERT OR UPDATE ON customer_contact_persons FOR EACH ROW EXECUTE FUNCTION bss002_validate_customer_child();
            CREATE TRIGGER bss002_addresses_company BEFORE INSERT OR UPDATE ON customer_addresses FOR EACH ROW EXECUTE FUNCTION bss002_validate_customer_child();
            CREATE TRIGGER bss002_profiles_company BEFORE INSERT OR UPDATE ON customer_business_profiles FOR EACH ROW EXECUTE FUNCTION bss002_validate_customer_child();
            CREATE TRIGGER bss002_verifications_company BEFORE INSERT OR UPDATE ON customer_verifications FOR EACH ROW EXECUTE FUNCTION bss002_validate_customer_child();
            CREATE TRIGGER bss002_notes_company BEFORE INSERT OR UPDATE ON customer_notes FOR EACH ROW EXECUTE FUNCTION bss002_validate_customer_child();
            CREATE TRIGGER bss002_assignments_company BEFORE INSERT OR UPDATE ON customer_tag_assignments FOR EACH ROW EXECUTE FUNCTION bss002_validate_customer_child();
            CREATE TRIGGER bss002_assignments_tag BEFORE INSERT OR UPDATE ON customer_tag_assignments FOR EACH ROW EXECUTE FUNCTION bss002_validate_customer_tag();
            CREATE TRIGGER bss002_leads_company BEFORE INSERT OR UPDATE ON leads FOR EACH ROW EXECUTE FUNCTION bss002_validate_lead();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS bss002_validate_customer_tag() CASCADE; DROP FUNCTION IF EXISTS bss002_validate_lead() CASCADE; DROP FUNCTION IF EXISTS bss002_validate_customer_child() CASCADE;');
        Schema::dropIfExists('lead_lifecycle_history');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('customer_tag_assignments');
        Schema::dropIfExists('customer_notes');
        Schema::dropIfExists('customer_verifications');
        Schema::dropIfExists('customer_business_profiles');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customer_contact_persons');
        Schema::dropIfExists('customer_contacts');
        Schema::dropIfExists('bss_tags');
        Schema::dropIfExists('bss_sources');
    }
};
