<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerConfirmation;
use App\Models\FeasibilityCheck;
use App\Models\FeasibilityCondition;
use App\Models\FeasibilityEvidence;
use App\Models\FeasibilitySurvey;
use App\Models\FiberCable;
use App\Models\FiberSegment;
use App\Models\FiberTermination;
use App\Models\Lead;
use App\Models\NetworkConnectionPoint;
use App\Models\NetworkPort;
use App\Models\PassiveOpticalPort;
use App\Models\PonDomain;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * BSS-003 feasibility, survey, evidence, and customer confirmation workflows.
 *
 * Feasibility is purely advisory: it NEVER allocates, locks, or decrements any
 * infrastructure capacity. FIM/Network entities are consumed read-only through
 * evidence references.
 */
class FeasibilityService
{
    private const TRANSITIONS = [
        'requested' => ['reviewing', 'survey_required', 'cancelled', 'expired'],
        'reviewing' => ['survey_required', 'survey_scheduled', 'surveyed', 'feasible', 'conditionally_feasible', 'not_feasible', 'cancelled', 'expired'],
        'survey_required' => ['survey_scheduled', 'surveyed', 'feasible', 'conditionally_feasible', 'not_feasible', 'cancelled', 'expired'],
        'survey_scheduled' => ['surveyed', 'feasible', 'conditionally_feasible', 'not_feasible', 'cancelled', 'expired'],
        'surveyed' => ['feasible', 'conditionally_feasible', 'not_feasible', 'cancelled', 'expired'],
        'feasible' => [],
        'conditionally_feasible' => [],
        'not_feasible' => [],
        'cancelled' => [],
        'expired' => [],
    ];

    public function createFromLead(Lead $lead, array $data, User $actor): FeasibilityCheck
    {
        return DB::transaction(function () use ($lead, $data, $actor) {
            if (! ManagementScopeService::isInScope($actor, $lead)) {
                throw ValidationException::withMessages(['lead_id' => 'You do not have access to this lead.']);
            }
            $lead = Lead::lockForUpdate()->findOrFail($lead->id);
            if ($lead->status !== 'qualified') {
                throw ValidationException::withMessages(['lead_id' => 'Only qualified leads can be submitted for feasibility.']);
            }
            $company = Company::lockForUpdate()->findOrFail($lead->company_id);
            $this->assertNoActiveFeasibilityForLead($lead);
            $feasibility = $this->createRecord($company, [...$data, 'lead_id' => $lead->id, 'customer_id' => $lead->converted_customer_id], $actor);
            $this->syncLead($lead, 'feasibility_pending', $actor, ['feasibility_id' => $feasibility->id, 'feasibility_code' => $feasibility->feasibility_code, 'reason' => 'feasibility requested']);

            return $feasibility;
        });
    }

    public function createDirect(array $data, User $actor): FeasibilityCheck
    {
        return DB::transaction(function () use ($data, $actor) {
            $company = Company::lockForUpdate()->findOrFail($data['company_id']);
            if (! ManagementScopeService::isInScope($actor, $company)) {
                throw ValidationException::withMessages(['company_id' => 'You do not have access to this company.']);
            }
            $customer = Customer::lockForUpdate()->findOrFail($data['customer_id']);
            if ($customer->company_id !== $company->id || $customer->status === 'retired') {
                throw ValidationException::withMessages(['customer_id' => 'Customer must be live and belong to the same company.']);
            }

            return $this->createRecord($company, $data, $actor);
        });
    }

    public function updateAssessment(FeasibilityCheck $feasibility, array $data, User $actor): FeasibilityCheck
    {
        $this->assertScope($actor, $feasibility);

        return DB::transaction(function () use ($feasibility, $data, $actor) {
            $record = FeasibilityCheck::lockForUpdate()->findOrFail($feasibility->id);
            if ($record->isTerminal()) {
                throw ValidationException::withMessages(['status' => 'Assessment fields of a finalized feasibility cannot be edited.']);
            }
            $record->update([
                ...Arr::only($data, ['requested_service_summary', 'requested_location_summary', 'requested_location_lat', 'requested_location_lng', 'conditions_summary', 'estimated_work_summary', 'internal_notes', 'customer_safe_summary']),
                $this->updateField($record, 'assessment_method', $data['assessment_method'] ?? null, ['desk_review', 'gis_review', 'network_review', 'field_survey', 'hybrid', 'other']),
                'updated_by' => $actor->id,
            ]);

            return $record->fresh();
        });
    }

    public function assignAssessor(FeasibilityCheck $feasibility, int $userId, User $actor): FeasibilityCheck
    {
        $this->assertScope($actor, $feasibility);

        return DB::transaction(function () use ($feasibility, $userId, $actor) {
            $record = FeasibilityCheck::lockForUpdate()->findOrFail($feasibility->id);
            if ($record->isTerminal()) {
                throw ValidationException::withMessages(['status' => 'A finalized feasibility can no longer be assigned.']);
            }
            $assessor = User::lockForUpdate()->findOrFail($userId);
            if ($assessor->company_id !== $record->company_id) {
                throw ValidationException::withMessages(['assigned_assessor_user_id' => 'Assessor must belong to the feasibility company.']);
            }
            $record->update(['assigned_assessor_user_id' => $userId, 'updated_by' => $actor->id]);

            return $record->fresh();
        });
    }

    public function startAssessment(FeasibilityCheck $feasibility, User $actor): FeasibilityCheck
    {
        $this->assertScope($actor, $feasibility);

        return DB::transaction(function () use ($feasibility, $actor) {
            $record = FeasibilityCheck::lockForUpdate()->findOrFail($feasibility->id);
            $from = $record->status;
            if (! in_array('reviewing', self::TRANSITIONS[$from] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Cannot start an assessment from {$from}."]);
            }
            $record->update(['status' => 'reviewing', 'assessment_started_at' => now(), 'updated_by' => $actor->id]);
            $this->history($record, $actor, $from, 'reviewing');

            return $record->fresh();
        });
    }

    public function startSurvey(FeasibilityCheck $feasibility, array $data, User $actor): FeasibilitySurvey
    {
        $this->assertScope($actor, $feasibility);

        return DB::transaction(function () use ($feasibility, $data, $actor) {
            $record = FeasibilityCheck::lockForUpdate()->findOrFail($feasibility->id);
            $toStatus = ! empty($data['scheduled_at']) ? 'survey_scheduled' : 'survey_required';
            $from = $record->status;
            if (! in_array($toStatus, self::TRANSITIONS[$from] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Cannot start a survey from {$from}."]);
            }
            if (! empty($data['assigned_to'])) {
                $surveyor = User::lockForUpdate()->find($data['assigned_to']);
                if (! $surveyor || $surveyor->company_id !== $record->company_id) {
                    throw ValidationException::withMessages(['assigned_to' => 'Surveyor must belong to the feasibility company.']);
                }
            }
            $survey = $record->survey ?? new FeasibilitySurvey;
            $survey->feasibility_check_id = $record->id;
            $survey->company_id = $record->company_id;
            if (! empty($data['assigned_to'])) {
                $survey->assigned_to = $data['assigned_to'];
            }
            $survey->status = ! empty($data['scheduled_at']) ? 'scheduled' : 'requested';
            if (! empty($data['scheduled_at'])) {
                $survey->scheduled_at = $data['scheduled_at'];
            }
            $survey->updated_by = $actor->id;
            if (! $survey->exists) {
                $survey->created_by = $actor->id;
            }
            $survey->save();

            $record->update(['status' => $toStatus, 'updated_by' => $actor->id]);
            $this->history($record, $actor, $from, $toStatus, context: ['survey_id' => $survey->id]);

            return $survey->fresh();
        });
    }

    public function completeSurvey(FeasibilityCheck $feasibility, array $data, User $actor): FeasibilitySurvey
    {
        $this->assertScope($actor, $feasibility);

        return DB::transaction(function () use ($feasibility, $data, $actor) {
            $record = FeasibilityCheck::lockForUpdate()->findOrFail($feasibility->id);
            $survey = $record->survey;
            if (! $survey) {
                throw ValidationException::withMessages(['status' => 'No survey record exists. Start a survey first.']);
            }
            if (! in_array('surveyed', self::TRANSITIONS[$record->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'A survey can only be completed on an open feasibility.']);
            }
            $survey = FeasibilitySurvey::lockForUpdate()->findOrFail($survey->id);
            $from = $record->status;
            $updates = [
                'status' => 'completed',
                'started_at' => $survey->started_at ?? now(),
                'completed_at' => now(),
                'updated_by' => $actor->id,
            ];
            if (array_key_exists('location_verified', $data) && $data['location_verified'] !== null) {
                $updates['location_verified'] = $data['location_verified'];
            }
            if (array_key_exists('civil_work_required', $data) && $data['civil_work_required'] !== null) {
                $updates['civil_work_required'] = $data['civil_work_required'];
            }
            foreach (['coordinates_verified_lat', 'coordinates_verified_lng', 'nearest_infrastructure_notes', 'access_path_notes', 'signal_observations', 'survey_notes', 'findings'] as $nullableField) {
                if (array_key_exists($nullableField, $data)) {
                    $updates[$nullableField] = $data[$nullableField];
                }
            }
            if (array_key_exists('recommended_outcome', $data) && $data['recommended_outcome'] !== null) {
                $updates['recommended_outcome'] = $data['recommended_outcome'];
            }
            $updates += $this->updateField($survey, 'installation_complexity', $data['installation_complexity'] ?? null, ['simple', 'moderate', 'complex', 'unknown']);
            $survey->update($updates);
            $record->update(['status' => 'surveyed', 'updated_by' => $actor->id]);
            $this->history($record, $actor, $from, 'surveyed', context: ['survey_id' => $survey->id]);

            return $survey->fresh();
        });
    }

    public function addEvidence(FeasibilityCheck $feasibility, array $data, User $actor): FeasibilityEvidence
    {
        $this->assertScope($actor, $feasibility);

        return DB::transaction(function () use ($feasibility, $data, $actor) {
            $record = FeasibilityCheck::lockForUpdate()->findOrFail($feasibility->id);
            if ($record->isTerminal()) {
                throw ValidationException::withMessages(['status' => 'Evidence cannot be recorded on a finalized feasibility.']);
            }
            $this->validateEvidenceReference($record->company_id, $data);

            return FeasibilityEvidence::create([
                'feasibility_check_id' => $record->id,
                'company_id' => $record->company_id,
                'evidence_type' => $data['evidence_type'],
                'referenced_entity_type' => $data['referenced_entity_type'] ?? null,
                'referenced_entity_id' => $data['referenced_entity_id'] ?? null,
                'observation_summary' => $data['observation_summary'],
                'recorded_by' => $actor->id,
                'recorded_at' => now(),
            ]);
        });
    }

    public function addCondition(FeasibilityCheck $feasibility, array $data, User $actor): FeasibilityCondition
    {
        $this->assertScope($actor, $feasibility);

        return DB::transaction(function () use ($feasibility, $data, $actor) {
            $record = FeasibilityCheck::lockForUpdate()->findOrFail($feasibility->id);
            if ($record->isTerminal()) {
                throw ValidationException::withMessages(['status' => 'Conditions cannot be added to a finalized feasibility.']);
            }

            return FeasibilityCondition::create([
                'feasibility_check_id' => $record->id,
                'company_id' => $record->company_id,
                'condition_type' => $data['condition_type'],
                'description' => $data['description'],
                'is_mandatory' => $data['is_mandatory'] ?? true,
                'status' => $data['status'] ?? 'pending',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        });
    }

    public function resolveCondition(FeasibilityCondition $condition, array $data, User $actor): FeasibilityCondition
    {
        $this->assertScope($actor, $condition);

        return DB::transaction(function () use ($condition, $data, $actor) {
            $record = FeasibilityCondition::lockForUpdate()->findOrFail($condition->id);
            $resolution = $data['status'];
            if (! in_array($resolution, ['resolved', 'waived', 'not_applicable'], true)) {
                throw ValidationException::withMessages(['status' => 'Conditions can only be resolved, waived, or marked not applicable.']);
            }
            $record->update([
                'status' => $resolution,
                'resolution_notes' => $data['resolution_notes'] ?? $record->resolution_notes,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
                'updated_by' => $actor->id,
            ]);

            return $record->fresh();
        });
    }

    public function recordOutcome(FeasibilityCheck $feasibility, string $outcome, array $data, User $actor): FeasibilityCheck
    {
        $this->assertScope($actor, $feasibility);

        return DB::transaction(function () use ($feasibility, $outcome, $data, $actor) {
            $record = FeasibilityCheck::lockForUpdate()->findOrFail($feasibility->id);
            if (! in_array($outcome, FeasibilityCheck::OUTCOMES, true)) {
                throw ValidationException::withMessages(['outcome' => 'Invalid outcome.']);
            }
            if (! in_array($outcome, self::TRANSITIONS[$record->status] ?? [], true)) {
                throw ValidationException::withMessages(['outcome' => "Cannot record a {$outcome} outcome from {$record->status}."]);
            }
            if (! empty($data['valid_until']) && now()->gt($data['valid_until'])) {
                throw ValidationException::withMessages(['valid_until' => 'Valid until must be in the future.']);
            }
            $from = $record->status;
            $validUntil = $data['valid_until'] ?? $record->valid_until;
            $record->update([
                'status' => $outcome,
                'outcome' => $outcome,
                'assessed_at' => now(),
                'valid_until' => $validUntil,
                'conditions_summary' => $data['conditions_summary'] ?? $record->conditions_summary,
                'estimated_work_summary' => $data['estimated_work_summary'] ?? $record->estimated_work_summary,
                'customer_safe_summary' => $data['customer_safe_summary'] ?? $record->customer_safe_summary,
                'updated_by' => $actor->id,
            ]);
            $this->history($record, $actor, $from, $outcome, toOutcome: $outcome, context: ['decision' => $data['decision_summary'] ?? null, 'valid_until' => isset($validUntil) ? (string) $validUntil : null]);

            if ($record->lead_id) {
                $lead = Lead::lockForUpdate()->findOrFail($record->lead_id);
                if ($outcome === 'feasible') {
                    $this->syncLead($lead, 'feasible', $actor, ['feasibility_id' => $record->id, 'reason' => 'feasibility outcome feasible']);
                } elseif ($outcome === 'not_feasible') {
                    $this->syncLead($lead, 'not_feasible', $actor, ['feasibility_id' => $record->id, 'reason' => 'feasibility outcome not feasible']);
                }
                // conditionally_feasible keeps the lead at feasibility_pending (conservative; explicit confirmation required).
            }

            return $record->fresh();
        });
    }

    public function recordConfirmation(FeasibilityCheck $feasibility, array $data, User $actor): CustomerConfirmation
    {
        $this->assertScope($actor, $feasibility);

        return DB::transaction(function () use ($feasibility, $data, $actor) {
            $record = FeasibilityCheck::lockForUpdate()->findOrFail($feasibility->id);
            if (! $record->confirmsAllowedOutcome()) {
                throw ValidationException::withMessages(['status' => 'Confirmation requires a feasible or conditionally feasible outcome.']);
            }
            if ($record->isExpired()) {
                throw ValidationException::withMessages(['valid_until' => 'This feasibility has expired and can no longer be confirmed.']);
            }
            if ($record->confirmation) {
                throw ValidationException::withMessages(['feasibility_check_id' => 'This feasibility already has a confirmation record.']);
            }

            return CustomerConfirmation::create([
                'feasibility_check_id' => $record->id,
                'company_id' => $record->company_id,
                'lead_id' => $record->lead_id,
                'customer_id' => $record->customer_id,
                'status' => 'pending',
                'channel' => $data['channel'],
                'reference_code' => $this->generateConfirmationCode(),
                'presented_summary' => $data['presented_summary'],
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $actor->id,
            ]);
        });
    }

    public function confirmCustomerIntent(CustomerConfirmation $confirmation, User $actor): CustomerConfirmation
    {
        $this->assertScope($actor, $confirmation);

        return DB::transaction(function () use ($confirmation, $actor) {
            $record = CustomerConfirmation::lockForUpdate()->findOrFail($confirmation->id);
            if ($record->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Only a pending confirmation can be confirmed.']);
            }
            $feasibility = $record->feasibilityCheck;
            if ($feasibility->isExpired()) {
                $record->update(['status' => 'expired']);
                throw ValidationException::withMessages(['valid_until' => 'This feasibility has expired; the confirmation was marked as expired.']);
            }
            $record->update(['status' => 'confirmed', 'confirmed_at' => now()]);
            if ($record->lead_id) {
                $lead = Lead::lockForUpdate()->findOrFail($record->lead_id);
                $this->syncLead($lead, 'confirmed', $actor, ['feasibility_id' => $feasibility->id, 'confirmation_id' => $record->id, 'reason' => 'customer confirmed']);
            }

            return $record->fresh();
        });
    }

    public function declineCustomerIntent(CustomerConfirmation $confirmation, User $actor): CustomerConfirmation
    {
        $this->assertScope($actor, $confirmation);

        return DB::transaction(function () use ($confirmation, $actor) {
            $record = CustomerConfirmation::lockForUpdate()->findOrFail($confirmation->id);
            if ($record->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Only a pending confirmation can be declined.']);
            }
            $record->update(['status' => 'declined', 'declined_at' => now()]);
            if ($record->lead_id) {
                $lead = Lead::lockForUpdate()->findOrFail($record->lead_id);
                $this->syncLead($lead, 'lost', $actor, ['feasibility_id' => $record->feasibility_check_id, 'confirmation_id' => $record->id, 'reason' => 'customer declined confirmation']);
            }

            return $record->fresh();
        });
    }

    // ── Internals ─────────────────────────────────────────────

    private function createRecord(Company $company, array $data, User $actor): FeasibilityCheck
    {
        if (! empty($data['customer_address_id'])) {
            $address = CustomerAddress::lockForUpdate()->findOrFail($data['customer_address_id']);
            if ($address->company_id !== $company->id) {
                throw ValidationException::withMessages(['customer_address_id' => 'Address must belong to the same company.']);
            }
        }

        $record = FeasibilityCheck::create([
            ...Arr::only($data, ['lead_id', 'customer_id', 'customer_address_id', 'requested_service_summary', 'requested_location_summary', 'requested_location_lat', 'requested_location_lng', 'internal_notes', 'customer_safe_summary', 'valid_until']),
            $this->fieldValue('assessment_method', $data['assessment_method'] ?? null, ['desk_review', 'gis_review', 'network_review', 'field_survey', 'hybrid', 'other'], 'desk_review'),
            'company_id' => $company->id,
            'feasibility_code' => $this->generateFeasibilityCode($company),
            'status' => 'requested',
            'outcome' => null,
            'requested_at' => $data['requested_at'] ?? now(),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        $this->history($record, $actor, null, 'requested', context: ['label' => 'created']);

        return $record;
    }

    private function assertNoActiveFeasibilityForLead(Lead $lead): void
    {
        if (FeasibilityCheck::where('company_id', $lead->company_id)->where('lead_id', $lead->id)->whereIn('status', FeasibilityCheck::ACTIVE_STATUSES)->exists()) {
            throw ValidationException::withMessages(['lead_id' => 'This lead already has an active feasibility check.']);
        }
    }

    private function generateFeasibilityCode(Company $company): string
    {
        do {
            $code = 'FC-'.now()->format('ymd').'-'.strtoupper(Str::random(4));
        } while (FeasibilityCheck::where('company_id', $company->id)->where('feasibility_code', $code)->whereNull('deleted_at')->exists());

        return $code;
    }

    private function generateConfirmationCode(): string
    {
        do {
            $code = 'CONF-'.now()->format('ymd').'-'.strtoupper(Str::random(4));
        } while (CustomerConfirmation::where('reference_code', $code)->exists());

        return $code;
    }

    private function validateEvidenceReference(int $companyId, array $data): void
    {
        if (empty($data['referenced_entity_type'])) {
            return;
        }
        $type = $data['referenced_entity_type'];
        if (! in_array($type, FeasibilityEvidence::REFERENCED_ENTITY_TYPES, true)) {
            throw ValidationException::withMessages(['referenced_entity_type' => 'Unsupported referenced entity type.']);
        }
        $entity = $this->entityModelClass($type)::whereKey($data['referenced_entity_id'] ?? 0)->first();
        if (! $entity) {
            throw ValidationException::withMessages(['referenced_entity_id' => 'Referenced entity does not exist.']);
        }
        if ((int) $entity->company_id !== $companyId) {
            throw ValidationException::withMessages(['referenced_entity_id' => 'Referenced entity must belong to the feasibility company.']);
        }
    }

    private function entityModelClass(string $type): string
    {
        return match ($type) {
            'Site' => Site::class,
            'Asset' => Asset::class,
            'NetworkPort' => NetworkPort::class,
            'PonDomain' => PonDomain::class,
            'PassiveOpticalPort' => PassiveOpticalPort::class,
            'FiberTermination' => FiberTermination::class,
            'NetworkConnectionPoint' => NetworkConnectionPoint::class,
            'FiberCable' => FiberCable::class,
            'FiberSegment' => FiberSegment::class,
            default => throw new \RuntimeException('Unsupported referenced entity type'),
        };
    }

    private function syncLead(Lead $lead, string $toStatus, User $actor, array $context = []): void
    {
        $from = $lead->status;
        if ($from === $toStatus) {
            return;
        }
        $lead->update(['status' => $toStatus, 'updated_by' => $actor->id]);
        $lead->lifecycleHistory()->create([
            'company_id' => $lead->company_id,
            'from_status' => $from,
            'to_status' => $toStatus,
            'context' => $context ?: null,
            'actor_id' => $actor->id,
        ]);
    }

    private function history(FeasibilityCheck $record, User $actor, ?string $fromStatus, string $toStatus, ?string $toOutcome = null, array $context = []): void
    {
        $record->lifecycleHistory()->create([
            'company_id' => $record->company_id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'from_outcome' => $record->outcome ?? null,
            'to_outcome' => $toOutcome,
            'context' => $context ?: null,
            'actor_id' => $actor->id,
        ]);
    }

    private function fieldValue(string $field, mixed $value, array $allowed, ?string $default): array
    {
        if ($value !== null) {
            if (in_array($value, $allowed, true)) {
                return [$field => $value];
            }

            return [$field => $default];
        }

        return [$field => $default];
    }

    private function updateField(Model $record, string $field, mixed $value, array $allowed): array
    {
        if ($value !== null && in_array($value, $allowed, true)) {
            return [$field => $value];
        }

        return [$field => $record->{$field}];
    }

    private function assertScope(User $actor, Model $resource): void
    {
        if (! ManagementScopeService::isInScope($actor, $resource)) {
            throw ValidationException::withMessages(['id' => 'You do not have access to this resource.']);
        }
    }
}
