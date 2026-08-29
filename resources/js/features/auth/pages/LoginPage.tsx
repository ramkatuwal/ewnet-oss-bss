import React, { useState, useEffect } from 'react';
import {
    Box,
    Paper,
    Typography,
    TextField,
    Button,
    Alert,
    CircularProgress,
    InputAdornment,
    IconButton,
} from '@mui/material';
import { Visibility, VisibilityOff } from '@mui/icons-material';
import { useNavigate } from 'react-router-dom';
import { useAuthStore } from '@/stores/authStore';

export const LoginPage: React.FC = () => {
    const navigate = useNavigate();
    const { login, isLoading } = useAuthStore();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [validationErrors, setValidationErrors] = useState<{ email?: string; password?: string }>({});
    
    const [branding, setBranding] = useState({
        app_name: 'EWNET',
        logo_path: null as string | null,
        login_branding: 'Sign in to your account'
    });

    useEffect(() => {
        console.log('[LoginPage] Fetching branding...');
        fetch('/api/v1/branding')
            .then((res) => {
                console.log('[LoginPage] Response status:', res.status);
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json();
            })
            .then((data) => {
                console.log('[LoginPage] Branding data:', data);
                if (data && data.data) {
                    setBranding({
                        app_name: data.data.app_name || 'EWNET',
                        logo_path: data.data.logo_path || null,
                        login_branding: data.data.login_branding || 'Sign in to your account'
                    });
                }
            })
            .catch((err) => {
                console.error('[LoginPage] Branding fetch failed:', err);
            });
    }, []);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setError(null);
        setValidationErrors({});

        const errors: { email?: string; password?: string } = {};
        if (!email) errors.email = 'Email is required';
        if (!password) errors.password = 'Password is required';
        if (Object.keys(errors).length > 0) {
            setValidationErrors(errors);
            return;
        }

        try {
            await login(email, password);
            navigate('/dashboard');
        } catch (err: any) {
            if (err.response?.status === 422) {
                const data = err.response?.data;
                if (data?.errors) setValidationErrors(data.errors);
                else if (data?.message) setError(data.message);
                else setError('Invalid email or password. Please try again.');
            } else if (err.response?.status === 401) {
                setError('Invalid email or password. Please try again.');
            } else {
                setError('An error occurred. Please try again.');
            }
        }
    };

    return (
        <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '100vh', bgcolor: 'background.default' }}>
            <Paper elevation={3} sx={{ p: 4, width: '100%', maxWidth: 400, borderRadius: 2 }}>
                <Box sx={{ textAlign: 'center', mb: 3 }}>
                    {branding.logo_path ? (
                        <Box
                            component="img"
                            src={branding.logo_path}
                            alt={branding.app_name}
                            sx={{ height: 80, width: 'auto', objectFit: 'contain', mb: 2 }}
                        />
                    ) : (
                        <Box sx={{ height: 80, mb: 2 }} />
                    )}
                    <Typography variant="h4" component="h1" gutterBottom>
                        {branding.app_name}
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                        {branding.login_branding}
                    </Typography>
                </Box>

                {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}

                <form onSubmit={handleSubmit} autoComplete="on">
                    <TextField
                        fullWidth label="Email" type="email" value={email} onChange={(e) => setEmail(e.target.value)}
                        margin="normal" required autoComplete="username" error={!!validationErrors.email}
                        helperText={validationErrors.email} disabled={isLoading}
                    />
                    <TextField
                        fullWidth label="Password" type={showPassword ? 'text' : 'password'} value={password}
                        onChange={(e) => setPassword(e.target.value)} margin="normal" required
                        autoComplete="current-password" error={!!validationErrors.password}
                        helperText={validationErrors.password} disabled={isLoading}
                        InputProps={{
                            endAdornment: (
                                <InputAdornment position="end">
                                    <IconButton onClick={() => setShowPassword(!showPassword)} edge="end">
                                        {showPassword ? <VisibilityOff /> : <Visibility />}
                                    </IconButton>
                                </InputAdornment>
                            ),
                        }}
                    />
                    <Button type="submit" fullWidth variant="contained" size="large" sx={{ mt: 3 }} disabled={isLoading}>
                        {isLoading ? <CircularProgress size={24} /> : 'Sign In'}
                    </Button>
                </form>
            </Paper>
        </Box>
    );
};
