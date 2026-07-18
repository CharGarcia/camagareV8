import { api } from './client';

export type SubmoduloMenu = {
  id_submodulo: number;
  nombre_submodulo: string;
  ruta: string;
  icono_submodulo: string;
};

export type ModuloMenu = {
  id_modulo: number;
  nombre_modulo: string;
  icono_modulo: string;
  submodulos: SubmoduloMenu[];
};

export async function obtenerMenu() {
  const resp = await api.get('/menu');
  return resp.data.data as ModuloMenu[];
}
