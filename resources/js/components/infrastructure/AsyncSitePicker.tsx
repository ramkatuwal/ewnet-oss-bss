import { Controller, Control, FieldValues, Path } from 'react-hook-form';
import SearchableSelect, { SearchableSelectOption } from '@/components/forms/SearchableSelect';
import { sitesApi, Site } from '@/api/sites';

type SiteOption = SearchableSelectOption<number> & { site: Site };

const toOption = (site: Site): SiteOption => ({
    value: site.id,
    label: `${site.site_code} - ${site.name}`,
    secondary: [site.company?.name, site.region?.name, site.branch?.name].filter(Boolean).join(' / '),
    site,
});

export const AsyncSitePicker = <T extends FieldValues>({ control, error, disabled, selectedSite, onSelected, companyId, regionId, branchId }: {
    control: Control<T>;
    error?: string;
    disabled?: boolean;
    selectedSite?: Site | null;
    onSelected?: (site: Site | null) => void;
    companyId?: number;
    regionId?: number;
    branchId?: number;
}) => (
    <Controller
        name={'site_id' as Path<T>}
        control={control}
        render={({ field }) => (
            <SearchableSelect<SiteOption>
                label="Site *"
                value={selectedSite ? toOption(selectedSite) : null}
                onChange={(option) => {
                    field.onChange(option?.value);
                    onSelected?.(option?.site ?? null);
                }}
                loadOptions={async (search) => {
                    const result = await sitesApi.list({
                        search: search || undefined,
                        per_page: 50,
                        company_id: companyId || undefined,
                        region_id: regionId || undefined,
                        branch_id: branchId || undefined,
                    });
                    return result.data.map(toOption);
                }}
                getOptionLabel={(option) => option.label}
                getOptionSecondary={(option) => option.secondary}
                placeholder="Search by site code or name..."
                required
                disabled={disabled}
                error={Boolean(error)}
                helperText={error}
            />
        )}
    />
);
