import { apiClient } from '@/api/client';
import type { PaginatedResponse } from '@/types';

export type CustomerType = 'individual' | 'organization';
export type CustomerStatus = 'active' | 'inactive' | 'retired';
export type ServiceType = 'internet' | 'voice' | 'iptv' | 'other';
export type ServiceStatus = 'active' | 'inactive' | 'retired';
export type CustomerServiceStatus = 'pending' | 'active' | 'suspended' | 'terminated' | 'cancelled';
export interface Customer { id: number; company_id: number; customer_code: string; name: string; type: CustomerType; status: CustomerStatus; email: string | null; phone: string | null; address: string | null; }
export interface Service { id: number; company_id: number; service_code: string; name: string; type: ServiceType; status: ServiceStatus; description: string | null; }
export interface CustomerService { id: number; customer_id: number; service_id: number; company_id: number; status: CustomerServiceStatus; starts_on: string | null; ends_on: string | null; service?: Service; }
export type LeadStatus = 'new' | 'qualified' | 'converted' | 'lost' | 'feasibility_pending' | 'feasible' | 'not_feasible' | 'confirmed';
export interface Lead { id: number; company_id: number; lead_code: string; name: string; email: string | null; phone: string | null; status: LeadStatus; qualification: Record<string, unknown> | null; converted_customer_id: number | null; }
export type FeasibilityStatus = 'requested' | 'reviewing' | 'survey_required' | 'survey_scheduled' | 'surveyed' | 'feasible' | 'conditionally_feasible' | 'not_feasible' | 'cancelled' | 'expired';
export type FeasibilityOutcome = 'feasible' | 'conditionally_feasible' | 'not_feasible';
export type ConfirmationStatus = 'pending' | 'confirmed' | 'declined' | 'expired' | 'cancelled';
export interface FeasibilityCheck {
    id: number; company_id: number; feasibility_code: string; lead_id: number | null; customer_id: number | null; customer_address_id: number | null;
    requested_service_summary: string; requested_location_summary: string | null; requested_location_lat: number | null; requested_location_lng: number | null;
    status: FeasibilityStatus; outcome: FeasibilityOutcome | null; assessment_method: string | null; assigned_assessor_user_id: number | null;
    requested_at: string | null; assessment_started_at: string | null; assessed_at: string | null; valid_until: string | null;
    conditions_summary: string | null; estimated_work_summary: string | null; internal_notes: string | null; customer_safe_summary: string | null;
    created_at: string; updated_at: string;
    lead?: { id: number; lead_code: string; name: string; status: string } | null;
    customer?: { id: number; customer_code: string; name: string; status: string } | null;
    customer_address?: { id: number; line1: string; city: string | null } | null;
    assigned_assessor?: { id: number; name: string } | null;
    evidence?: FeasibilityEvidence[];
    survey?: FeasibilitySurvey | null;
    conditions?: FeasibilityCondition[];
    confirmation?: CustomerConfirmation | null;
    lifecycle_history?: { id: number; from_status: string | null; to_status: string; from_outcome: string | null; to_outcome: string | null; actor: string | null; created_at: string }[];
}
export interface FeasibilityEvidence { id: number; feasibility_check_id: number; company_id: number; evidence_type: string; referenced_entity_type: string | null; referenced_entity_id: number | null; observation_summary: string; recorded_by_name: string | null; recorded_at: string; created_at: string; }
export interface FeasibilitySurvey { id: number; feasibility_check_id: number; company_id: number; assigned_to_name: string | null; status: string; requested_at: string | null; scheduled_at: string | null; started_at: string | null; completed_at: string | null; location_verified: boolean; coordinates_verified_lat: number | null; coordinates_verified_lng: number | null; nearest_infrastructure_notes: string | null; access_path_notes: string | null; civil_work_required: boolean; installation_complexity: string | null; signal_observations: string | null; survey_notes: string | null; findings: string | null; recommended_outcome: FeasibilityOutcome | null; }
export interface FeasibilityCondition { id: number; feasibility_check_id: number; company_id: number; condition_type: string; description: string; is_mandatory: boolean; status: 'pending' | 'in_progress' | 'resolved' | 'waived' | 'not_applicable'; resolution_notes: string | null; resolved_by_name: string | null; resolved_at: string | null; created_at: string; }
export interface CustomerConfirmation { id: number; feasibility_check_id: number; company_id: number; lead_id: number | null; customer_id: number | null; status: ConfirmationStatus; channel: string; reference_code: string | null; presented_summary: string; notes: string | null; recorded_by_name: string | null; confirmed_at: string | null; declined_at: string | null; created_at: string; }
export interface Customer360 extends Customer { contacts: { id: number; kind: string; value: string; verified: boolean }[]; contact_persons: { id: number; name: string; role: string | null; email: string | null; phone: string | null }[]; addresses: { id: number; kind: string; line1: string; city: string | null; country_code: string | null }[]; business_profile: { legal_name: string | null; registration_number: string | null; tax_number: string | null; industry: string | null } | null; verifications: { id: number; kind: string; status: string; reference: string | null; reason: string | null }[]; notes: { id: number; body: string; created_at: string }[]; }
export type CustomerInput = Omit<Customer, 'id' | 'status'> & { status?: Exclude<CustomerStatus, 'retired'> };
export type CustomerOnboardingInput = CustomerInput & { source_id?: number; initial_contact?: { kind: 'email' | 'phone' | 'other'; value: string }; initial_address?: { kind?: 'billing' | 'service' | 'other'; line1: string }; business_profile?: { legal_name?: string; registration_number?: string }; verification?: { kind: string; status: 'pending' | 'verified' | 'rejected'; reference?: string } };
export type ServiceInput = Omit<Service, 'id' | 'status'> & { status?: Exclude<ServiceStatus, 'retired'> };

const collection = <T>(path: string, params?: Record<string, unknown>) => apiClient.get<PaginatedResponse<T>>(path, { params }).then(response => response.data);
const data = <T>(request: Promise<{ data: { data: T } }>) => request.then(response => response.data.data);

export const bssApi = {
    customers: (params?: Record<string, unknown>) => collection<Customer>('/api/v1/bss/customers', params),
    customer: (id: number) => data<Customer>(apiClient.get(`/api/v1/bss/customers/${id}`)),
    createCustomer: (body: CustomerInput) => data<Customer>(apiClient.post('/api/v1/bss/customers', body)),
    onboardCustomer: (body: CustomerOnboardingInput) => data<Customer>(apiClient.post('/api/v1/bss/customers/onboard', body)),
    customerDuplicateCandidates: (params: { company_id: number; name?: string; email?: string; phone?: string }) => data<Customer[]>(apiClient.get('/api/v1/bss/customers/duplicate-candidates', { params })),
    updateCustomer: (id: number, body: Partial<CustomerInput>) => data<Customer>(apiClient.patch(`/api/v1/bss/customers/${id}`, body)),
    retireCustomer: (id: number) => apiClient.delete(`/api/v1/bss/customers/${id}`),
    customer360: (id: number) => data<Customer360>(apiClient.get(`/api/v1/bss/customers/${id}/360`)),
    addContact: (id: number, body: { kind: 'email' | 'phone' | 'other'; value: string; is_primary?: boolean }) => data(apiClient.post(`/api/v1/bss/customers/${id}/contacts`, body)),
    addPerson: (id: number, body: { name: string; role?: string; email?: string; phone?: string }) => data(apiClient.post(`/api/v1/bss/customers/${id}/contact-persons`, body)),
    addAddress: (id: number, body: { kind: 'billing' | 'service' | 'other'; line1: string; city?: string; country_code?: string }) => data(apiClient.post(`/api/v1/bss/customers/${id}/addresses`, body)),
    saveBusinessProfile: (id: number, body: { legal_name?: string; registration_number?: string; tax_number?: string; industry?: string }) => data(apiClient.put(`/api/v1/bss/customers/${id}/business-profile`, body)),
    addVerification: (id: number, body: { kind: string; status: 'pending' | 'verified' | 'rejected'; reference?: string; reason?: string }) => data(apiClient.post(`/api/v1/bss/customers/${id}/verifications`, body)),
    addNote: (id: number, body: { body: string }) => data(apiClient.post(`/api/v1/bss/customers/${id}/notes`, body)),
    tags: (companyId: number, kind: 'tag' | 'flag') => data<{ id: number; name: string; kind: 'tag' | 'flag' }[]>(apiClient.get('/api/v1/bss/tags', { params: { company_id: companyId, kind } })),
    createTag: (body: { company_id: number; kind: 'tag' | 'flag'; name: string; color?: string }) => data(apiClient.post('/api/v1/bss/tags', body)),
    assignTag: (customerId: number, tagId: number) => apiClient.post(`/api/v1/bss/customers/${customerId}/tags`, { tag_id: tagId }),
    services: (params?: Record<string, unknown>) => collection<Service>('/api/v1/bss/services', params),
    service: (id: number) => data<Service>(apiClient.get(`/api/v1/bss/services/${id}`)),
    createService: (body: ServiceInput) => data<Service>(apiClient.post('/api/v1/bss/services', body)),
    updateService: (id: number, body: Partial<ServiceInput>) => data<Service>(apiClient.patch(`/api/v1/bss/services/${id}`, body)),
    retireService: (id: number) => apiClient.delete(`/api/v1/bss/services/${id}`),
    customerServices: (customerId: number, params?: Record<string, unknown>) => collection<CustomerService>(`/api/v1/bss/customers/${customerId}/services`, params),
    createCustomerService: (customerId: number, body: { service_id: number; starts_on?: string }) => data<CustomerService>(apiClient.post(`/api/v1/bss/customers/${customerId}/services`, body)),
    transitionCustomerService: (id: number, status: Exclude<CustomerServiceStatus, 'pending'>) => data<CustomerService>(apiClient.post(`/api/v1/bss/customer-services/${id}/transition`, { status })),
    leads: (params?: Record<string, unknown>) => collection<Lead>('/api/v1/bss/leads', params),
    createLead: (body: Pick<Lead, 'company_id' | 'lead_code' | 'name' | 'email' | 'phone'>) => data<Lead>(apiClient.post('/api/v1/bss/leads', body)),
    lead: (id: number) => data<Lead>(apiClient.get(`/api/v1/bss/leads/${id}`)),
    updateLead: (id: number, body: Partial<Pick<Lead, 'lead_code' | 'name' | 'email' | 'phone'>>) => data<Lead>(apiClient.patch(`/api/v1/bss/leads/${id}`, body)),
    qualifyLead: (id: number, qualification: Record<string, unknown>) => data<Lead>(apiClient.post(`/api/v1/bss/leads/${id}/qualify`, { qualification })),
    unqualifyLead: (id: number) => data<Lead>(apiClient.post(`/api/v1/bss/leads/${id}/unqualify`)),
    loseLead: (id: number, reason?: string) => data<Lead>(apiClient.post(`/api/v1/bss/leads/${id}/lose`, { reason })),
    duplicateCandidates: (id: number) => data<Pick<Customer, 'id' | 'customer_code' | 'name'>[]>(apiClient.get(`/api/v1/bss/leads/${id}/duplicate-candidates`)),
    convertLead: (id: number, customerCode: string, useCustomerId?: number) => data<Customer>(apiClient.post(`/api/v1/bss/leads/${id}/convert`, { customer_code: customerCode, use_customer_id: useCustomerId })),
    feasibilityChecks: (params?: Record<string, unknown>) => collection<FeasibilityCheck>('/api/v1/bss/feasibility-checks', params),
    feasibilityCheck: (id: number) => data<FeasibilityCheck>(apiClient.get(`/api/v1/bss/feasibility-checks/${id}`)),
    createFeasibility: (body: { lead_id?: number; customer_id?: number; customer_address_id?: number; requested_service_summary: string; requested_location_summary?: string; requested_location_lat?: number; requested_location_lng?: number }) => data<FeasibilityCheck>(apiClient.post('/api/v1/bss/feasibility-checks', body)),
    updateFeasibility: (id: number, body: Partial<Pick<FeasibilityCheck, 'assessment_method' | 'internal_notes' | 'customer_safe_summary' | 'requested_service_summary' | 'requested_location_summary'>> & { requested_location_lat?: number | null; requested_location_lng?: number | null }) => data<FeasibilityCheck>(apiClient.patch(`/api/v1/bss/feasibility-checks/${id}`, body)),
    assignFeasibility: (id: number, assigned_assessor_user_id: number) => data<FeasibilityCheck>(apiClient.post(`/api/v1/bss/feasibility-checks/${id}/assign`, { assigned_assessor_user_id })),
    startFeasibilityAssessment: (id: number) => data<FeasibilityCheck>(apiClient.post(`/api/v1/bss/feasibility-checks/${id}/start-assessment`)),
    startFeasibilitySurvey: (id: number, body?: { assigned_to?: number; scheduled_at?: string }) => data<FeasibilitySurvey>(apiClient.post(`/api/v1/bss/feasibility-checks/${id}/start-survey`, body)),
    completeFeasibilitySurvey: (id: number, body: Record<string, unknown>) => data<FeasibilitySurvey>(apiClient.post(`/api/v1/bss/feasibility-checks/${id}/complete-survey`, body)),
    feasibilityEvidence: (id: number) => data<FeasibilityEvidence[]>(apiClient.get(`/api/v1/bss/feasibility-checks/${id}/evidence`)),
    addFeasibilityEvidence: (id: number, body: { evidence_type: string; referenced_entity_type?: string; referenced_entity_id?: number; observation_summary: string }) => data<FeasibilityEvidence>(apiClient.post(`/api/v1/bss/feasibility-checks/${id}/evidence`, body)),
    feasibilityConditions: (id: number) => data<FeasibilityCondition[]>(apiClient.get(`/api/v1/bss/feasibility-checks/${id}/conditions`)),
    addFeasibilityCondition: (id: number, body: { condition_type: string; description: string; is_mandatory?: boolean }) => data<FeasibilityCondition>(apiClient.post(`/api/v1/bss/feasibility-checks/${id}/conditions`, body)),
    resolveFeasibilityCondition: (conditionId: number, body: { status: 'resolved' | 'waived' | 'not_applicable'; resolution_notes: string }) => data<FeasibilityCondition>(apiClient.post(`/api/v1/bss/feasibility-conditions/${conditionId}/resolve`, body)),
    decideFeasibility: (id: number, body: { outcome: FeasibilityOutcome; valid_until?: string; conditions_summary?: string; estimated_work_summary?: string; decision_summary?: string }) => data<FeasibilityCheck>(apiClient.post(`/api/v1/bss/feasibility-checks/${id}/decide`, body)),
    feasibilityConfirmations: (id: number) => data<CustomerConfirmation[]>(apiClient.get(`/api/v1/bss/feasibility-checks/${id}/confirmations`)),
    createFeasibilityConfirmation: (id: number, body: { channel: string; presented_summary: string; notes?: string }) => data<CustomerConfirmation>(apiClient.post(`/api/v1/bss/feasibility-checks/${id}/confirmations`, body)),
    confirmCustomerConfirmation: (confirmationId: number) => data<CustomerConfirmation>(apiClient.post(`/api/v1/bss/confirmations/${confirmationId}/confirm`)),
    declineCustomerConfirmation: (confirmationId: number) => data<CustomerConfirmation>(apiClient.post(`/api/v1/bss/confirmations/${confirmationId}/decline`)),
    leadFeasibility: (leadId: number) => data<FeasibilityCheck[]>(apiClient.get(`/api/v1/bss/leads/${leadId}/feasibility`)),
};
