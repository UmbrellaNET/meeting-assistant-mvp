'use client';
import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { api, setToken } from '@/lib/api';
import type { User } from '@/lib/types';

type AuthContextValue = { user: User | null; loading: boolean; login: (email:string,password:string)=>Promise<void>; logout:()=>Promise<void>; refresh:()=>Promise<void> };
const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({children}:{children:React.ReactNode}) {
  const [user,setUser] = useState<User|null>(null);
  const [loading,setLoading] = useState(true);
  const refresh = async () => { try { setUser(await api<User>('/auth/me')); } catch { setUser(null); } finally { setLoading(false); } };
  useEffect(()=>{ void refresh(); },[]);
  const login = async (email:string,password:string) => { const result=await api<{token:string;user:User}>('/auth/login',{method:'POST',body:JSON.stringify({email,password})}); setToken(result.token); setUser(result.user); };
  const logout = async () => { try { await api('/auth/logout',{method:'POST'}); } finally { setToken(null); setUser(null); } };
  const value=useMemo(()=>({user,loading,login,logout,refresh}),[user,loading]);
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
export function useAuth(){ const value=useContext(AuthContext); if(!value) throw new Error('useAuth must be used inside AuthProvider'); return value; }
