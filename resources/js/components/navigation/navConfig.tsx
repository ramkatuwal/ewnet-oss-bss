import DashboardIcon from '@mui/icons-material/Dashboard';
import FolderIcon from '@mui/icons-material/Folder';
import BusinessIcon from '@mui/icons-material/Business';
import LocationOnIcon from '@mui/icons-material/LocationOn';
import StorefrontIcon from '@mui/icons-material/Storefront';
import PersonIcon from '@mui/icons-material/Person';
import SecurityIcon from '@mui/icons-material/Security';
import VpnKeyIcon from '@mui/icons-material/VpnKey';
import PolicyIcon from '@mui/icons-material/Policy';
import DescriptionIcon from '@mui/icons-material/Description';
import ManageSearchIcon from '@mui/icons-material/ManageSearch';
import ApartmentIcon from '@mui/icons-material/Apartment';
import SettingsIcon from '@mui/icons-material/Settings';
import InfoIcon from '@mui/icons-material/Info';
import InventoryIcon from '@mui/icons-material/Inventory';
import SyncIcon from '@mui/icons-material/Sync';
import CloudUploadIcon from '@mui/icons-material/CloudUpload';
import CloudDownloadIcon from '@mui/icons-material/CloudDownload';
import type { NavItem } from '@/types';

export const navigationItems: NavItem[] = [
    {
        label: 'Dashboard',
        path: '/dashboard',
        icon: <DashboardIcon />,
    },
    {
        label: 'Manage',
        icon: <FolderIcon />,
        children: [
            { label: 'Companies', path: '/manage/companies', icon: <BusinessIcon />, permission: 'companies.view' },
            { label: 'Regions', path: '/manage/regions', icon: <LocationOnIcon />, permission: 'regions.view' },
            { label: 'Branches', path: '/manage/branches', icon: <StorefrontIcon />, permission: 'branches.view' },
            { label: 'Departments', path: '/manage/departments', icon: <ApartmentIcon />, permission: 'departments.view' },
            { label: 'Users', path: '/manage/users', icon: <PersonIcon />, permission: 'users.view' },
            { label: 'Roles', path: '/manage/roles', icon: <SecurityIcon />, permission: 'roles.view' },
            { label: 'Permissions', path: '/manage/permissions', icon: <VpnKeyIcon />, permission: 'permissions.view' },
        ],
    },
    {
        label: 'Audit',
        icon: <ManageSearchIcon />,
        children: [
            { label: 'Security Activity', path: '/audit/security', icon: <PolicyIcon />, permission: 'system.debug.view' },
            { label: 'System Logs', path: '/audit/system-logs', icon: <DescriptionIcon />, permission: 'system.debug.view' },
            { label: 'System Info', path: '/audit/system-info', icon: <InfoIcon />, permission: 'system.info.view' },
        ],
    },
    {
        label: 'Network',
        icon: <LocationOnIcon />,
        children: [
            { label: 'Sites', path: '/network/sites', icon: <LocationOnIcon />, permission: 'sites.view' },
            { label: 'Assets', path: '/network/assets', icon: <InventoryIcon />, permission: 'assets.view' },
        ],
    },
    {
        label: 'System',
        icon: <SettingsIcon />,
        children: [
            { label: 'Configuration', path: '/system/configuration', icon: <SettingsIcon />, permission: 'system.config.manage' },
            { label: 'Integrations', path: '/system/integrations', icon: <SettingsIcon />, permission: 'integrations.view' },
            { label: 'LibreNMS Import', path: '/system/integrations/librenms/import', icon: <SyncIcon />, permission: 'librenms.import' },
            { label: 'LibreNMS Site Import', path: '/system/integrations/librenms/sites', icon: <LocationOnIcon />, permission: 'librenms.import' },
            { label: 'UISP Import', path: '/system/integrations/uisp/import', icon: <CloudUploadIcon />, permission: 'integration.uisp.import' },
            { label: 'System Import', path: '/system/import', icon: <CloudDownloadIcon />, permission: 'integration.uisp.import' },

        ],
    },
];
