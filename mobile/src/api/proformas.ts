// SDK 54 movió downloadAsync/cacheDirectory a la API "legacy" — ver AGENTS.md.
import * as FileSystem from 'expo-file-system/legacy';
import { api } from './client';
import { API_BASE_URL } from './config';
import { getAccessToken } from '../auth/tokenStore';
import type { PaginaListado } from '../hooks/useListadoPaginado';

export type EstadoProforma = 'borrador' | 'aprobada' | 'rechazada' | 'convertida' | 'anulada';

export type ProformaListado = {
  id: number;
  establecimiento: string;
  punto_emision: string;
  secuencial: string;
  fecha_emision: string;
  cliente_nombre: string | null;
  cliente_ruc: string | null;
  vendedor_nombre: string | null;
  importe_total: string;
  estado: EstadoProforma;
  estado_correo: string | null;
};

export async function listarProformas(params: { buscar?: string; page?: number }) {
  const resp = await api.get('/proformas/listar', { params });
  return resp.data as PaginaListado<ProformaListado>;
}

export type ProformaCabecera = ProformaListado & {
  id_establecimiento: number;
  id_punto_emision: number;
  id_cliente: number;
  id_vendedor: number | null;
  cliente_email: string | null;
  cliente_direccion: string | null;
  dias_vigencia: number;
  observaciones: string | null;
  total_sin_impuestos: string;
  total_descuento: string;
  aprobacion_cliente_fecha: string | null;
  aprobacion_cliente_comentario: string | null;
};

export type ProformaDetalleLinea = {
  id: number;
  id_producto: number | null;
  descripcion: string;
  producto_codigo: string | null;
  codigo_principal: string | null;
  cantidad: string;
  precio_unitario: string;
  descuento: string | null;
  precio_total_sin_impuesto: string;
  impuestos: { codigo_impuesto: string; codigo_porcentaje: string; tarifa: string; base_imponible: string; valor: string }[];
};

export async function obtenerProforma(id: number) {
  const resp = await api.get('/proformas/obtener', { params: { id } });
  return resp.data.data as {
    cabecera: ProformaCabecera;
    detalles: ProformaDetalleLinea[];
    info_adicional: { nombre: string; valor: string }[];
  };
}

export type SerieProforma = {
  id_establecimiento: number;
  establecimiento: string;
  puntos_emision: { id_punto_emision: number; punto_emision: string }[];
};

export async function obtenerSeriesProforma() {
  const resp = await api.get('/proformas/series');
  return resp.data.data as { establecimientos: SerieProforma[] };
}

export async function obtenerSecuencialProforma(idPuntoEmision: number, fecha?: string) {
  const resp = await api.get('/proformas/secuencial', { params: { id_punto_emision: idPuntoEmision, fecha } });
  return resp.data.data as { secuencial: number; formateado: string };
}

export async function obtenerCatalogosProforma() {
  const resp = await api.get('/proformas/catalogos');
  return resp.data.data as {
    vendedores: { id: number; nombre: string }[];
    dias_vigencia: number;
    decimales_precio: number;
    decimales_cantidad: number;
  };
}

export type ClienteProforma = { id: number; nombre: string; identificacion: string; email: string | null };

export async function buscarClientesProforma(q: string) {
  const resp = await api.get('/proformas/buscar-clientes', { params: { q } });
  return resp.data.data as ClienteProforma[];
}

export type ProductoProforma = {
  id: number;
  codigo: string;
  nombre: string;
  precio_base: string;
  pvp: string;
  porcentaje_iva_final?: string | null;
};

export async function buscarProductosProforma(q: string) {
  const resp = await api.get('/proformas/buscar-productos', { params: { q } });
  return resp.data.data as ProductoProforma[];
}

export type ProformaInput = {
  fecha_emision: string;
  id_cliente: number;
  id_vendedor?: number;
  dias_vigencia: number;
  observaciones?: string;
  /** id_detalle: línea ya guardada (en edición) — el servidor conserva lo que la app no edita. */
  detalles: { id_detalle?: number; id_producto: number | null; cantidad: number; precio_unitario: number; descuento: number }[];
};

export async function crearProforma(input: ProformaInput & { id_establecimiento: number; id_punto_emision: number }) {
  const resp = await api.post('/proformas/crear', input);
  return resp.data.data as { id: number; numero: string };
}

export async function actualizarProforma(id: number, input: ProformaInput) {
  const resp = await api.post('/proformas/actualizar', { ...input, id });
  return resp.data.data as { id: number };
}

export async function cambiarEstadoProforma(id: number, estado: EstadoProforma) {
  const resp = await api.post('/proformas/cambiar-estado', { id, estado });
  return resp.data.data as { id: number; estado: EstadoProforma };
}

export async function duplicarProforma(id: number) {
  const resp = await api.post('/proformas/duplicar', { id });
  return resp.data.data as { id: number; numero: string };
}

export async function enviarCorreoProforma(id: number, correos: string, adjuntarFicha: boolean) {
  const resp = await api.post('/proformas/enviar-correo', { id, correos, adjuntar_ficha: adjuntarFicha });
  return resp.data.data as { mensaje: string };
}

export async function convertirProformaAFactura(id: number, forzar = false) {
  const resp = await api.post('/proformas/convertir-factura', { id, forzar });
  return resp.data.data as {
    id_factura: number;
    requiere_confirmacion: boolean;
    mensaje: string;
    stock_insuficiente: boolean;
    faltantes: { producto: string; disponible: number; requerido: number }[];
  };
}

export async function convertirProformaAPedido(id: number, forzar = false) {
  const resp = await api.post('/proformas/convertir-pedido', { id, forzar });
  return resp.data.data as {
    id_pedido: number;
    numero: string;
    requiere_confirmacion: boolean;
    mensaje: string;
    items_sin_producto: string[];
  };
}

/** Descarga el PDF al almacenamiento local de la app y devuelve el URI del archivo. */
export async function descargarPdfProforma(id: number): Promise<string> {
  const token = await getAccessToken();
  const destino = `${FileSystem.cacheDirectory}proforma_${id}.pdf`;
  const resultado = await FileSystem.downloadAsync(`${API_BASE_URL}/proformas/pdf?id=${id}`, destino, {
    headers: token ? { Authorization: `Bearer ${token}` } : undefined,
  });
  if (resultado.status !== 200) {
    throw new Error('No se pudo descargar el PDF de la proforma.');
  }
  return resultado.uri;
}

/** Etiqueta y color de cada estado (mismos colores que los badges de la web). */
export const ESTADO_PROFORMA: Record<string, { label: string; color: string }> = {
  borrador: { label: 'Borrador', color: '#6c757d' },
  aprobada: { label: 'Aprobada', color: '#198754' },
  rechazada: { label: 'Rechazada', color: '#fd7e14' },
  convertida: { label: 'Convertida', color: '#0d6efd' },
  anulada: { label: 'Anulada', color: '#dc3545' },
};
