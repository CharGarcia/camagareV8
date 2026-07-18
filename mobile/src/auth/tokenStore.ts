import * as SecureStore from 'expo-secure-store';

const ACCESS_KEY = 'cmg_access_token';
const REFRESH_KEY = 'cmg_refresh_token';
const EMPRESA_KEY = 'cmg_id_empresa';
const DEVICE_ID_KEY = 'cmg_dispositivo_id';
const SERIE_KEY = 'cmg_serie_pedidos';

export async function getAccessToken(): Promise<string | null> {
  return SecureStore.getItemAsync(ACCESS_KEY);
}

export async function getRefreshToken(): Promise<string | null> {
  return SecureStore.getItemAsync(REFRESH_KEY);
}

export async function getIdEmpresa(): Promise<number | null> {
  const v = await SecureStore.getItemAsync(EMPRESA_KEY);
  return v ? Number(v) : null;
}

export async function setTokens(accessToken: string, refreshToken: string): Promise<void> {
  await SecureStore.setItemAsync(ACCESS_KEY, accessToken);
  await SecureStore.setItemAsync(REFRESH_KEY, refreshToken);
}

export async function setIdEmpresa(idEmpresa: number): Promise<void> {
  await SecureStore.setItemAsync(EMPRESA_KEY, String(idEmpresa));
}

export async function clearTokens(): Promise<void> {
  await SecureStore.deleteItemAsync(ACCESS_KEY);
  await SecureStore.deleteItemAsync(REFRESH_KEY);
  await SecureStore.deleteItemAsync(EMPRESA_KEY);
}

/** Id de dispositivo estable por instalación (para sesiones_activas/refresh tokens). */
export async function getOrCreateDispositivoId(): Promise<string> {
  let id = await SecureStore.getItemAsync(DEVICE_ID_KEY);
  if (!id) {
    id = 'movil-' + Math.random().toString(36).slice(2) + '-' + Date.now().toString(36);
    await SecureStore.setItemAsync(DEVICE_ID_KEY, id);
  }
  return id;
}

export type SerieGuardada = {
  idEmpresa: number;
  id_establecimiento: number;
  establecimiento: string;
  id_punto_emision: number;
  punto_emision: string;
};

/** La serie guardada solo es válida si pertenece a la empresa activa actual. */
export async function getSerieSeleccionada(idEmpresaActual: number): Promise<SerieGuardada | null> {
  const raw = await SecureStore.getItemAsync(SERIE_KEY);
  if (!raw) return null;
  try {
    const serie: SerieGuardada = JSON.parse(raw);
    return serie.idEmpresa === idEmpresaActual ? serie : null;
  } catch {
    return null;
  }
}

export async function setSerieSeleccionada(serie: SerieGuardada): Promise<void> {
  await SecureStore.setItemAsync(SERIE_KEY, JSON.stringify(serie));
}

export async function clearSerie(): Promise<void> {
  await SecureStore.deleteItemAsync(SERIE_KEY);
}
