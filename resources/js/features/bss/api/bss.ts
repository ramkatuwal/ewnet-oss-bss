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
    qualifyLead: (id: number, qualification: Record<string, unknown>) => data<Lead>(apiClient.post(`/api/v1/bss/leads/${id}/qualify`, { qualification })),
    duplicateCandidates: (id: number) => data<Pick<Customer, 'id' | 'customer_code' | 'name'>[]>(apiClient.get(`/api/v1/bss/leads/${id}/duplicate-candidates`)),
    convertLead: (id: number, customerCode: string, useCustomerId?: number) => data<Customer>(apiClient.post(`/api/v1/bss/leads/${id}/convert`, { customer_code: customerCode, use_customer_id: useCustomerId })),
};
