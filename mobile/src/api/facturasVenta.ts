// SDK 54 movió downloadAsync/cacheDirectory a la API "legacy" (la API nueva por
// defecto usa un modelo distinto basado en File/Directory) — ver AGENTS.md.
import * as FileSystem from 'expo-file-system/legacy';
import { api } from './client';
import { API_BASE_URL } from './config';
import { getAccessToken } from '../auth/tokenStore';

export type FacturaListado = {
  id: number;
  establecimiento: string;
  punto_emision: string;
  secuencial: string;
  fecha_emision: string;
  importe_total: string;
  estado: string;
  cliente_nombre: string;
  cliente_ruc: string;
  /** importe - cobros - retenciones - notas de crédito. */
  saldo_pendiente: number;
};

export async function listarFacturas(params: { buscar?: string; page?: number } = {}) {
  const resp = await api.get('/facturas-venta/listar', { params });
  return resp.data as { data: FacturaListado[]; meta: { total: number; total_pages: number; page: number } };
}

export type FacturaCabecera = FacturaListado & {
  id_cliente: number;
  id_establecimiento: number;
  id_punto_emision: number;
  total_sin_impuestos: string;
  total_descuento: string;
  total_ice: string | null;
  propina: string;
  dias_credito: number;
  observaciones: string | null;
  id_vendedor: number | null;
  vendedor_nombre: string | null;
  tipo_ambiente: string;
  clave_acceso: string | null;
  fecha_autorizacion: string | null;
};

export type FacturaDetalleLinea = {
  id: number;
  id_producto: number | null;
  id_bodega: number | null;
  producto_nombre: string;
  producto_codigo: string | null;
  cantidad: string;
  precio_unitario: string;
  precio_total_sin_impuesto: string;
  impuestos: { codigo_impuesto: string; codigo_porcentaje: string; tarifa: string; base_imponible: string; valor: string }[];
};

export type FacturaPago = {
  forma_pago: string;
  nombre_forma_pago: string;
  total: string;
};

/** Desglose de subtotal/IVA por tarifa, igual que el bloque de totales de la web. */
export type TotalPorTarifa = {
  codigo_porcentaje: string;
  nombre_tarifa_iva: string;
  porcentaje: number;
  base: number;
  iva: number;
};

export async function obtenerFactura(id: number) {
  const resp = await api.get('/facturas-venta/obtener', { params: { id } });
  return resp.data.data as {
    cabecera: FacturaCabecera;
    detalles: FacturaDetalleLinea[];
    pagos: FacturaPago[];
    totales_iva: TotalPorTarifa[];
    /** importe - cobros ya registrados - retenciones - notas de crédito. */
    saldo_pendiente: number;
  };
}

export type EstablecimientoFactura = {
  id_establecimiento: number;
  establecimiento: string;
  direccion: string;
  id_forma_pago_sri_def: number | null;
  valor_limite_consumidor_final: number;
  puntos_emision: { id_punto_emision: number; punto_emision: string }[];
};

export async function obtenerSeries() {
  const resp = await api.get('/facturas-venta/series');
  return resp.data.data as { establecimientos: EstablecimientoFactura[]; id_punto_emision_favorito: number | null };
}

export async function obtenerSecuencial(idPuntoEmision: number) {
  const resp = await api.get('/facturas-venta/secuencial', { params: { id_punto_emision: idPuntoEmision } });
  return resp.data.data as { secuencial: number; formateado: string };
}

export type Bodega = { id: number; nombre: string; es_default: boolean };
export type VendedorFactura = { id: number; nombre: string; identificacion: string | null; correo: string | null };
export type FormaPagoSri = { id: number; codigo: string; nombre: string; status: number };

export async function obtenerCatalogosFacturas() {
  const resp = await api.get('/facturas-venta/catalogos');
  return resp.data.data as { bodegas: Bodega[]; vendedores: VendedorFactura[]; formas_pago_sri: FormaPagoSri[] };
}

export type FacturaInput = {
  fecha_emision: string;
  id_cliente: number;
  id_establecimiento: number;
  id_punto_emision: number;
  establecimiento: string;
  punto_emision: string;
  secuencial: string;
  dias_credito?: number;
  observaciones?: string;
  id_vendedor?: number;
  id_bodega?: number;
  forma_pago: string;
  detalles: { id_producto: number; cantidad: number }[];
};

export async function crearFactura(input: FacturaInput) {
  const resp = await api.post('/facturas-venta/crear', input);
  return resp.data.data as { id: number };
}

/** Solo funciona mientras la factura sigue en 'borrador'; no permite cambiar la serie. */
export async function actualizarFactura(id: number, input: Omit<FacturaInput, 'id_establecimiento' | 'id_punto_emision' | 'establecimiento' | 'punto_emision' | 'secuencial'>) {
  const resp = await api.post('/facturas-venta/actualizar', { id, ...input });
  return resp.data.data as { id: number };
}

export type ResultadoEnvioSri = {
  enviado_ok: boolean;
  estado: string | null;
  mensaje: string;
  numero_autorizacion: string;
  fecha_autorizacion: string;
  errores: string[];
};

/**
 * El envío al SRI firma, transmite y espera la autorización (SriEnvioService,
 * sin modificar) — puede tardar hasta ~20s en el peor caso. Se usa un timeout
 * propio, más generoso que el del cliente por defecto (15s), para no cortar la
 * espera antes de que el backend responda.
 */
export async function enviarFacturaSri(id: number) {
  const resp = await api.post('/facturas-venta/enviar-sri', { id }, { timeout: 45000 });
  return resp.data.data as ResultadoEnvioSri;
}

export type FormaCobro = { id: number; nombre: string; tipo: string };

export async function obtenerFormasCobro() {
  const resp = await api.get('/facturas-venta/formas-cobro');
  return resp.data.data as FormaCobro[];
}

export type EstablecimientoIngreso = {
  id_establecimiento: number;
  establecimiento: string;
  puntos_emision: { id_punto_emision: number; punto_emision: string }[];
};

/** Series (establecimiento-punto de emisión) disponibles para el Ingreso del cobro; no se
 * limitan al establecimiento de la factura (el Ingreso es un documento propio). */
export async function obtenerSeriesIngreso() {
  const resp = await api.get('/facturas-venta/series-ingreso');
  return resp.data.data as { establecimientos: EstablecimientoIngreso[]; id_punto_emision_favorito: number | null };
}

export type TipoOperacionBancaria = 'TRANSFERENCIA' | 'DEPOSITO' | 'DEBITO' | 'CHEQUE';

export type CobroInput = {
  id_factura: number;
  monto: number;
  id_forma_cobro: number;
  observaciones?: string;
  /** Serie del ingreso; si se omite el servidor la resuelve solo (favorito o establecimiento de la factura). */
  id_punto_emision?: number;
  /** Fecha del ingreso (YYYY-MM-DD); por defecto hoy. */
  fecha_emision?: string;
  /** Solo si la forma de cobro elegida es de tipo BANCO. */
  tipo_operacion_bancaria?: TipoOperacionBancaria;
  numero_referencia?: string;
  /** Obligatoria si tipo_operacion_bancaria === 'CHEQUE' (fecha en que se podrá cobrar). */
  fecha_cobro?: string;
};

/** Solo funciona si la factura está 'autorizado' y tiene saldo pendiente > 0. */
export async function registrarCobro(input: CobroInput) {
  const resp = await api.post('/facturas-venta/cobrar', input);
  return resp.data.data as { id_ingreso: number };
}

/** Descarga el PDF al almacenamiento local de la app y devuelve el URI del archivo. */
export async function descargarPdfFactura(id: number): Promise<string> {
  const token = await getAccessToken();
  const destino = `${FileSystem.cacheDirectory}factura_${id}.pdf`;
  const resultado = await FileSystem.downloadAsync(`${API_BASE_URL}/facturas-venta/pdf?id=${id}`, destino, {
    headers: token ? { Authorization: `Bearer ${token}` } : undefined,
  });
  if (resultado.status !== 200) {
    throw new Error('No se pudo descargar el PDF de la factura.');
  }
  return resultado.uri;
}
