import React, { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  RefreshControl,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RootStackParamList } from '../navigation/RootNavigator';
import { listarPedidos, PedidoListado } from '../api/pedidos';
import { mensajeError } from '../api/client';
import { useSerie } from '../pedidos/SerieContext';

const COLOR_ESTADO: Record<string, string> = {
  PENDIENTE: '#fd7e14',
  FACTURADO: '#198754',
  PROCESADO: '#198754',
  ANULADO: '#dc3545',
};

export default function PedidosListScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const { serie, cambiarSerie } = useSerie();
  const [pedidos, setPedidos] = useState<PedidoListado[]>([]);
  const [cargando, setCargando] = useState(true);
  const [refrescando, setRefrescando] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const cargar = useCallback(async (esRefresh = false) => {
    esRefresh ? setRefrescando(true) : setCargando(true);
    setError(null);
    try {
      const resp = await listarPedidos({ page: 1 });
      setPedidos(resp.data);
    } catch (err) {
      setError(mensajeError(err, 'No se pudieron cargar los pedidos.'));
    } finally {
      esRefresh ? setRefrescando(false) : setCargando(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      cargar();
    }, [cargar])
  );

  return (
    <View style={styles.container}>
      <View style={styles.toolbar}>
        <TouchableOpacity onPress={() => navigation.navigate('PedidoForm', undefined)} style={styles.botonNuevo}>
          <Text style={styles.botonNuevoTexto}>+ Nuevo pedido</Text>
        </TouchableOpacity>
      </View>

      {serie ? (
        <TouchableOpacity
          onPress={() => {
            cambiarSerie();
            navigation.replace('SeleccionSerie');
          }}
          style={styles.serieBar}
        >
          <Text style={styles.serieTexto}>
            Serie: {serie.establecimiento}-{serie.punto_emision}
          </Text>
          <Text style={styles.serieCambiar}>Cambiar</Text>
        </TouchableOpacity>
      ) : null}

      {error ? <Text style={styles.error}>{error}</Text> : null}

      {cargando ? (
        <ActivityIndicator size="large" color="#0d6efd" style={{ marginTop: 40 }} />
      ) : (
        <FlatList
          data={pedidos}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={{ padding: 16 }}
          refreshControl={<RefreshControl refreshing={refrescando} onRefresh={() => cargar(true)} />}
          ListEmptyComponent={<Text style={styles.vacio}>No hay pedidos todavía.</Text>}
          renderItem={({ item }) => {
            const color = COLOR_ESTADO[(item.estado || '').toUpperCase()] ?? '#6c757d';
            return (
              <TouchableOpacity
                style={styles.card}
                onPress={() => navigation.navigate('PedidoForm', { id: item.id })}
              >
                <View style={{ flex: 1 }}>
                  <Text style={styles.numero}>{item.numero_pedido ?? `#${item.id}`}</Text>
                  <Text style={styles.cliente} numberOfLines={1}>
                    {item.cliente_nombre}
                  </Text>
                  <Text style={styles.fecha}>{item.fecha_pedido}</Text>
                </View>
                <View style={[styles.badge, { backgroundColor: color + '22', borderColor: color }]}>
                  <Text style={[styles.badgeTexto, { color }]}>{item.estado}</Text>
                </View>
              </TouchableOpacity>
            );
          }}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f6f8' },
  toolbar: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 16,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
  },
  botonNuevo: { backgroundColor: '#0d6efd', paddingHorizontal: 14, paddingVertical: 8, borderRadius: 8 },
  botonNuevoTexto: { color: '#fff', fontWeight: '600' },
  salir: { color: '#dc3545', fontWeight: '600' },
  serieBar: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 8,
    backgroundColor: '#e7f1ff',
  },
  serieTexto: { color: '#0d6efd', fontWeight: '600' },
  serieCambiar: { color: '#0d6efd', fontSize: 12, textDecorationLine: 'underline' },
  error: { color: '#dc3545', textAlign: 'center', marginTop: 12 },
  vacio: { color: '#888', textAlign: 'center', marginTop: 40 },
  card: {
    backgroundColor: '#fff',
    borderRadius: 10,
    padding: 14,
    marginBottom: 10,
    flexDirection: 'row',
    alignItems: 'center',
    elevation: 1,
  },
  numero: { fontSize: 12, color: '#888' },
  cliente: { fontSize: 16, fontWeight: '600', marginTop: 2 },
  fecha: { fontSize: 13, color: '#777', marginTop: 2 },
  badge: { borderWidth: 1, borderRadius: 20, paddingHorizontal: 10, paddingVertical: 4 },
  badgeTexto: { fontSize: 12, fontWeight: '700' },
});
