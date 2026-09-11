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
import CableIcon from '@mui/icons-material/Cable';
import LanIcon from '@mui/icons-material/Lan';
import ReceiptLongIcon from '@mui/icons-material/ReceiptLong';
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
            { label: 'Network Operations', path: '/network/operations', icon: <LanIcon />, permission: 'net.network-ports.view' },
        ],
    },
    {
        label: 'BSS',
        icon: <ReceiptLongIcon />,
        children: [
            { label: 'Customers & Services', path: '/bss', icon: <ReceiptLongIcon />, permission: 'bss.customers.view' },
            { label: 'Leads', path: '/bss/leads', icon: <ReceiptLongIcon />, permission: 'bss.leads.view' },
            { label: 'Feasibility', path: '/bss/feasibility', icon: <ReceiptLongIcon />, permission: 'bss.feasibility.view' },
        ],
    },
    {
        label: 'Fiber Infrastructure',
        icon: <CableIcon />,
        children: [
            { label: 'Operations', path: '/fim', icon: <CableIcon />, permission: 'fim.cables.view' },
            { label: 'Map', path: '/fim/map', icon: <LocationOnIcon />, permission: 'fim.cables.view' },
        ],
    },
    {
        label: 'System',
        icon: <SettingsIcon />,
        children: [
            { label: 'Configuration', path: '/system/configuration', icon: <SettingsIcon />, permission: 'system.config.manage' },
            { label: 'Integrations', path: '/system/integrations', icon: <SettingsIcon />, permission: 'integrations.view' },
            { label: 'System Import', path: '/system-import', icon: <SyncIcon />, permission: 'imports.view' },
        ],
    },
];
