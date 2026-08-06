import React, { useCallback, useState } from 'react';
import { ActivityIndicator, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { obtenerResumenDashboard, ResumenDashboard } from '../api/dashboard';
import { mensajeError } from '../api/client';

function variacion(actual: number, anterior: number): { texto: string; positivo: boolean } | null {
  if (!anterior) return null;
  const pct = ((actual - anterior) / anterior) * 100;
  return { texto: `${pct >= 0 ? '+' : ''}${pct.toFixed(1)}% vs. mes anterior`, positivo: pct >= 0 };
}

export default function DashboardScreen() {
  const [resumen, setResumen] = useState<ResumenDashboard | null>(null);
  const [cargando, setCargando] = useState(true);
  const [refrescando, setRefrescando] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const cargar = useCallback(async (esRefresh = false) => {
    esRefresh ? setRefrescando(true) : setCargando(true);
    setError(null);
    try {
      const data = await obtenerResumenDashboard();
      setResumen(data);
    } catch (err) {
      setError(mensajeError(err, 'No se pudo cargar el resumen.'));
    } finally {
      esRefresh ? setRefrescando(false) : setCargando(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      cargar();
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [cargar])
  );

  if (cargando) {
    return <ActivityIndicator size="large" color="#0d6efd" style={{ marginTop: 40 }} />;
  }
  if (error || !resumen) {
    return <Text style={styles.error}>{error ?? 'Sin datos.'}</Text>;
  }

  const varVentas = variacion(resumen.ventas_mes_actual, resumen.ventas_mes_anterior);
  const varCompras = variacion(resumen.compras_mes_actual, resumen.compras_mes_anterior);

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={{ padding: 16 }}
      refreshControl={<RefreshControl refreshing={refrescando} onRefresh={() => cargar(true)} />}
    >
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
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f6f8' },
  error: { color: '#dc3545', textAlign: 'center', marginTop: 40 },
  periodo: { fontSize: 13, color: '#777', fontWeight: '600', textTransform: 'uppercase', marginBottom: 12 },
  card: { borderRadius: 12, padding: 20, marginBottom: 14, elevation: 1 },
  cardVentas: { backgroundColor: '#e7f1ff' },
  cardCompras: { backgroundColor: '#fff4e5' },
  cardTitulo: { fontSize: 14, fontWeight: '700', color: '#444', marginBottom: 6 },
  cardMonto: { fontSize: 28, fontWeight: '800', color: '#222' },
  variacion: { fontSize: 12, fontWeight: '600', marginTop: 6 },
  positivo: { color: '#198754' },
  negativo: { color: '#dc3545' },
});
