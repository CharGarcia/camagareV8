import { api } from './client';

export type EstadisticasReporte = {
  total_base_0: number;
  total_base_iva: number;
  total_iva: number;
  gran_total: number;
  total_documentos: number;
};

export type ReporteMes = {
  mes: string; // 'YYYY-MM'
  cantidad_facturas?: number;
  cantidad_comprobantes?: number;
  base_0: number;
  base_iva: number;
  valor_iva: number;
  total: number;
};

export type ReporteVentasCliente = {
  id_cliente: number;
  cliente_ruc: string;
  cliente_nombre: string;
  cantidad_facturas: number;
  base_0: number;
  base_iva: number;
  valor_iva: number;
  total: number;
};

export type ReporteComprasProveedor = {
  id_proveedor: number;
  proveedor_ruc: string;
  proveedor_nombre: string;
  cantidad_comprobantes: number;
  base_0: number;
  base_iva: number;
  valor_iva: number;
  total: number;
};

export async function obtenerReporteVentas(params: { fecha_desde?: string; fecha_hasta?: string } = {}) {
  const resp = await api.get('/reporte-ventas/resumen', { params });
  return resp.data.data as {
    rango: { desde: string; hasta: string };
    estadisticas: EstadisticasReporte;
    por_mes: ReporteMes[];
    por_cliente: ReporteVentasCliente[];
  };
}

export async function obtenerReporteCompras(params: { fecha_desde?: string; fecha_hasta?: string } = {}) {
  const resp = await api.get('/reporte-compras/resumen', { params });
  return resp.data.data as {
    rango: { desde: string; hasta: string };
    estadisticas: EstadisticasReporte;
    por_mes: ReporteMes[];
    por_proveedor: ReporteComprasProveedor[];
  };
}
