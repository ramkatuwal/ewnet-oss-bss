import { describe, expect, it, vi } from 'vitest';

const get = vi.fn(() => Promise.resolve({ data: { data: [] } }));
const post = vi.fn(() => Promise.resolve({ data: { data: {} } }));
const put = vi.fn(() => Promise.resolve({ data: { data: {} } }));

vi.mock('@/api/client', () => ({
    apiClient: { get, post, put, patch: post, delete: post },
}));

describe('BSS API workflows', () => {
    it('sends transactional direct onboarding payloads and duplicate advisory queries', async () => {
        const { bssApi } = await import('./bss');
        await bssApi.onboardCustomer({ company_id: 1, customer_code: 'C-1', name: 'Customer', type: 'individual', email: null, phone: null, address: null, initial_contact: { kind: 'email', value: 'customer@example.test' } });
        await bssApi.customerDuplicateCandidates({ company_id: 1, name: 'Customer' });
        expect(post).toHaveBeenCalledWith('/api/v1/bss/customers/onboard', expect.objectContaining({ initial_contact: expect.any(Object) }));
        expect(get).toHaveBeenCalledWith('/api/v1/bss/customers/duplicate-candidates', { params: { company_id: 1, name: 'Customer' } });
    });

    it('calls customer 360 child-record and tag/flag endpoints with bounded identifiers', async () => {
        const { bssApi } = await import('./bss');
        await bssApi.addContact(10, { kind: 'email', value: 'a@example.test' });
        await bssApi.addPerson(10, { name: 'Alice' });
        await bssApi.addAddress(10, { kind: 'service', line1: '123 Main' });
        await bssApi.saveBusinessProfile(10, { legal_name: 'Acme' });
        await bssApi.addVerification(10, { kind: 'kyc', status: 'verified', reference: 'REF-1' });
        await bssApi.addNote(10, { body: 'Note body' });
        await bssApi.assignTag(10, 7);
        await bssApi.tags(1, 'flag');
        await bssApi.createTag({ company_id: 1, kind: 'tag', name: 'VIP', color: '#ff0000' });
        expect(post).toHaveBeenCalledWith('/api/v1/bss/customers/10/contacts', expect.objectContaining({ kind: 'email' }));
        expect(post).toHaveBeenCalledWith('/api/v1/bss/customers/10/contact-persons', expect.objectContaining({ name: 'Alice' }));
        expect(post).toHaveBeenCalledWith('/api/v1/bss/customers/10/addresses', expect.objectContaining({ line1: '123 Main' }));
        expect(put).toHaveBeenCalledWith('/api/v1/bss/customers/10/business-profile', { legal_name: 'Acme' });
        expect(post).toHaveBeenCalledWith('/api/v1/bss/customers/10/verifications', expect.objectContaining({ reference: 'REF-1' }));
        expect(post).toHaveBeenCalledWith('/api/v1/bss/customers/10/notes', expect.objectContaining({ body: 'Note body' }));
        expect(post).toHaveBeenCalledWith('/api/v1/bss/customers/10/tags', { tag_id: 7 });
        expect(get).toHaveBeenCalledWith('/api/v1/bss/tags', { params: { company_id: 1, kind: 'flag' } });
        expect(post).toHaveBeenCalledWith('/api/v1/bss/tags', expect.objectContaining({ name: 'VIP' }));
    });

    it('calls idempotent lead lifecycle, duplicate, and conversion endpoints', async () => {
        const { bssApi } = await import('./bss');
        await bssApi.qualifyLead(3, { serviceable: true });
        await bssApi.unqualifyLead(3);
        await bssApi.loseLead(3, 'not serviceable');
        await bssApi.duplicateCandidates(3);
        await bssApi.convertLead(3, 'CUS-3');
        await bssApi.convertLead(4, 'CUS-4', 99);
        expect(post).toHaveBeenCalledWith('/api/v1/bss/leads/3/qualify', { qualification: { serviceable: true } });
        expect(post).toHaveBeenCalledWith('/api/v1/bss/leads/3/unqualify');
        expect(post).toHaveBeenCalledWith('/api/v1/bss/leads/3/lose', { reason: 'not serviceable' });
        expect(get).toHaveBeenCalledWith('/api/v1/bss/leads/3/duplicate-candidates');
        expect(post).toHaveBeenCalledWith('/api/v1/bss/leads/3/convert', { customer_code: 'CUS-3', use_customer_id: undefined });
        expect(post).toHaveBeenCalledWith('/api/v1/bss/leads/4/convert', { customer_code: 'CUS-4', use_customer_id: 99 });
    });

    it('exposes customer service assignment and lead create/list endpoints', async () => {
        const { bssApi } = await import('./bss');
        await bssApi.createCustomerService(5, { service_id: 8 });
        await bssApi.transitionCustomerService(12, 'active');
        await bssApi.createLead({ company_id: 1, lead_code: 'LD-1', name: 'Prospect', email: null, phone: null });
        expect(post).toHaveBeenCalledWith('/api/v1/bss/customers/5/services', { service_id: 8, starts_on: undefined });
        expect(post).toHaveBeenCalledWith('/api/v1/bss/customer-services/12/transition', { status: 'active' });
        expect(post).toHaveBeenCalledWith('/api/v1/bss/leads', expect.objectContaining({ lead_code: 'LD-1' }));
    });

    it('requests feasibility from a lead with coordinates and refines it via assessment steps', async () => {
        const { bssApi } = await import('./bss');
        await bssApi.createFeasibility({ lead_id: 3, requested_service_summary: 'Broadband 50 Mbps', requested_location_lat: 27.7172, requested_location_lng: 85.324 });
        await bssApi.updateFeasibility(7, { assessment_method: 'desk_review', internal_notes: 'Checked hub capacity.' });
        await bssApi.assignFeasibility(7, 12);
        await bssApi.startFeasibilityAssessment(7);
        expect(post).toHaveBeenCalledWith('/api/v1/bss/feasibility-checks', expect.objectContaining({ lead_id: 3, requested_service_summary: 'Broadband 50 Mbps', requested_location_lat: 27.7172, requested_location_lng: 85.324 }));
        expect(post).toHaveBeenCalledWith('/api/v1/bss/feasibility-checks/7', expect.objectContaining({ assessment_method: 'desk_review' }));
        expect(post).toHaveBeenCalledWith('/api/v1/bss/feasibility-checks/7/assign', { assigned_assessor_user_id: 12 });
        expect(post).toHaveBeenCalledWith('/api/v1/bss/feasibility-checks/7/start-assessment');
    });

    it('drives survey, evidence, conditions, decision, and confirmation lifecycle endpoints', async () => {
        const { bssApi } = await import('./bss');
        await bssApi.startFeasibilitySurvey(7, { assigned_to: 12, scheduled_at: '2026-09-20T10:00:00' });
        await bssApi.completeFeasibilitySurvey(7, { location_verified: true, civil_work_required: false, recommended_outcome: 'feasible', installation_complexity: 'moderate' });
        await bssApi.feasibilityEvidence(7);
        await bssApi.addFeasibilityEvidence(7, { evidence_type: 'site_observation', observation_summary: 'CLE visible.' });
        await bssApi.feasibilityConditions(7);
        await bssApi.addFeasibilityCondition(7, { condition_type: 'pole_permission', description: 'Permission letter required.', is_mandatory: true });
        await bssApi.resolveFeasibilityCondition(9, { status: 'resolved', resolution_notes: 'Letter received.' });
        await bssApi.decideFeasibility(7, { outcome: 'feasible', valid_until: '2026-12-31', conditions_summary: 'None.', estimated_work_summary: '2 days' });
        await bssApi.feasibilityConfirmations(7);
        await bssApi.createFeasibilityConfirmation(7, { channel: 'phone', presented_summary: 'Customer confirmed intent.', notes: 'Spoke to owner.' });
        await bssApi.confirmCustomerConfirmation(21);
        await bssApi.declineCustomerConfirmation(22);
        await bssApi.leadFeasibility(3);
        expect(post).toHaveBeenCalledWith('/api/v1/bss/feasibility-checks/7/start-survey', expect.objectContaining({ assigned_to: 12, scheduled_at: '2026-09-20T10:00:00' }));
        expect(post).toHaveBeenCalledWith('/api/v1/bss/feasibility-checks/7/complete-survey', expect.objectContaining({ location_verified: true, installation_complexity: 'moderate', recommended_outcome: 'feasible' }));
        expect(get).toHaveBeenCalledWith('/api/v1/bss/feasibility-checks/7/evidence');
        expect(post).toHaveBeenCalledWith('/api/v1/bss/feasibility-checks/7/evidence', expect.objectContaining({ evidence_type: 'site_observation' }));
        expect(get).toHaveBeenCalledWith('/api/v1/bss/feasibility-checks/7/conditions');
        expect(post).toHaveBeenCalledWith('/api/v1/bss/feasibility-checks/7/conditions', expect.objectContaining({ condition_type: 'pole_permission', is_mandatory: true }));
        expect(post).toHaveBeenCalledWith('/api/v1/bss/feasibility-conditions/9/resolve', expect.objectContaining({ status: 'resolved' }));
        expect(post).toHaveBeenCalledWith('/api/v1/bss/feasibility-checks/7/decide', expect.objectContaining({ outcome: 'feasible' }));
        expect(get).toHaveBeenCalledWith('/api/v1/bss/feasibility-checks/7/confirmations');
        expect(post).toHaveBeenCalledWith('/api/v1/bss/feasibility-checks/7/confirmations', expect.objectContaining({ channel: 'phone', presented_summary: 'Customer confirmed intent.' }));
        expect(post).toHaveBeenCalledWith('/api/v1/bss/confirmations/21/confirm');
        expect(post).toHaveBeenCalledWith('/api/v1/bss/confirmations/22/decline');
        expect(get).toHaveBeenCalledWith('/api/v1/bss/leads/3/feasibility');
    });

    it('supports bounded feasibility list queries', async () => {
        const { bssApi } = await import('./bss');
        await bssApi.feasibilityChecks({ company_id: 1, status: 'reviewing', per_page: 25 });
        expect(get).toHaveBeenCalledWith('/api/v1/bss/feasibility-checks', { params: { company_id: 1, status: 'reviewing', per_page: 25 } });
    });
});
