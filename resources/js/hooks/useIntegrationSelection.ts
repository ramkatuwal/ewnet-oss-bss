import { useState, useMemo, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { importApi, ImportProvider } from '@/api/import';

interface UseIntegrationSelectionProps {
  provider: 'uisp' | 'librenms';
}

interface UseIntegrationSelectionResult {
  providers: ImportProvider[];
  filteredIntegrations: ImportProvider[];
  selectedIntegration: number | null;
  setSelectedIntegration: (id: number | null) => void;
  isLoading: boolean;
  isError: boolean;
  error: Error | null;
}

export const useIntegrationSelection = ({ provider }: UseIntegrationSelectionProps): UseIntegrationSelectionResult => {
  const [selectedIntegration, setSelectedIntegration] = useState<number | null>(null);

  const { data: providers, isLoading, isError, error } = useQuery({
    queryKey: ['import-providers'],
    queryFn: importApi.getProviders,
  });

  const filteredIntegrations = useMemo(() => {
    if (!providers) return [];
    return providers.filter((p: ImportProvider) => p.provider === provider && p.enabled);
  }, [providers, provider]);

  // Auto-select if exactly one enabled integration exists
  useEffect(() => {
    if (filteredIntegrations.length === 1 && !selectedIntegration) {
      setSelectedIntegration(filteredIntegrations[0].id);
    } else if (filteredIntegrations.length === 0) {
      setSelectedIntegration(null);
    }
  }, [filteredIntegrations, selectedIntegration]);

  return {
    providers: providers || [],
    filteredIntegrations,
    selectedIntegration,
    setSelectedIntegration,
    isLoading,
    isError,
    error,
  };
};
