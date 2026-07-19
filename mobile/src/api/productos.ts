import { api } from './client';
import { ASSET_BASE_URL } from './config';

export function urlImagenProducto(imagen: string | null): string | null {
  if (!imagen) return null;
  return `${ASSET_BASE_URL}/${imagen}`;
}

export type ProductoListado = {
  id: number;
  codigo: string;
  nombre: string;
  precio_base: string;
  pvp: string;
  status: number;
  nombre_categoria: string | null;
  nombre_medida: string | null;
  imagen: string | null;
};

export async function listarProductos(params: { buscar?: string; page?: number } = {}) {
  const resp = await api.get('/productos/listar', { params });
  return resp.data as { data: ProductoListado[]; meta: { total: number; total_pages: number; page: number } };
}

export type ProductoDetalle = {
  id: number;
  codigo: string;
  codigo_auxiliar: string | null;
  nombre: string;
  tipo_produccion: string;
  tarifa_iva: number;
  precio_base: string;
  status: number;
  id_categoria: number | null;
  id_marca: number | null;
  id_medida: number | null;
  nombre_categoria: string | null;
  nombre_marca: string | null;
  nombre_medida: string | null;
  imagen: string | null;
  stock_actual_general: number;
};

export async function obtenerProducto(id: number) {
  const resp = await api.get('/productos/obtener', { params: { id } });
  return resp.data.data as ProductoDetalle;
}

export type ProductoInput = {
  codigo?: string;
  codigo_auxiliar?: string;
  nombre: string;
  tipo_produccion?: '01' | '02';
  tarifa_iva?: number;
  precio_base?: number;
  id_categoria?: number;
  id_marca?: number;
  id_medida?: number;
  imagen?: string;
};

export async function crearProducto(input: ProductoInput) {
  const resp = await api.post('/productos/crear', input);
  return resp.data.data as { id: number };
}

export async function actualizarProducto(id: number, input: ProductoInput) {
  const resp = await api.post('/productos/actualizar', { id, ...input });
  return resp.data.data as { id: number };
}

export type Categoria = { id: number; nombre: string };
export type Marca = { id: number; nombre: string };
export type UnidadMedida = { id: number; id_tipo: number; nombre: string; abreviatura: string };
export type TarifaIva = { id: number; nombre: string; porcentaje: number };
export type MedidaDefault = { id_tipo_medida: number; id_medida: number } | null;

export async function obtenerCatalogosProductos() {
  const resp = await api.get('/productos/catalogos');
  return resp.data.data as {
    categorias: Categoria[];
    marcas: Marca[];
    unidades: UnidadMedida[];
    tarifas_iva: TarifaIva[];
    medida_default: MedidaDefault;
  };
}

export async function obtenerSiguienteCodigo(tipoProduccion: '01' | '02' = '01') {
  const resp = await api.get('/productos/siguiente-codigo', { params: { tipo: tipoProduccion } });
  return resp.data.data as { codigo: string };
}

export async function subirImagenProducto(imagenBase64: string) {
  const resp = await api.post('/productos/subir-imagen', { imagen_base64: imagenBase64 });
  return resp.data.data as { path: string };
}
