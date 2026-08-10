import { api } from './client';

export type OrdenListado = {
  id: number;
  numero_orden: string;
  fecha_servicio: string;
  equipo_descripcion: string;
  cliente_nombre: string;
  cliente_identificacion: string;
  estado: string;
  total: string;
  tipo_documento: string | null;
  numero_documento: string | null;
};

export async function listarOrdenes(params: { buscar?: string; page?: number } = {}) {
  const resp = await api.get('/servicio-externo/listar', { params });
  return resp.data as { data: OrdenListado[]; meta: { total: number; total_pages: number; page: number } };
}

export type OrdenDetalleLinea = {
  id: number;
  tipo_linea: 'producto' | 'servicio';
  id_producto: number | null;
  producto_codigo: string | null;
  descripcion: string;
  cantidad: string;
  precio_unitario: string;
  descuento: string;
  porcentaje_iva: string;
  valor_iva: string;
  total_linea: string;
};

export type OrdenDetalle = OrdenListado & {
  equipo_marca: string | null;
  equipo_modelo: string | null;
  equipo_serie: string | null;
  direccion_servicio: string | null;
  descripcion_trabajo: string | null;
  observaciones: string | null;
  subtotal: string;
  iva: string;
  detalles: OrdenDetalleLinea[];
};

export async function obtenerOrden(id: number) {
  const resp = await api.get('/servicio-externo/obtener', { params: { id } });
  return resp.data.data as OrdenDetalle;
}

export type PuntoEmision = { id: number; id_establecimiento: number; codigo_punto: string; cod_establecimiento: string };
export type FormaPago = { codigo: string; nombre: string };
export type Bodega = { id: number; nombre: string };
export type TarifaIva = { id: number; porcentaje_iva: string; codigo: string };
export type UnidadMedida = { id: number; nombre: string };

export async function obtenerCatalogos() {
  const resp = await api.get('/servicio-externo/catalogos');
  return resp.data.data as {
    puntos: PuntoEmision[];
    formas_pago: FormaPago[];
    bodegas: Bodega[];
    tarifas_iva: TarifaIva[];
    unidades: UnidadMedida[];
  };
}

export async function obtenerSiguienteSecuencial(idPuntoEmision: number) {
  const resp = await api.get('/servicio-externo/siguiente-secuencial', { params: { id_punto_emision: idPuntoEmision } });
  return resp.data.data as { formateado: string };
}

export type ClienteBusqueda = { id: number; identificacion: string; nombre: string; direccion?: string | null };

export async function buscarClientes(q: string) {
  const resp = await api.get('/servicio-externo/buscar-clientes', { params: { q } });
  return resp.data.data as ClienteBusqueda[];
}

export type ProductoBusqueda = {
  id: number;
  codigo: string;
  nombre: string;
  precio_base: string | number;
  tarifa_iva: number;
  stock_actual?: number;
  controla_stock?: boolean;
};

export async function buscarProductos(q: string, idBodega?: number) {
  const resp = await api.get('/servicio-externo/buscar-productos', { params: { q, id_bodega: idBodega } });
  return resp.data.data as ProductoBusqueda[];
}

export type LineaInput = {
  tipo_linea: 'producto' | 'servicio';
  id_producto?: number;
  descripcion: string;
  cantidad: number;
  precio_unitario: number;
  descuento?: number;
  porcentaje_iva?: number;
  id_tarifa_iva?: number;
  id_bodega?: number;
};

export type OrdenInput = {
  id_punto_emision: number;
  secuencial: string;
  id_cliente: number;
  equipo_descripcion: string;
  equipo_marca?: string;
  equipo_modelo?: string;
  equipo_serie?: string;
  direccion_servicio?: string;
  fecha_servicio: string;
  descripcion_trabajo?: string;
  observaciones?: string;
  id_bodega?: number;
  detalles: LineaInput[];
};

export async function crearOrden(input: OrdenInput) {
  const resp = await api.post('/servicio-externo/crear', input);
  return resp.data.data as { id: number };
}

export async function generarDocumento(idOrden: number, tipo: 'FACTURA' | 'RECIBO', formaPago: string, idBodega?: number) {
  const resp = await api.post('/servicio-externo/generar-documento', {
    id_orden: idOrden,
    tipo,
    forma_pago: formaPago,
    id_bodega: idBodega,
  });
  return resp.data.data as { tipo: string; id_documento: number; numero_documento: string };
}
