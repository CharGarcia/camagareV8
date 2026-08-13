import { api } from './client';

export type ResumenDashboard = {
  label_periodo: string;
  anio: number;
  mes: number;
  ventas_mes_actual: number;
  ventas_mes_anterior: number;
  compras_mes_actual: number;
  compras_mes_anterior: number;
  cxc_total: number;
  cxp_total: number;
};

export async function obtenerResumenDashboard(params: { anio?: number; mes?: number } = {}) {
  const resp = await api.get('/dashboard/resumen', { params });
  return resp.data.data as ResumenDashboard;
}
