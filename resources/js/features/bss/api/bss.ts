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
export type LeadStatus = 'new' | 'qualified' | 'converted' | 'lost';
export interface Lead { id: number; company_id: number; lead_code: string; name: string; email: string | null; phone: string | null; status: LeadStatus; qualification: Record<string, unknown> | null; converted_customer_id: number | null; }
export interface Customer360 extends Customer { contacts: { id: number; kind: string; value: string; verified: boolean }[]; contact_persons: { id: number; name: string; role: string | null; email: string | null; phone: string | null }[]; addresses: { id: number; kind: string; line1: string; city: string | null; country_code: string | null }[]; business_profile: { legal_name: string | null; registration_number: string | null; tax_number: string | null; industry: string | null } | null; verifications: { id: number; kind: string; status: string; reference: string | null; reason: string | null }[]; notes: { id: number; body: string; created_at: string }[]; }
export type CustomerInput = Omit<Customer, 'id' | 'status'> & { status?: Exclude<CustomerStatus, 'retired'> };
export type ServiceInput = Omit<Service, 'id' | 'status'> & { status?: Exclude<ServiceStatus, 'retired'> };

const collection = <T>(path: string, params?: Record<string, unknown>) => apiClient.get<PaginatedResponse<T>>(path, { params }).then(response => response.data);
const data = <T>(request: Promise<{ data: { data: T } }>) => request.then(response => response.data.data);

export const bssApi = {
    customers: (params?: Record<string, unknown>) => collection<Customer>('/api/v1/bss/customers', params),
    customer: (id: number) => data<Customer>(apiClient.get(`/api/v1/bss/customers/${id}`)),
    createCustomer: (body: CustomerInput) => data<Customer>(apiClient.post('/api/v1/bss/customers', body)),
    updateCustomer: (id: number, body: Partial<CustomerInput>) => data<Customer>(apiClient.patch(`/api/v1/bss/customers/${id}`, body)),
    retireCustomer: (id: number) => apiClient.delete(`/api/v1/bss/customers/${id}`),
    customer360: (id: number) => data<Customer360>(apiClient.get(`/api/v1/bss/customers/${id}/360`)),
    addContact: (id: number, body: { kind: 'email' | 'phone' | 'other'; value: string; is_primary?: boolean }) => data(apiClient.post(`/api/v1/bss/customers/${id}/contacts`, body)),
    addPerson: (id: number, body: { name: string; role?: string; email?: string; phone?: string }) => data(apiClient.post(`/api/v1/bss/customers/${id}/contact-persons`, body)),
    addAddress: (id: number, body: { kind: 'billing' | 'service' | 'other'; line1: string; city?: string; country_code?: string }) => data(apiClient.post(`/api/v1/bss/customers/${id}/addresses`, body)),
    saveBusinessProfile: (id: number, body: { legal_name?: string; registration_number?: string; tax_number?: string; industry?: string }) => data(apiClient.put(`/api/v1/bss/customers/${id}/business-profile`, body)),
    addVerification: (id: number, body: { kind: string; status: 'pending' | 'verified' | 'rejected'; reference?: string; reason?: string }) => data(apiClient.post(`/api/v1/bss/customers/${id}/verifications`, body)),
    addNote: (id: number, body: { body: string }) => data(apiClient.post(`/api/v1/bss/customers/${id}/notes`, body)),
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
};
