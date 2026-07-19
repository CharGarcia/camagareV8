import { api } from './client';

export type ConsignacionPendiente = {
  id: number;
  serie: string;
  secuencial: string;
  fecha_emision: string;
  fecha_entrega: string | null;
  hora_entrega_desde: string | null;
  hora_entrega_hasta: string | null;
  punto_partida: string | null;
  punto_llegada: string | null;
  total: string;
  estado: string;
  cliente_nombre: string;
  cliente_direccion: string | null;
  cliente_identificacion: string;
  responsable_traslado_nombre: string | null;
};

export async function listarPendientesEntrega(params: { buscar?: string; page?: number } = {}) {
  const resp = await api.get('/entregas/pendientes', { params });
  return resp.data as { data: ConsignacionPendiente[]; meta: { total: number; total_pages: number; page: number } };
}

export type ConsignacionDetalleLinea = {
  id_producto: number;
  producto_nombre: string;
  producto_codigo: string;
  cantidad: string;
  precio_unitario: string;
  total: string;
};

export type ConsignacionDetalle = ConsignacionPendiente & {
  punto_partida: string | null;
  punto_llegada: string | null;
  observaciones: string | null;
  detalles: ConsignacionDetalleLinea[];
};

export async function obtenerConsignacion(id: number) {
  const resp = await api.get('/entregas/obtener', { params: { id } });
  return resp.data.data as ConsignacionDetalle;
}

export type RegistrarEntregaInput = {
  id_consignacion: number;
  uuid_cliente: string;
  capturado_en: string;
  latitud?: number;
  longitud?: number;
  precision_m?: number;
  firma_base64?: string;
  dispositivo_id?: string;
  observaciones?: string;
};

export async function registrarEntrega(input: RegistrarEntregaInput) {
  const resp = await api.post('/entregas/registrar', input);
  return resp.data.data as { id_entrega: number; ya_entregada: boolean };
}
