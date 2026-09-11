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
});
