import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';
import * as authApi from '../api/auth';
import { setOnSesionExpirada } from '../api/client';
import {
  clearTokens,
  getAccessToken,
  getIdEmpresa,
  getOrCreateDispositivoId,
  getRefreshToken,
  setIdEmpresa as guardarIdEmpresa,
  setTokens,
} from './tokenStore';

type Usuario = { id: number; nombre: string; nivel: number };

type AvisoSesionActiva = { ultimaActividad?: string; cedula: string; password: string } | null;

type AuthState = {
  cargando: boolean;
  autenticado: boolean;
  usuario: Usuario | null;
  idEmpresa: number | null;
  requiereSeleccionEmpresa: boolean;
  avisoSesionActiva: AvisoSesionActiva;
  login: (cedula: string, password: string) => Promise<void>;
  forzarLoginDesdeAviso: () => Promise<void>;
  descartarAvisoSesionActiva: () => void;
  listarEmpresas: () => Promise<authApi.Empresa[]>;
  seleccionarEmpresa: (idEmpresa: number) => Promise<void>;
  logout: () => Promise<void>;
};

const AuthContext = createContext<AuthState | undefined>(undefined);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [cargando, setCargando] = useState(true);
  const [usuario, setUsuario] = useState<Usuario | null>(null);
  const [idEmpresa, setIdEmpresaState] = useState<number | null>(null);
  const [requiereSeleccionEmpresa, setRequiereSeleccionEmpresa] = useState(false);
  const [avisoSesionActiva, setAvisoSesionActiva] = useState<AvisoSesionActiva>(null);

  // Si el refresh token también falló (sesión realmente cerrada), volver a Login.
  useEffect(() => {
    setOnSesionExpirada(() => {
      setUsuario(null);
      setIdEmpresaState(null);
      setRequiereSeleccionEmpresa(false);
    });
    return () => setOnSesionExpirada(null);
  }, []);

  // Al abrir la app: si hay tokens guardados, se asume sesión válida (los interceptores
  // de axios renuevan el access_token en la primera llamada si ya venció).
  useEffect(() => {
    (async () => {
      const access = await getAccessToken();
      const refresh = await getRefreshToken();
      if (access && refresh) {
        const emp = await getIdEmpresa();
        setIdEmpresaState(emp);
        setUsuario({ id: 0, nombre: '', nivel: 0 });
        setRequiereSeleccionEmpresa(emp === null);
      }
      setCargando(false);
    })();
  }, []);

  async function iniciarSesion(cedula: string, password: string, forceLogin: boolean) {
    const dispositivoId = await getOrCreateDispositivoId();
    try {
      const data = await authApi.login(cedula, password, dispositivoId, forceLogin);
      await setTokens(data.access_token, data.refresh_token);
      setUsuario(data.usuario);
      setAvisoSesionActiva(null);

      if (data.id_empresa) {
        await guardarIdEmpresa(data.id_empresa);
        setIdEmpresaState(data.id_empresa);
        setRequiereSeleccionEmpresa(false);
      } else {
        setIdEmpresaState(null);
        setRequiereSeleccionEmpresa(true);
      }
    } catch (err: any) {
      const code = err?.response?.data?.error?.code;
      if (code === 'SESION_ACTIVA_OTRO_DISPOSITIVO') {
        setAvisoSesionActiva({ ultimaActividad: err.response.data.error.ultima_actividad, cedula, password });
        return;
      }
      throw err;
    }
  }

  const value = useMemo<AuthState>(
    () => ({
      cargando,
      autenticado: usuario !== null,
      usuario,
      idEmpresa,
      requiereSeleccionEmpresa,
      avisoSesionActiva,

      login: (cedula, password) => iniciarSesion(cedula, password, false),

      forzarLoginDesdeAviso: async () => {
        if (!avisoSesionActiva) return;
        await iniciarSesion(avisoSesionActiva.cedula, avisoSesionActiva.password, true);
      },

      descartarAvisoSesionActiva: () => setAvisoSesionActiva(null),

      listarEmpresas: () => authApi.empresas(),

      seleccionarEmpresa: async (idEmpresaElegida: number) => {
        const data = await authApi.seleccionarEmpresa(idEmpresaElegida);
        const refresh = await getRefreshToken();
        await setTokens(data.access_token, refresh ?? '');
        await guardarIdEmpresa(data.id_empresa);
        setIdEmpresaState(data.id_empresa);
        setRequiereSeleccionEmpresa(false);
      },

      logout: async () => {
        const refresh = await getRefreshToken();
        try {
          await authApi.logout(refresh);
        } catch {
          // Si falla la llamada de red, igual limpiamos localmente.
        }
        await clearTokens();
        setUsuario(null);
        setIdEmpresaState(null);
        setRequiereSeleccionEmpresa(false);
      },
    }),
    [cargando, usuario, idEmpresa, requiereSeleccionEmpresa, avisoSesionActiva]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth debe usarse dentro de <AuthProvider>');
  return ctx;
}
