import axios from 'axios';
import { API_BASE_URL } from './config';
import { api } from './client';

export type LoginResultado = {
  access_token: string;
  refresh_token: string;
  expires_in: number;
  usuario: { id: number; nombre: string; nivel: number };
  id_empresa: number | null;
  requiere_seleccion_empresa: boolean;
};

export async function login(
  cedula: string,
  password: string,
  dispositivoId: string,
  forceLogin = false
): Promise<LoginResultado> {
  // Login no lleva Authorization todavía: axios "pelado" (no la instancia api con interceptor).
  const resp = await axios.post(`${API_BASE_URL}/auth/login`, {
    cedula,
    password,
    dispositivo_id: dispositivoId,
    force_login: forceLogin,
  });
  return resp.data.data;
}

export type Empresa = { id_empresa: number; ruc: string; nombre: string };

export async function empresas(): Promise<Empresa[]> {
  const resp = await api.get('/auth/empresas');
  return resp.data.data.empresas;
}

export async function seleccionarEmpresa(idEmpresa: number): Promise<{ access_token: string; id_empresa: number }> {
  const resp = await api.post('/auth/seleccionar-empresa', { id_empresa: idEmpresa });
  return resp.data.data;
}

export async function logout(refreshToken: string | null): Promise<void> {
  await api.post('/auth/logout', { refresh_token: refreshToken ?? undefined });
}
