import React, { useCallback, useState } from 'react';
import { ActivityIndicator, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { obtenerReporteVentas, ReporteMes, ReporteVentasCliente } from '../api/reportes';
import { mensajeError } from '../api/client';
import SelectorFechaHora from '../components/SelectorFechaHora';

function aFecha(iso: string): Date {
  return new Date(iso + 'T00:00:00');
}
function aIso(d: Date): string {
  return d.toISOString().slice(0, 10);
}
function nombreMes(mesIso: string): string {
  const [anio, mes] = mesIso.split('-');
  const nombres = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
  return `${nombres[Number(mes) - 1] ?? mes} ${anio}`;
}

export default function ReporteVentasScreen() {
  const hoy = new Date();
  const hace6Meses = new Date(hoy.getFullYear(), hoy.getMonth() - 5, 1);

  const [desde, setDesde] = useState<Date>(hace6Meses);
  const [hasta, setHasta] = useState<Date>(hoy);

  const [cargando, setCargando] = useState(true);
  const [refrescando, setRefrescando] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [estadisticas, setEstadisticas] = useState<{
    total_base_0: number;
    total_base_iva: number;
    total_iva: number;
    gran_total: number;
    total_documentos: number;
  } | null>(null);
  const [porMes, setPorMes] = useState<ReporteMes[]>([]);
  const [porCliente, setPorCliente] = useState<ReporteVentasCliente[]>([]);

  const cargar = useCallback(
    async (esRefresh = false) => {
      esRefresh ? setRefrescando(true) : setCargando(true);
      setError(null);
      try {
        const data = await obtenerReporteVentas({ fecha_desde: aIso(desde), fecha_hasta: aIso(hasta) });
        setEstadisticas(data.estadisticas);
        setPorMes(data.por_mes);
        setPorCliente(data.por_cliente);
      } catch (err) {
        setError(mensajeError(err, 'No se pudo cargar el reporte.'));
      } finally {
        esRefresh ? setRefrescando(false) : setCargando(false);
      }
    },
    [desde, hasta]
  );

  useFocusEffect(
    useCallback(() => {
      cargar();
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [cargar])
  );

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={{ padding: 16 }}
      refreshControl={<RefreshControl refreshing={refrescando} onRefresh={() => cargar(true)} />}
    >
      <View style={styles.filtroFila}>
        <SelectorFechaHora
          label="Desde"
          mode="date"
          value={desde}
          permiteQuitar={false}
          onChange={(d) => d && setDesde(d)}
        />
        <View style={{ width: 12 }} />
        <SelectorFechaHora
          label="Hasta"
          mode="date"
          value={hasta}
          permiteQuitar={false}
          onChange={(d) => d && setHasta(d)}
        />
      </View>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      {cargando ? (
        <ActivityIndicator size="large" color="#0d6efd" style={{ marginTop: 30 }} />
      ) : (
        <>
          {estadisticas ? (
            <View style={styles.card}>
              <Text style={styles.seccionTitulo}>Totales del período</Text>
              <Fila label="Base 0%" valor={estadisticas.total_base_0} />
              <Fila label="Base gravada" valor={estadisticas.total_base_iva} />
              <Fila label="IVA" valor={estadisticas.total_iva} />
              <Fila label="Total general" valor={estadisticas.gran_total} destacado />
              <View style={styles.filaTotal}>
                <Text style={styles.filaLabel}>Documentos</Text>
                <Text style={styles.filaValor}>{estadisticas.total_documentos}</Text>
              </View>
            </View>
          ) : null}

          <View style={styles.card}>
            <Text style={styles.seccionTitulo}>Por mes</Text>
            {porMes.length === 0 ? (
              <Text style={styles.vacio}>Sin datos en el rango seleccionado.</Text>
            ) : (
              porMes.map((m) => (
                <View key={m.mes} style={styles.filaLista}>
                  <Text style={styles.itemNombre}>{nombreMes(m.mes)}</Text>
                  <Text style={styles.itemTotal}>${Number(m.total).toFixed(2)}</Text>
                </View>
              ))
            )}
          </View>

          <View style={styles.card}>
            <Text style={styles.seccionTitulo}>Por cliente</Text>
            {porCliente.length === 0 ? (
              <Text style={styles.vacio}>Sin datos en el rango seleccionado.</Text>
            ) : (
              porCliente.map((c) => (
                <View key={c.id_cliente} style={styles.filaLista}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.itemNombre} numberOfLines={1}>
                      {c.cliente_nombre}
                    </Text>
                    <Text style={styles.itemSub}>{c.cliente_ruc}</Text>
                  </View>
                  <Text style={styles.itemTotal}>${Number(c.total).toFixed(2)}</Text>
                </View>
              ))
            )}
          </View>
        </>
      )}
    </ScrollView>
  );
}

function Fila({ label, valor, destacado = false }: { label: string; valor: number; destacado?: boolean }) {
  return (
    <View style={styles.filaTotal}>
      <Text style={destacado ? styles.filaLabelDestacado : styles.filaLabel}>{label}</Text>
      <Text style={destacado ? styles.filaValorDestacado : styles.filaValor}>${valor.toFixed(2)}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f6f8' },
  filtroFila: { flexDirection: 'row', marginBottom: 16 },
  error: { color: '#dc3545', textAlign: 'center', marginBottom: 12 },
  card: { backgroundColor: '#fff', borderRadius: 10, padding: 14, marginBottom: 12, elevation: 1 },
  seccionTitulo: { fontSize: 13, fontWeight: '700', color: '#666', textTransform: 'uppercase', marginBottom: 8 },
  filaTotal: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 4 },
  filaLabel: { fontSize: 13, color: '#666' },
  filaValor: { fontSize: 13, color: '#333' },
  filaLabelDestacado: { fontSize: 14, fontWeight: '700', color: '#333' },
  filaValorDestacado: { fontSize: 14, fontWeight: '700', color: '#0d6efd' },
  vacio: { color: '#888', textAlign: 'center', paddingVertical: 12, fontSize: 13 },
  filaLista: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 8,
    borderTopWidth: 1,
    borderTopColor: '#f0f0f0',
  },
  itemNombre: { fontSize: 13, fontWeight: '600', color: '#333' },
  itemSub: { fontSize: 11, color: '#888', marginTop: 1 },
  itemTotal: { fontSize: 13, fontWeight: '700', color: '#0d6efd' },
});
