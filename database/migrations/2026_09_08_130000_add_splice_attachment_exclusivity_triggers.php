<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS fim_prevent_splice_attachment_conflict(); DROP FUNCTION IF EXISTS fim_prevent_attachment_splice_conflict();');

        DB::unprepared(<<<'SQL'
            CREATE FUNCTION fim_prevent_splice_attachment_conflict() RETURNS trigger AS $$
            DECLARE
                term_a record;
                term_b record;
                lock_id_a bigint;
                lock_id_b bigint;
            BEGIN
                IF NEW.deleted_at IS NOT NULL THEN RETURN NEW; END IF;

                SELECT id, deleted_at INTO term_a FROM fiber_terminations WHERE id = NEW.termination_a_id;
                SELECT id, deleted_at INTO term_b FROM fiber_terminations WHERE id = NEW.termination_b_id;

                IF term_a.deleted_at IS NOT NULL OR term_b.deleted_at IS NOT NULL THEN
                    RETURN NEW;
                END IF;

                lock_id_a := LEAST(NEW.termination_a_id, NEW.termination_b_id);
                lock_id_b := GREATEST(NEW.termination_a_id, NEW.termination_b_id);

                PERFORM 1 FROM fiber_terminations WHERE id = lock_id_a FOR UPDATE;
                PERFORM 1 FROM fiber_terminations WHERE id = lock_id_b FOR UPDATE;

                IF EXISTS (
                    SELECT 1 FROM fiber_termination_port_attachments
                    WHERE deleted_at IS NULL
                      AND fiber_termination_id IN (NEW.termination_a_id, NEW.termination_b_id)
                ) THEN
                    RAISE EXCEPTION 'fiber termination has a live port attachment' USING ERRCODE = '23505';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER fim_prevent_splice_attachment_conflict_trigger
                BEFORE INSERT OR UPDATE OF termination_a_id, termination_b_id, deleted_at
                ON physical_connections
                FOR EACH ROW EXECUTE FUNCTION fim_prevent_splice_attachment_conflict();

            CREATE FUNCTION fim_prevent_attachment_splice_conflict() RETURNS trigger AS $$
            DECLARE
                term record;
            BEGIN
                IF NEW.deleted_at IS NOT NULL THEN RETURN NEW; END IF;

                SELECT id, deleted_at INTO term FROM fiber_terminations WHERE id = NEW.fiber_termination_id;

                IF term.deleted_at IS NOT NULL THEN
                    RETURN NEW;
                END IF;

                PERFORM 1 FROM fiber_terminations WHERE id = NEW.fiber_termination_id FOR UPDATE;

                IF EXISTS (
                    SELECT 1 FROM physical_connections
                    WHERE deleted_at IS NULL
                      AND (termination_a_id = NEW.fiber_termination_id
                           OR termination_b_id = NEW.fiber_termination_id)
                ) THEN
                    RAISE EXCEPTION 'fiber termination has a live physical connection' USING ERRCODE = '23505';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER fim_prevent_attachment_splice_conflict_trigger
                BEFORE INSERT OR UPDATE OF fiber_termination_id, deleted_at
                ON fiber_termination_port_attachments
                FOR EACH ROW EXECUTE FUNCTION fim_prevent_attachment_splice_conflict();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS fim_prevent_splice_attachment_conflict_trigger ON physical_connections; DROP FUNCTION IF EXISTS fim_prevent_splice_attachment_conflict(); DROP TRIGGER IF EXISTS fim_prevent_attachment_splice_conflict_trigger ON fiber_termination_port_attachments; DROP FUNCTION IF EXISTS fim_prevent_attachment_splice_conflict();');
    }
};
