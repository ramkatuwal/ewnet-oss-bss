<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CustomerConfirmationResource;
use App\Http\Resources\V1\FeasibilityCheckResource;
use App\Http\Resources\V1\FeasibilityConditionResource;
use App\Http\Resources\V1\FeasibilityEvidenceResource;
use App\Http\Resources\V1\FeasibilitySurveyResource;
use App\Models\FeasibilityCheck;
use App\Models\FeasibilityCondition;
use App\Models\Lead;
use App\Services\AuditService;
use App\Services\FeasibilityService;
use App\Services\ManagementScopeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FeasibilityCheckController extends Controller
{
    public function __construct(private readonly FeasibilityService $feasibility) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', FeasibilityCheck::class);
        $request->validate([
            'company_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:requested,reviewing,survey_required,survey_scheduled,surveyed,feasible,conditionally_feasible,not_feasible,cancelled,expired'],
            'outcome' => ['nullable', 'in:feasible,conditionally_feasible,not_feasible'],
            'lead_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $query = ManagementScopeService::applyScopeToQuery(FeasibilityCheck::query(), $request->user(), FeasibilityCheck::class);
        foreach (['company_id', 'status', 'outcome', 'lead_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->filled('search')) {
            $query->where(fn ($q) => $q->where('feasibility_code', 'ilike', '%'.$request->string('search').'%')->orWhere('requested_service_summary', 'ilike', '%'.$request->string('search').'%')->orWhere('requested_location_summary', 'ilike', '%'.$request->string('search').'%'));
        }

        return FeasibilityCheckResource::collection($query->with(['lead:id,lead_code,name,status', 'customer:id,customer_code,name,status', 'assignedAssessor:id,name', 'confirmation'])->orderByDesc('id')->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        $this->authorize('create', FeasibilityCheck::class);
        $data = $request->validate([
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_address_id' => ['nullable', 'integer', 'exists:customer_addresses,id'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'requested_service_summary' => ['required', 'string', 'max:2000'],
            'requested_location_summary' => ['nullable', 'string', 'max:2000'],
            'requested_location_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'requested_location_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'assessment_method' => ['nullable', 'in:desk_review,gis_review,network_review,field_survey,hybrid,other'],
            'requested_at' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
            'customer_safe_summary' => ['nullable', 'string', 'max:2000'],
        ]);
        if (isset($data['lead_id']) && ! isset($data['customer_id'])) {
            $feasibility = $this->feasibility->createFromLead(Lead::findOrFail($data['lead_id']), $data, $request->user());
        } elseif (isset($data['customer_id'])) {
            if (! isset($data['company_id'])) {
                throw ValidationException::withMessages(['company_id' => 'Company is required when creating a feasibility check for a customer.']);
            }
            $feasibility = $this->feasibility->createDirect($data, $request->user());
        } else {
            throw ValidationException::withMessages(['lead_id' => 'Either a lead or a customer is required when creating a feasibility check.']);
        }
        AuditService::log('bss.feasibility.created', 'success', $feasibility, ['feasibility_id' => $feasibility->id, 'company_id' => $feasibility->company_id, 'feasibility_code' => $feasibility->feasibility_code]);

        return (new FeasibilityCheckResource($feasibility->load('lead:id,lead_code,name,status', 'customer:id,customer_code,name,status')))->response()->setStatusCode(201);
    }

    public function show(FeasibilityCheck $feasibilityCheck)
    {
        $this->authorize('view', $feasibilityCheck);
        $feasibilityCheck->load([
            'lead', 'customer', 'customerAddress', 'assignedAssessor:id,name', 'evidence', 'survey', 'conditions', 'confirmation', 'lifecycleHistory.actor:id,name',
        ]);

        return new FeasibilityCheckResource($feasibilityCheck);
    }

    public function update(Request $request, FeasibilityCheck $feasibilityCheck)
    {
        $this->authorize('update', $feasibilityCheck);
        $data = $request->validate([
            'requested_service_summary' => ['sometimes', 'string', 'max:2000'],
            'requested_location_summary' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'requested_location_lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'requested_location_lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'assessment_method' => ['sometimes', 'nullable', 'in:desk_review,gis_review,network_review,field_survey,hybrid,other'],
            'internal_notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'customer_safe_summary' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
        $feasibility = $this->feasibility->updateAssessment($feasibilityCheck, $data, $request->user());
        AuditService::log('bss.feasibility.updated', 'success', $feasibility, ['feasibility_id' => $feasibility->id, 'company_id' => $feasibility->company_id]);

        return (new FeasibilityCheckResource($feasibility->load('lifecycleHistory')))->response();
    }

    public function assign(Request $request, FeasibilityCheck $feasibilityCheck)
    {
        $this->authorize('assign', $feasibilityCheck);
        $data = $request->validate(['assigned_assessor_user_id' => ['required', 'integer', 'exists:users,id']]);
        $feasibility = $this->feasibility->assignAssessor($feasibilityCheck, $data['assigned_assessor_user_id'], $request->user());
        AuditService::log('bss.feasibility.assigned', 'success', $feasibility, ['feasibility_id' => $feasibility->id, 'company_id' => $feasibility->company_id, 'assessor' => $data['assigned_assessor_user_id']]);

        return (new FeasibilityCheckResource($feasibility->load('lifecycleHistory')))->response();
    }

    public function startAssessment(FeasibilityCheck $feasibilityCheck, Request $request)
    {
        $this->authorize('update', $feasibilityCheck);
        $feasibility = $this->feasibility->startAssessment($feasibilityCheck, $request->user());
        AuditService::log('bss.feasibility.assessment_started', 'success', $feasibility, ['feasibility_id' => $feasibility->id, 'company_id' => $feasibility->company_id]);

        return (new FeasibilityCheckResource($feasibility->load('lifecycleHistory')))->response();
    }

    public function startSurvey(Request $request, FeasibilityCheck $feasibilityCheck)
    {
        $this->authorize('survey', $feasibilityCheck);
        $data = $request->validate(['assigned_to' => ['nullable', 'integer', 'exists:users,id'], 'scheduled_at' => ['nullable', 'date']]);
        $survey = $this->feasibility->startSurvey($feasibilityCheck, $data, $request->user());
        AuditService::log('bss.feasibility.survey_started', 'success', $survey, ['feasibility_id' => $feasibilityCheck->id, 'company_id' => $feasibilityCheck->company_id, 'survey_id' => $survey->id]);

        return new FeasibilitySurveyResource($survey);
    }

    public function completeSurvey(Request $request, FeasibilityCheck $feasibilityCheck)
    {
        $this->authorize('survey', $feasibilityCheck);
        $data = $request->validate([
            'location_verified' => ['sometimes', 'boolean'],
            'coordinates_verified_lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'coordinates_verified_lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'nearest_infrastructure_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'access_path_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'civil_work_required' => ['sometimes', 'boolean'],
            'installation_complexity' => ['sometimes', 'nullable', 'in:simple,moderate,complex,unknown'],
            'signal_observations' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'survey_notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'findings' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'recommended_outcome' => ['sometimes', 'nullable', 'in:feasible,conditionally_feasible,not_feasible'],
        ]);
        $survey = $this->feasibility->completeSurvey($feasibilityCheck, $data, $request->user());
        AuditService::log('bss.feasibility.survey_completed', 'success', $survey, ['feasibility_id' => $feasibilityCheck->id, 'company_id' => $feasibilityCheck->company_id, 'survey_id' => $survey->id]);

        return new FeasibilitySurveyResource($survey);
    }

    public function evidence(FeasibilityCheck $feasibilityCheck)
    {
        $this->authorize('view', $feasibilityCheck);

        return FeasibilityEvidenceResource::collection($feasibilityCheck->evidence()->orderByDesc('id')->get());
    }

    public function storeEvidence(Request $request, FeasibilityCheck $feasibilityCheck)
    {
        $this->authorize('evidence', $feasibilityCheck);
        $data = $request->validate([
            'evidence_type' => ['required', 'in:site_observation,asset_observation,port_observation,fiber_observation,pon_observation,gis_analysis,network_analysis,provider_observation,manual_observation,other'],
            'referenced_entity_type' => ['nullable', 'in:Site,Asset,NetworkPort,PonDomain,PassiveOpticalPort,FiberTermination,NetworkConnectionPoint,FiberCable,FiberSegment'],
            'referenced_entity_id' => ['nullable', 'required_with:referenced_entity_type', 'integer'],
            'observation_summary' => ['required', 'string', 'max:5000'],
        ]);
        $evidence = $this->feasibility->addEvidence($feasibilityCheck, $data, $request->user());
        AuditService::log('bss.feasibility.evidence_added', 'success', $evidence, ['feasibility_id' => $feasibilityCheck->id, 'company_id' => $feasibilityCheck->company_id, 'evidence_id' => $evidence->id]);

        return (new FeasibilityEvidenceResource($evidence))->response()->setStatusCode(201);
    }

    public function conditions(FeasibilityCheck $feasibilityCheck)
    {
        $this->authorize('view', $feasibilityCheck);

        return FeasibilityConditionResource::collection($feasibilityCheck->conditions()->orderByDesc('id')->get());
    }

    public function storeCondition(Request $request, FeasibilityCheck $feasibilityCheck)
    {
        $this->authorize('update', $feasibilityCheck);
        $data = $request->validate([
            'condition_type' => ['required', 'in:fiber_construction,pole_permission,equipment_requirement,capacity_upgrade,additional_survey,commercial_approval,civil_work,permits,other'],
            'description' => ['required', 'string', 'max:2000'],
            'is_mandatory' => ['sometimes', 'boolean'],
        ]);
        $condition = $this->feasibility->addCondition($feasibilityCheck, $data, $request->user());
        AuditService::log('bss.feasibility.condition_added', 'success', $condition, ['feasibility_id' => $feasibilityCheck->id, 'company_id' => $feasibilityCheck->company_id, 'condition_id' => $condition->id]);

        return (new FeasibilityConditionResource($condition))->response()->setStatusCode(201);
    }

    public function resolveCondition(Request $request, FeasibilityCondition $condition)
    {
        $this->authorize('update', $condition->feasibilityCheck);
        $data = $request->validate([
            'status' => ['required', 'in:resolved,waived,not_applicable'],
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $condition = $this->feasibility->resolveCondition($condition, $data, $request->user());
        AuditService::log('bss.feasibility.condition_resolved', 'success', $condition, ['feasibility_id' => $condition->feasibility_check_id, 'company_id' => $condition->company_id, 'condition_id' => $condition->id]);

        return new FeasibilityConditionResource($condition);
    }

    public function decide(Request $request, FeasibilityCheck $feasibilityCheck)
    {
        $this->authorize('decide', $feasibilityCheck);
        $data = $request->validate([
            'outcome' => ['required', 'in:feasible,conditionally_feasible,not_feasible'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
            'conditions_summary' => ['nullable', 'string', 'max:2000'],
            'estimated_work_summary' => ['nullable', 'string', 'max:2000'],
            'decision_summary' => ['nullable', 'string', 'max:2000'],
        ]);
        $feasibility = $this->feasibility->recordOutcome($feasibilityCheck, $data['outcome'], $data, $request->user());
        AuditService::log('bss.feasibility.decided', 'success', $feasibility, ['feasibility_id' => $feasibility->id, 'company_id' => $feasibility->company_id, 'outcome' => $feasibility->outcome]);

        return (new FeasibilityCheckResource($feasibility->load('lead:id,lead_code,name,status', 'lifecycleHistory')))->response();
    }

    public function confirmations(FeasibilityCheck $feasibilityCheck)
    {
        $this->authorize('view', $feasibilityCheck);

        return CustomerConfirmationResource::collection($feasibilityCheck->confirmations()->orderByDesc('id')->get());
    }

    public function storeConfirmation(Request $request, FeasibilityCheck $feasibilityCheck)
    {
        $this->authorize('createConfirmation', $feasibilityCheck);
        $data = $request->validate([
            'channel' => ['required', 'in:phone,office,email,portal,sales_agent,signed_document,other'],
            'presented_summary' => ['required', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $confirmation = $this->feasibility->recordConfirmation($feasibilityCheck, $data, $request->user());
        AuditService::log('bss.confirmation.recorded', 'success', $confirmation, ['feasibility_id' => $feasibilityCheck->id, 'company_id' => $feasibilityCheck->company_id, 'confirmation_id' => $confirmation->id]);

        return (new CustomerConfirmationResource($confirmation))->response()->setStatusCode(201);
    }

    public function forLead(Lead $lead)
    {
        $this->authorize('view', $lead);

        return FeasibilityCheckResource::collection($lead->feasibilityChecks()->with('lead:id,lead_code,name,status', 'customer:id,customer_code,name,status', 'assignedAssessor:id,name', 'confirmation')->orderByDesc('id')->get());
    }
}
