import React, {createContext, useContext, useState, useEffect} from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {apiClient} from '../api/client';

const TOKEN_KEY = '@viaferrata_token';

interface User {
  id: number;
  username: string;
  email: string;
  role?: string;
}

interface AuthContextType {
  token: string | null;
  user: User | null;
  isLoading: boolean;
  login: (token: string, user: User) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType>({
  token: null,
  user: null,
  isLoading: true,
  login: async () => {},
  logout: async () => {},
  refreshUser: async () => {},
});

export const AuthProvider: React.FC<{children: React.ReactNode}> = ({
  children,
}) => {
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const init = async () => {
      try {
        const savedToken = await AsyncStorage.getItem(TOKEN_KEY);
        if (savedToken) {
          setToken(savedToken);
          apiClient.defaults.headers.common[
            'Authorization'
          ] = `Bearer ${savedToken}`;
          try {
            const res = await apiClient.get('/auth/me');
            setUser(res.data);
          } catch {
            await AsyncStorage.removeItem(TOKEN_KEY);
            setToken(null);
            delete apiClient.defaults.headers.common['Authorization'];
          }
        }
      } catch (e) {
        // ignore
      } finally {
        setIsLoading(false);
      }
    };
    init();
  }, []);

  const login = async (newToken: string, newUser: User) => {
    await AsyncStorage.setItem(TOKEN_KEY, newToken);
    apiClient.defaults.headers.common[
      'Authorization'
    ] = `Bearer ${newToken}`;
    setToken(newToken);
    setUser(newUser);
  };

  const logout = async () => {
    await AsyncStorage.removeItem(TOKEN_KEY);
    delete apiClient.defaults.headers.common['Authorization'];
    setToken(null);
    setUser(null);
  };

  const refreshUser = async () => {
    if (!token) return;
    try {
      const res = await apiClient.get('/auth/me');
      setUser(res.data);
    } catch {
      await logout();
    }
  };

  return (
    <AuthContext.Provider
      value={{token, user, isLoading, login, logout, refreshUser}}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => useContext(AuthContext);
