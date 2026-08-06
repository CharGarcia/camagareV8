import { api } from './client';

export type ResumenDashboard = {
  label_periodo: string;
  ventas_mes_actual: number;
  ventas_mes_anterior: number;
  compras_mes_actual: number;
  compras_mes_anterior: number;
};

export async function obtenerResumenDashboard() {
  const resp = await api.get('/dashboard/resumen');
  return resp.data.data as ResumenDashboard;
}
