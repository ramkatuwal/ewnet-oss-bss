<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feasibility_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('feasibility_code', 64);
            $table->foreignId('lead_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('customer_address_id')->nullable()->constrained('customer_addresses')->nullOnDelete();
            $table->text('requested_service_summary');
            $table->text('requested_location_summary')->nullable();
            $table->decimal('requested_location_lat', 10, 7)->nullable();
            $table->decimal('requested_location_lng', 10, 7)->nullable();
            $table->string('status', 32)->default('requested');
            $table->string('outcome', 32)->nullable();
            $table->string('assessment_method', 32)->default('desk_review');
            $table->foreignId('assigned_assessor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('requested_at');
            $table->timestampTz('assessment_started_at')->nullable();
            $table->timestampTz('assessed_at')->nullable();
            $table->timestampTz('valid_until')->nullable();
            $table->text('conditions_summary')->nullable();
            $table->text('estimated_work_summary')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('customer_safe_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('feasibility_lifecycle_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feasibility_check_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('from_outcome', 32)->nullable();
            $table->string('to_outcome', 32)->nullable();
            $table->jsonb('context')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('feasibility_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feasibility_check_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('evidence_type', 32);
            $table->string('referenced_entity_type', 64)->nullable();
            $table->unsignedBigInteger('referenced_entity_id')->nullable();
            $table->text('observation_summary');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('recorded_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('feasibility_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feasibility_check_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('requested');
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->boolean('location_verified')->default(false);
            $table->decimal('coordinates_verified_lat', 10, 7)->nullable();
            $table->decimal('coordinates_verified_lng', 10, 7)->nullable();
            $table->text('nearest_infrastructure_notes')->nullable();
            $table->text('access_path_notes')->nullable();
            $table->boolean('civil_work_required')->default(false);
            $table->string('installation_complexity', 32)->default('unknown');
            $table->text('signal_observations')->nullable();
            $table->text('survey_notes')->nullable();
            $table->text('findings')->nullable();
            $table->string('recommended_outcome', 32)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('feasibility_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feasibility_check_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('condition_type', 32);
            $table->text('description');
            $table->boolean('is_mandatory')->default(true);
            $table->string('status', 32)->default('pending');
            $table->timestampTz('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('customer_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feasibility_check_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 32)->default('pending');
            $table->string('channel', 32);
            $table->string('reference_code', 48)->nullable();
            $table->text('presented_summary');
            $table->text('notes')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('declined_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE feasibility_checks ADD CONSTRAINT feasibility_checks_status_check CHECK (status IN ('requested', 'reviewing', 'survey_required', 'survey_scheduled', 'surveyed', 'feasible', 'conditionally_feasible', 'not_feasible', 'cancelled', 'expired'));
            ALTER TABLE feasibility_checks ADD CONSTRAINT feasibility_checks_outcome_check CHECK (outcome IS NULL OR outcome IN ('feasible', 'conditionally_feasible', 'not_feasible'));
            ALTER TABLE feasibility_checks ADD CONSTRAINT feasibility_checks_assessment_method_check CHECK (assessment_method IN ('desk_review', 'gis_review', 'network_review', 'field_survey', 'hybrid', 'other'));
            ALTER TABLE feasibility_checks ADD CONSTRAINT feasibility_checks_requested_lat_check CHECK (requested_location_lat IS NULL OR (requested_location_lat >= -90 AND requested_location_lat <= 90));
            ALTER TABLE feasibility_checks ADD CONSTRAINT feasibility_checks_requested_lng_check CHECK (requested_location_lng IS NULL OR (requested_location_lng >= -180 AND requested_location_lng <= 180));
            ALTER TABLE feasibility_evidence ADD CONSTRAINT feasibility_evidence_evidence_type_check CHECK (evidence_type IN ('site_observation', 'asset_observation', 'port_observation', 'fiber_observation', 'pon_observation', 'gis_analysis', 'network_analysis', 'provider_observation', 'manual_observation', 'other'));
            ALTER TABLE feasibility_evidence ADD CONSTRAINT feasibility_evidence_reference_complete_check CHECK ((referenced_entity_type IS NULL) = (referenced_entity_id IS NULL));
            ALTER TABLE feasibility_surveys ADD CONSTRAINT feasibility_surveys_status_check CHECK (status IN ('requested', 'scheduled', 'in_progress', 'completed', 'cancelled', 'no_access'));
            ALTER TABLE feasibility_surveys ADD CONSTRAINT feasibility_surveys_complexity_check CHECK (installation_complexity IN ('simple', 'moderate', 'complex', 'unknown'));
            ALTER TABLE feasibility_surveys ADD CONSTRAINT feasibility_surveys_recommended_outcome_check CHECK (recommended_outcome IS NULL OR recommended_outcome IN ('feasible', 'conditionally_feasible', 'not_feasible'));
            ALTER TABLE feasibility_surveys ADD CONSTRAINT feasibility_surveys_verified_lat_check CHECK (coordinates_verified_lat IS NULL OR (coordinates_verified_lat >= -90 AND coordinates_verified_lat <= 90));
            ALTER TABLE feasibility_surveys ADD CONSTRAINT feasibility_surveys_verified_lng_check CHECK (coordinates_verified_lng IS NULL OR (coordinates_verified_lng >= -180 AND coordinates_verified_lng <= 180));
            ALTER TABLE feasibility_conditions ADD CONSTRAINT feasibility_conditions_condition_type_check CHECK (condition_type IN ('fiber_construction', 'pole_permission', 'equipment_requirement', 'capacity_upgrade', 'additional_survey', 'commercial_approval', 'civil_work', 'permits', 'other'));
            ALTER TABLE feasibility_conditions ADD CONSTRAINT feasibility_conditions_status_check CHECK (status IN ('pending', 'in_progress', 'resolved', 'waived', 'not_applicable'));
            ALTER TABLE customer_confirmations ADD CONSTRAINT customer_confirmations_status_check CHECK (status IN ('pending', 'confirmed', 'declined', 'expired', 'cancelled'));
            ALTER TABLE customer_confirmations ADD CONSTRAINT customer_confirmations_channel_check CHECK (channel IN ('phone', 'office', 'email', 'portal', 'sales_agent', 'signed_document', 'other'));

            ALTER TABLE leads DROP CONSTRAINT leads_status_check;
            ALTER TABLE leads ALTER COLUMN status TYPE varchar(32);
            ALTER TABLE lead_lifecycle_history ALTER COLUMN from_status TYPE varchar(32);
            ALTER TABLE lead_lifecycle_history ALTER COLUMN to_status TYPE varchar(32);
            ALTER TABLE leads ADD CONSTRAINT leads_status_check CHECK (status IN ('new', 'qualified', 'converted', 'lost', 'feasibility_pending', 'feasible', 'not_feasible', 'confirmed'));

            CREATE UNIQUE INDEX feasibility_checks_live_company_code_unique ON feasibility_checks (company_id, feasibility_code) WHERE deleted_at IS NULL;
            CREATE UNIQUE INDEX feasibility_checks_live_lead_active_unique ON feasibility_checks (company_id, lead_id) WHERE lead_id IS NOT NULL AND deleted_at IS NULL AND status IN ('requested', 'reviewing', 'survey_required', 'survey_scheduled', 'surveyed');
            CREATE INDEX feasibility_checks_company_status_index ON feasibility_checks (company_id, status) WHERE deleted_at IS NULL;
            CREATE INDEX feasibility_checks_status_outcome_index ON feasibility_checks (status, outcome, company_id) WHERE deleted_at IS NULL;
            CREATE INDEX feasibility_checks_lead_index ON feasibility_checks (lead_id) WHERE deleted_at IS NULL;
            CREATE INDEX feasibility_checks_customer_index ON feasibility_checks (customer_id) WHERE deleted_at IS NULL;
            CREATE INDEX feasibility_checks_assessor_index ON feasibility_checks (assigned_assessor_user_id) WHERE deleted_at IS NULL;
            CREATE INDEX feasibility_evidence_check_index ON feasibility_evidence (feasibility_check_id);
            CREATE INDEX feasibility_evidence_entity_index ON feasibility_evidence (referenced_entity_type, referenced_entity_id);
            CREATE INDEX feasibility_surveys_status_index ON feasibility_surveys (status);
            CREATE INDEX feasibility_conditions_check_index ON feasibility_conditions (feasibility_check_id);
            CREATE INDEX feasibility_conditions_status_index ON feasibility_conditions (status);
            CREATE UNIQUE INDEX customer_confirmations_reference_code_unique ON customer_confirmations (reference_code) WHERE reference_code IS NOT NULL;
            CREATE INDEX customer_confirmations_company_status_index ON customer_confirmations (company_id, status);

            CREATE OR REPLACE FUNCTION bss003_protect_feasibility_history() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'BSS feasibility history cannot be hard deleted' USING ERRCODE = '23503';
                END IF;
                IF OLD.deleted_at IS NOT NULL AND NEW.deleted_at IS NULL THEN
                    RAISE EXCEPTION 'BSS feasibility history cannot be restored' USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END; $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION bss003_validate_feasibility_identity() RETURNS trigger AS $$
            BEGIN
                IF (OLD.company_id, OLD.feasibility_code) IS DISTINCT FROM (NEW.company_id, NEW.feasibility_code) THEN
                    RAISE EXCEPTION 'feasibility company and code are immutable' USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END; $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION bss003_validate_feasibility_scope() RETURNS trigger AS $$
            DECLARE ref_company bigint;
            BEGIN
                IF NEW.lead_id IS NOT NULL THEN
                    SELECT company_id INTO ref_company FROM leads WHERE id = NEW.lead_id AND deleted_at IS NULL;
                    IF ref_company IS NULL OR ref_company <> NEW.company_id THEN
                        RAISE EXCEPTION 'feasibility lead must belong to feasibility company' USING ERRCODE = '23503';
                    END IF;
                END IF;
                IF NEW.customer_id IS NOT NULL THEN
                    SELECT company_id INTO ref_company FROM customers WHERE id = NEW.customer_id AND deleted_at IS NULL;
                    IF ref_company IS NULL OR ref_company <> NEW.company_id THEN
                        RAISE EXCEPTION 'feasibility customer must belong to feasibility company' USING ERRCODE = '23503';
                    END IF;
                END IF;
                IF NEW.customer_address_id IS NOT NULL THEN
                    SELECT company_id INTO ref_company FROM customer_addresses WHERE id = NEW.customer_address_id AND deleted_at IS NULL;
                    IF ref_company IS NULL OR ref_company <> NEW.company_id THEN
                        RAISE EXCEPTION 'feasibility customer address must belong to feasibility company' USING ERRCODE = '23503';
                    END IF;
                END IF;
                RETURN NEW;
            END; $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION bss003_validate_feasibility_child() RETURNS trigger AS $$
            DECLARE feasibility_company bigint;
            BEGIN
                SELECT company_id INTO feasibility_company FROM feasibility_checks WHERE id = NEW.feasibility_check_id AND deleted_at IS NULL;
                IF feasibility_company IS NULL OR feasibility_company <> NEW.company_id THEN
                    RAISE EXCEPTION 'BSS feasibility child must use its feasibility company' USING ERRCODE = '23503';
                END IF;
                RETURN NEW;
            END; $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION bss003_validate_confirmation_scope() RETURNS trigger AS $$
            DECLARE feasibility_company bigint; ref_company bigint;
            BEGIN
                SELECT company_id INTO feasibility_company FROM feasibility_checks WHERE id = NEW.feasibility_check_id AND deleted_at IS NULL;
                IF feasibility_company IS NULL OR feasibility_company <> NEW.company_id THEN
                    RAISE EXCEPTION 'confirmation must use its feasibility company' USING ERRCODE = '23503';
                END IF;
                IF NEW.lead_id IS NOT NULL THEN
                    SELECT company_id INTO ref_company FROM leads WHERE id = NEW.lead_id AND deleted_at IS NULL;
                    IF ref_company IS NULL OR ref_company <> NEW.company_id THEN
                        RAISE EXCEPTION 'confirmation lead must belong to confirmation company' USING ERRCODE = '23503';
                    END IF;
                END IF;
                IF NEW.customer_id IS NOT NULL THEN
                    SELECT company_id INTO ref_company FROM customers WHERE id = NEW.customer_id AND deleted_at IS NULL;
                    IF ref_company IS NULL OR ref_company <> NEW.company_id THEN
                        RAISE EXCEPTION 'confirmation customer must belong to confirmation company' USING ERRCODE = '23503';
                    END IF;
                END IF;
                RETURN NEW;
            END; $$ LANGUAGE plpgsql;

            CREATE TRIGGER bss003_feasibility_history BEFORE UPDATE OR DELETE ON feasibility_checks FOR EACH ROW EXECUTE FUNCTION bss003_protect_feasibility_history();
            CREATE TRIGGER bss003_feasibility_identity BEFORE UPDATE ON feasibility_checks FOR EACH ROW EXECUTE FUNCTION bss003_validate_feasibility_identity();
            CREATE TRIGGER bss003_feasibility_scope BEFORE INSERT OR UPDATE ON feasibility_checks FOR EACH ROW EXECUTE FUNCTION bss003_validate_feasibility_scope();
            CREATE TRIGGER bss003_evidence_company BEFORE INSERT OR UPDATE ON feasibility_evidence FOR EACH ROW EXECUTE FUNCTION bss003_validate_feasibility_child();
            CREATE TRIGGER bss003_surveys_company BEFORE INSERT OR UPDATE ON feasibility_surveys FOR EACH ROW EXECUTE FUNCTION bss003_validate_feasibility_child();
            CREATE TRIGGER bss003_conditions_company BEFORE INSERT OR UPDATE ON feasibility_conditions FOR EACH ROW EXECUTE FUNCTION bss003_validate_feasibility_child();
            CREATE TRIGGER bss003_confirmation_scope BEFORE INSERT OR UPDATE ON customer_confirmations FOR EACH ROW EXECUTE FUNCTION bss003_validate_confirmation_scope();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS bss003_validate_confirmation_scope() CASCADE;
            DROP FUNCTION IF EXISTS bss003_validate_feasibility_child() CASCADE;
            DROP FUNCTION IF EXISTS bss003_validate_feasibility_scope() CASCADE;
            DROP FUNCTION IF EXISTS bss003_validate_feasibility_identity() CASCADE;
            DROP FUNCTION IF EXISTS bss003_protect_feasibility_history() CASCADE;');
        Schema::dropIfExists('customer_confirmations');
        Schema::dropIfExists('feasibility_conditions');
        Schema::dropIfExists('feasibility_surveys');
        Schema::dropIfExists('feasibility_evidence');
        Schema::dropIfExists('feasibility_lifecycle_history');
        Schema::dropIfExists('feasibility_checks');

        DB::unprepared(<<<'SQL'
            ALTER TABLE leads DROP CONSTRAINT leads_status_check;
            ALTER TABLE leads ALTER COLUMN status TYPE varchar(16);
            ALTER TABLE lead_lifecycle_history ALTER COLUMN from_status TYPE varchar(16);
            ALTER TABLE lead_lifecycle_history ALTER COLUMN to_status TYPE varchar(16);
            ALTER TABLE leads ADD CONSTRAINT leads_status_check CHECK (status IN ('new', 'qualified', 'converted', 'lost'));
            SQL);
    }
};
