import axios, { AxiosError, InternalAxiosRequestConfig } from 'axios';
import { API_BASE_URL } from './config';
import { clearTokens, getAccessToken, getIdEmpresa, getRefreshToken, setTokens } from '../auth/tokenStore';

export const api = axios.create({ baseURL: API_BASE_URL, timeout: 15000 });

/** Se dispara cuando el refresh falla (sesión cerrada de verdad): la UI vuelve a Login. */
let onSesionExpirada: (() => void) | null = null;
export function setOnSesionExpirada(cb: (() => void) | null): void {
  onSesionExpirada = cb;
}

api.interceptors.request.use(async (config) => {
  const token = await getAccessToken();
  if (token) {
    config.headers.set('Authorization', `Bearer ${token}`);
  }
  return config;
});

let refreshingPromise: Promise<string | null> | null = null;

async function intentarRefrescar(): Promise<string | null> {
  const refreshToken = await getRefreshToken();
  if (!refreshToken) return null;
  const idEmpresa = await getIdEmpresa();
  try {
    const resp = await axios.post(`${API_BASE_URL}/auth/refresh`, {
      refresh_token: refreshToken,
      id_empresa: idEmpresa ?? undefined,
    });
    const { access_token, refresh_token } = resp.data.data;
    await setTokens(access_token, refresh_token);
    return access_token as string;
  } catch {
    await clearTokens();
    return null;
  }
}

type RetriableConfig = InternalAxiosRequestConfig & { _retry?: boolean };

api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const original = error.config as RetriableConfig | undefined;

    if (error.response?.status === 401 && original && !original._retry) {
      original._retry = true;
      if (!refreshingPromise) {
        refreshingPromise = intentarRefrescar().finally(() => {
          refreshingPromise = null;
        });
      }
      const nuevoAccessToken = await refreshingPromise;
      if (nuevoAccessToken) {
        original.headers.set('Authorization', `Bearer ${nuevoAccessToken}`);
        return api(original);
      }
      onSesionExpirada?.();
    }

    return Promise.reject(error);
  }
);

/** Extrae un mensaje legible de un error de la API ({ok:false,error:{code,message}}). */
export function mensajeError(err: unknown, fallback = 'Ocurrió un error inesperado.'): string {
  const ax = err as AxiosError<{ error?: { message?: string } }>;
  return ax.response?.data?.error?.message ?? fallback;
}

export function codigoError(err: unknown): string | null {
  const ax = err as AxiosError<{ error?: { code?: string } }>;
  return ax.response?.data?.error?.code ?? null;
}
