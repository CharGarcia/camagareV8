// Endpoints PÚBLICOS de asistencia (App\controllers\AsistenciaController), sin sesión ni JWT —
// exactamente los que ya usa app/views/publica/asistencia/marcar.php desde el navegador. Por eso
// NO se usa la instancia `api` de client.ts (baseURL /api/v1 + Bearer token): se llama directo
// contra ASSET_BASE_URL, igual que ASSET_BASE_URL ya se usa para archivos estáticos.
import axios from 'axios';
import { ASSET_BASE_URL } from './config';

export type InfoQr = { ok: boolean; valido: boolean; nombre: string | null; exige_gps?: boolean; error?: string };

/** Info de un token (empleado o punto) antes de marcar — solo uno de los dos params. */
export async function obtenerInfoQr(params: { e?: string } | { p?: string }): Promise<InfoQr> {
  const resp = await axios.get(`${ASSET_BASE_URL}/asistencia/info-qr`, { params, timeout: 15000 });
  return resp.data as InfoQr;
}

export type TipoMarcacion = 'entrada' | 'salida';

export type ResultadoMarcacion = {
  ok: boolean;
  id?: number;
  tipo?: TipoMarcacion;
  empleado?: string;
  punto?: string;
  estado?: 'valida' | 'sospechosa';
  distancia_m?: number | null;
  observacion?: string | null;
  error?: string;
};

export async function registrarMarcacion(input: {
  tokenEmpleado: string;
  tokenPunto: string;
  tipo: TipoMarcacion;
  latitud?: number;
  longitud?: number;
  /** dataURL base64 (data:image/jpeg;base64,...), igual que captura el <canvas> de la web. */
  selfie?: string;
}): Promise<ResultadoMarcacion> {
  const fd = new FormData();
  fd.append('tokenEmpleado', input.tokenEmpleado);
  fd.append('tokenPunto', input.tokenPunto);
  fd.append('tipo', input.tipo);
  if (input.latitud !== undefined) fd.append('latitud', String(input.latitud));
  if (input.longitud !== undefined) fd.append('longitud', String(input.longitud));
  if (input.selfie) fd.append('selfie', input.selfie);

  const resp = await axios.post(`${ASSET_BASE_URL}/asistencia/registrar`, fd, {
    timeout: 30000,
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return resp.data as ResultadoMarcacion;
}
