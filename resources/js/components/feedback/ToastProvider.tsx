import React, { createContext, useContext, useState, useCallback } from 'react';
import { Snackbar, Alert, AlertColor } from '@mui/material';

interface ToastMessage {
    id: number;
    severity: AlertColor;
    message: string;
}

interface ToastContextType {
    showToast: (message: string, severity?: AlertColor) => void;
}

const ToastContext = createContext<ToastContextType>({ showToast: () => {} });

export const useToast = () => useContext(ToastContext);

let toastId = 0;

export const ToastProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
    const [toasts, setToasts] = useState<ToastMessage[]>([]);

    const showToast = useCallback((message: string, severity: AlertColor = 'info') => {
        const id = ++toastId;
        setToasts((prev) => [...prev, { id, severity, message }]);
    }, []);

    const handleClose = (id: number) => {
        setToasts((prev) => prev.filter((t) => t.id !== id));
    };

    return (
        <ToastContext.Provider value={{ showToast }}>
            {children}
            {toasts.map((toast) => (
                <Snackbar
                    key={toast.id}
                    open
                    autoHideDuration={5000}
                    onClose={() => handleClose(toast.id)}
                    anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
                    sx={{ mb: toasts.indexOf(toast) * 6 }}
                >
                    <Alert
                        onClose={() => handleClose(toast.id)}
                        severity={toast.severity}
                        variant="filled"
                        sx={{ width: '100%' }}
                    >
                        {toast.message}
                    </Alert>
                </Snackbar>
            ))}
        </ToastContext.Provider>
    );
};
