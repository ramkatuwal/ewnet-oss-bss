import DashboardIcon from '@mui/icons-material/Dashboard';
import FolderIcon from '@mui/icons-material/Folder';
import BusinessIcon from '@mui/icons-material/Business';
import LocationOnIcon from '@mui/icons-material/LocationOn';
import StorefrontIcon from '@mui/icons-material/Storefront';
import PersonIcon from '@mui/icons-material/Person';
import SecurityIcon from '@mui/icons-material/Security';
import VpnKeyIcon from '@mui/icons-material/VpnKey';
import PolicyIcon from '@mui/icons-material/Policy';
import ManageSearchIcon from '@mui/icons-material/ManageSearch';
import ApartmentIcon from '@mui/icons-material/Apartment';
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
        permission: 'system.debug.view',
        children: [
            { label: 'Security Activity', path: '/audit/security', icon: <PolicyIcon />, permission: 'system.debug.view' },
        ],
    },
];
