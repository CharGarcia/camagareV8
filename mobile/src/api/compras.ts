import { api } from './client';

export type CompraListado = {
  id: number;
  establecimiento_prov: string;
  punto_emision_prov: string;
  secuencial_prov: string;
  numero_autorizacion: string;
  tipo_comprobante: string;
  tipo_comprobante_nombre: string | null;
  fecha_emision: string;
  proveedor_nombre: string;
  proveedor_ruc: string;
  sustento_nombre: string | null;
  total_sin_impuestos: string;
  monto_iva: string;
  importe_total: string;
  total_pagado: string;
  total_nc: string;
  total_retencion: string;
  estado: string;
};

export async function listarCompras(params: { buscar?: string; page?: number } = {}) {
  const resp = await api.get('/compras/listar', { params });
  return resp.data as { data: CompraListado[]; meta: { total: number; total_pages: number; page: number } };
}

export type CompraDetalleLinea = {
  id: number;
  descripcion: string;
  producto_nombre: string | null;
  cantidad: string;
  precio_unitario: string;
  descuento: string;
  precio_total: string;
};

export type CompraDetalle = CompraListado & {
  proveedor_direccion: string | null;
  proveedor_email: string | null;
  detalles: CompraDetalleLinea[];
  pagos: Array<{ forma_pago: string; total: string; plazo?: string; unidad_tiempo?: string }>;
};

export async function obtenerCompra(id: number) {
  const resp = await api.get('/compras/obtener', { params: { id } });
  return resp.data.data as CompraDetalle;
}

export type CargaAutorizacionResultado = {
  id: number | null;
  existe: boolean;
  estado_registro: string;
  mensaje: string;
  numero_documento: string | null;
  tipo_nombre: string | null;
  emisor: string | null;
  total: number | null;
};

export async function cargarCompraPorAutorizacion(numeroAutorizacion: string) {
  const resp = await api.post('/compras/cargar-por-autorizacion', { numero_autorizacion: numeroAutorizacion });
  return resp.data.data as CargaAutorizacionResultado;
}
