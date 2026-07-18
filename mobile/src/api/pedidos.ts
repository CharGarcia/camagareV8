import { api } from './client';

export type PedidoListado = {
  id: number;
  numero_pedido: string | null;
  fecha_pedido: string;
  cliente_nombre: string;
  estado: string;
  observaciones: string | null;
};

export async function listarPedidos(params: { buscar?: string; page?: number } = {}) {
  const resp = await api.get('/pedidos/listar', { params });
  return resp.data as { data: PedidoListado[]; meta: { total: number; total_pages: number; page: number } };
}

export async function obtenerPedido(id: number) {
  const resp = await api.get('/pedidos/obtener', { params: { id } });
  return resp.data.data as {
    cabecera: Record<string, unknown> & { id: number; cliente_nombre: string; estado: string };
    detalles: Array<Record<string, unknown> & { producto_nombre: string; cantidad: string; precio_unitario: string; total: string }>;
  };
}

export type DetallePedidoInput = {
  id_producto: number;
  cantidad: number;
  precio_unitario: number;
  subtotal: number;
  iva: number;
  total: number;
};

export type CabeceraPedidoInput = {
  id_cliente: number;
  fecha_pedido: string;
  observaciones?: string;
  fecha_entrega: string;
  hora_inicial_entrega: string;
  hora_maxima_entrega: string;
  id_responsable_entrega?: number;
  id_establecimiento: number;
  id_punto_emision: number;
  establecimiento: string;
  punto_emision: string;
  secuencial: string;
};

export async function crearPedido(cabecera: CabeceraPedidoInput, detalles: DetallePedidoInput[]) {
  const resp = await api.post('/pedidos/crear', { cabecera, detalles });
  return resp.data.data as { id: number };
}

export type ClienteBusqueda = { id: number; identificacion: string; nombre: string };

export async function buscarClientes(q: string) {
  const resp = await api.get('/pedidos/buscar-clientes', { params: { q } });
  return resp.data.data as ClienteBusqueda[];
}

export type ProductoBusqueda = { id: number; codigo: string; nombre: string; precio_base: string | number | null };

export async function buscarProductos(q: string) {
  const resp = await api.get('/pedidos/buscar-productos', { params: { q } });
  return resp.data.data as ProductoBusqueda[];
}

export type PuntoEmision = { id_punto_emision: number; punto_emision: string };
export type Establecimiento = {
  id_establecimiento: number;
  establecimiento: string;
  direccion: string;
  puntos_emision: PuntoEmision[];
};

export async function obtenerSeries() {
  const resp = await api.get('/pedidos/series');
  return resp.data.data as Establecimiento[];
}

export type Secuencial = {
  secuencial: number;
  formateado: string;
  es_gap: boolean;
  configurado: boolean;
  detalle: string;
};

export async function obtenerSecuencial(idPuntoEmision: number) {
  const resp = await api.get('/pedidos/secuencial', { params: { id_punto_emision: idPuntoEmision } });
  return resp.data.data as Secuencial;
}

export type ResponsableTraslado = { id: number; nombre: string; identificacion: string | null; email: string | null };

export async function obtenerResponsables() {
  const resp = await api.get('/pedidos/responsables');
  return resp.data.data as ResponsableTraslado[];
}
