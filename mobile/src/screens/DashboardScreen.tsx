import React, { useCallback, useState } from 'react';
import { ActivityIndicator, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { obtenerResumenDashboard, ResumenDashboard } from '../api/dashboard';
import { mensajeError } from '../api/client';
import SelectorLista from '../components/SelectorLista';

const MESES = [
  'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
  'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
];

function variacion(actual: number, anterior: number): { texto: string; positivo: boolean } | null {
  if (!anterior) return null;
  const pct = ((actual - anterior) / anterior) * 100;
  return { texto: `${pct >= 0 ? '+' : ''}${pct.toFixed(1)}% vs. período anterior`, positivo: pct >= 0 };
}

export default function DashboardScreen() {
  const anioActual = new Date().getFullYear();
  const mesActual = new Date().getMonth() + 1;

  const [anio, setAnio] = useState(anioActual);
  const [mes, setMes] = useState(mesActual);
  const [resumen, setResumen] = useState<ResumenDashboard | null>(null);
  const [cargando, setCargando] = useState(true);
  const [refrescando, setRefrescando] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const cargar = useCallback(async (a: number, m: number, esRefresh = false) => {
    esRefresh ? setRefrescando(true) : setCargando(true);
    setError(null);
    try {
      const data = await obtenerResumenDashboard({ anio: a, mes: m });
      setResumen(data);
    } catch (err) {
      setError(mensajeError(err, 'No se pudo cargar el resumen.'));
    } finally {
      esRefresh ? setRefrescando(false) : setCargando(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      cargar(anio, mes);
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [cargar, anio, mes])
  );

  const opcionesAnio = Array.from({ length: 5 }, (_, i) => anioActual - i).map((a) => ({ id: a, label: String(a) }));
  const opcionesMes = MESES.map((nombre, i) => ({ id: i + 1, label: nombre }));

  const varVentas = resumen ? variacion(resumen.ventas_mes_actual, resumen.ventas_mes_anterior) : null;
  const varCompras = resumen ? variacion(resumen.compras_mes_actual, resumen.compras_mes_anterior) : null;

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={{ padding: 16 }}
      refreshControl={<RefreshControl refreshing={refrescando} onRefresh={() => cargar(anio, mes, true)} />}
    >
      <View style={styles.filtros}>
        <View style={{ flex: 1 }}>
          <SelectorLista<number> label="Año" value={anio} opciones={opcionesAnio} onChange={(v) => v !== null && setAnio(v)} />
        </View>
        <View style={{ flex: 1 }}>
          <SelectorLista<number> label="Mes" value={mes} opciones={opcionesMes} onChange={(v) => v !== null && setMes(v)} />
        </View>
      </View>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      {cargando ? (
        <ActivityIndicator size="large" color="#0d6efd" style={{ marginTop: 40 }} />
      ) : resumen ? (
        <>
          <Text style={styles.periodo}>{resumen.label_periodo}</Text>

          <View style={[styles.card, styles.cardVentas]}>
            <Text style={styles.cardTitulo}>Ventas</Text>
            <Text style={styles.cardMonto}>${resumen.ventas_mes_actual.toFixed(2)}</Text>
            {varVentas ? (
              <Text style={[styles.variacion, varVentas.positivo ? styles.positivo : styles.negativo]}>
                {varVentas.texto}
              </Text>
            ) : null}
          </View>

          <View style={[styles.card, styles.cardCompras]}>
            <Text style={styles.cardTitulo}>Compras</Text>
            <Text style={styles.cardMonto}>${resumen.compras_mes_actual.toFixed(2)}</Text>
            {varCompras ? (
              <Text style={[styles.variacion, varCompras.positivo ? styles.positivo : styles.negativo]}>
                {varCompras.texto}
              </Text>
            ) : null}
          </View>

          <View style={[styles.card, styles.cardCxc]}>
            <Text style={styles.cardTitulo}>Cuentas por Cobrar</Text>
            <Text style={styles.cardMonto}>${resumen.cxc_total.toFixed(2)}</Text>
          </View>

          <View style={[styles.card, styles.cardCxp]}>
            <Text style={styles.cardTitulo}>Cuentas por Pagar</Text>
            <Text style={styles.cardMonto}>${resumen.cxp_total.toFixed(2)}</Text>
          </View>
        </>
      ) : (
        <Text style={styles.error}>Sin datos.</Text>
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f6f8' },
  error: { color: '#dc3545', textAlign: 'center', marginTop: 40 },
  filtros: { flexDirection: 'row', gap: 12, marginBottom: 16 },
  periodo: { fontSize: 13, color: '#777', fontWeight: '600', textTransform: 'uppercase', marginBottom: 12 },
  card: { borderRadius: 12, padding: 20, marginBottom: 14, elevation: 1 },
  cardVentas: { backgroundColor: '#e7f1ff' },
  cardCompras: { backgroundColor: '#fff4e5' },
  cardCxc: { backgroundColor: '#e6f7f5' },
  cardCxp: { backgroundColor: '#fdeaea' },
  cardTitulo: { fontSize: 14, fontWeight: '700', color: '#444', marginBottom: 6 },
  cardMonto: { fontSize: 28, fontWeight: '800', color: '#222' },
  variacion: { fontSize: 12, fontWeight: '600', marginTop: 6 },
  positivo: { color: '#198754' },
  negativo: { color: '#dc3545' },
});
