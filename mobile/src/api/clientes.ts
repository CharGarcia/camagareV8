import { api } from './client';

export type ClienteListado = {
  id: number;
  nombre: string;
  tipo_id: string;
  nombre_tipo_id: string | null;
  identificacion: string;
  telefono: string | null;
  email: string;
  direccion: string | null;
  provincia: string | null;
  ciudad: string | null;
  nombre_provincia: string | null;
  nombre_ciudad: string | null;
  status: number;
};

export async function listarClientes(params: { buscar?: string; page?: number } = {}) {
  const resp = await api.get('/clientes/listar', { params });
  return resp.data as { data: ClienteListado[]; meta: { total: number; total_pages: number; page: number } };
}

export async function obtenerCliente(id: number) {
  const resp = await api.get('/clientes/obtener', { params: { id } });
  return resp.data.data as ClienteListado;
}

export type ClienteInput = {
  nombre: string;
  tipo_id: string;
  identificacion: string;
  email: string;
  telefono?: string;
  direccion?: string;
  provincia?: string;
  ciudad?: string;
};

export async function crearCliente(input: ClienteInput) {
  const resp = await api.post('/clientes/crear', input);
  return resp.data.data as { id: number };
}

export async function actualizarCliente(id: number, input: ClienteInput) {
  const resp = await api.post('/clientes/actualizar', { id, ...input });
  return resp.data.data as { id: number };
}

export type TipoIdentificacion = { id: number; codigo: string; nombre: string };
export type Provincia = { codigo: string; nombre: string };
export type Ciudad = { codigo: string; nombre: string };

export async function obtenerCatalogosClientes() {
  const resp = await api.get('/clientes/catalogos');
  return resp.data.data as { tipos_id: TipoIdentificacion[]; provincias: Provincia[] };
}

export async function obtenerCiudadesPorProvincia(codProv: string) {
  const resp = await api.get('/clientes/ciudades', { params: { cod_prov: codProv } });
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
  const resp = await api.get('/clientes/consultar-sri', { params: { identificacion } });
  return resp.data.data as SriResultado;
}
