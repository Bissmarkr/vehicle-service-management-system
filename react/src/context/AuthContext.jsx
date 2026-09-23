import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import api from '../services/api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [auth, setAuth] = useState(() => {
    const raw = localStorage.getItem('vsms_auth');
    try {
      return raw ? JSON.parse(raw) : null;
    } catch {
      localStorage.removeItem('vsms_auth');
      return null;
    }
  });

  useEffect(() => {
    const handleUnauthorized = () => setAuth(null);
    window.addEventListener('vsms:unauthorized', handleUnauthorized);
    return () => window.removeEventListener('vsms:unauthorized', handleUnauthorized);
  }, []);

  const value = useMemo(
    () => ({
      auth,
      login: (userData) => {
        localStorage.setItem('vsms_auth', JSON.stringify(userData));
        if (userData.token) localStorage.setItem('vsms_token', userData.token);
        setAuth(userData);
      },
      logout: async () => {
        try {
          if (localStorage.getItem('vsms_token')) await api.post('/logout');
        } catch {
          // Local state is cleared even when the server is unavailable.
        }
        localStorage.removeItem('vsms_auth');
        localStorage.removeItem('vsms_token');
        setAuth(null);
      },
    }),
    [auth],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

// The provider and hook intentionally share this context module.
// eslint-disable-next-line react-refresh/only-export-components
export function useAuth() {
  return useContext(AuthContext);
}
