import { api } from './client';

export type ProveedorListado = {
  id: number;
  razon_social: string;
  nombre_comercial: string | null;
  tipo_id_proveedor: string;
  nombre_tipo_id: string | null;
  identificacion: string;
  telefono: string | null;
  email: string | null;
  direccion: string | null;
  provincia: string | null;
  ciudad: string | null;
  nombre_provincia: string | null;
  nombre_ciudad: string | null;
  status: boolean;
};

export async function listarProveedores(params: { buscar?: string; page?: number } = {}) {
  const resp = await api.get('/proveedores/listar', { params });
  return resp.data as { data: ProveedorListado[]; meta: { total: number; total_pages: number; page: number } };
}

export async function obtenerProveedor(id: number) {
  const resp = await api.get('/proveedores/obtener', { params: { id } });
  return resp.data.data as ProveedorListado;
}

export type ProveedorInput = {
  razon_social: string;
  tipo_id_proveedor: string;
  identificacion: string;
  nombre_comercial?: string;
  email?: string;
  telefono?: string;
  direccion?: string;
  provincia?: string;
  ciudad?: string;
};

export async function crearProveedor(input: ProveedorInput) {
  const resp = await api.post('/proveedores/crear', input);
  return resp.data.data as { id: number };
}

export async function actualizarProveedor(id: number, input: ProveedorInput) {
  const resp = await api.post('/proveedores/actualizar', { id, ...input });
  return resp.data.data as { id: number };
}

export type TipoIdentificacion = { id: number; codigo: string; nombre: string };
export type Provincia = { codigo: string; nombre: string };
export type Ciudad = { codigo: string; nombre: string };

export async function obtenerCatalogosProveedores() {
  const resp = await api.get('/proveedores/catalogos');
  return resp.data.data as { tipos_id: TipoIdentificacion[]; provincias: Provincia[] };
}

export async function obtenerCiudadesPorProvincia(codProv: string) {
  const resp = await api.get('/proveedores/ciudades', { params: { cod_prov: codProv } });
  return resp.data.data as Ciudad[];
}

export type SriResultado = {
  ok: boolean;
  data?: {
    nombre: string;
    nombre_comercial?: string;
    direccion?: string;
    cod_prov?: string | null;
    cod_ciudad?: string | null;
    telefono?: string;
    mail?: string;
  };
  error?: string;
  source?: string;
};

export async function consultarSri(identificacion: string) {
  const resp = await api.get('/proveedores/consultar-sri', { params: { identificacion } });
  return resp.data.data as SriResultado;
}
