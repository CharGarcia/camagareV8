import React, { useCallback, useRef, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  RefreshControl,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RootStackParamList } from '../navigation/RootNavigator';
import { listarPedidos, PedidoListado } from '../api/pedidos';
import { useListadoPaginado } from '../hooks/useListadoPaginado';
import PieListado from '../components/PieListado';
import { useSerie } from '../pedidos/SerieContext';

const COLOR_ESTADO: Record<string, string> = {
  PENDIENTE: '#fd7e14',
  FACTURADO: '#198754',
  PROCESADO: '#198754',
  ANULADO: '#dc3545',
};

export default function PedidosListScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const { serie } = useSerie();
  const [buscar, setBuscar] = useState('');
  const buscarRef = useRef('');
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const {
    items: pedidos,
    total,
    cargando,
    refrescando,
    cargandoMas,
    error,
    cargar,
    cargarMas,
  } = useListadoPaginado<PedidoListado>(
    (page) => listarPedidos({ buscar: buscarRef.current, page }),
    'No se pudieron cargar los pedidos.'
  );

  useFocusEffect(
    useCallback(() => {
      cargar();
    }, [cargar])
  );

  function onBuscarChange(texto: string) {
    setBuscar(texto);
    buscarRef.current = texto;
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => cargar(), 350);
  }

  function nuevoPedido() {
    if (serie) {
      navigation.navigate('PedidoForm', undefined);
    } else {
      navigation.navigate('SeleccionSerie', { irANuevoPedido: true });
    }
  }

  return (
    <View style={styles.container}>
      <View style={styles.toolbar}>
        <TextInput
          style={styles.buscador}
          placeholder="Buscar por número o cliente..."
          value={buscar}
          onChangeText={onBuscarChange}
        />
        <TouchableOpacity onPress={nuevoPedido} style={styles.botonNuevo}>
          <Text style={styles.botonNuevoTexto}>+ Nuevo</Text>
        </TouchableOpacity>
      </View>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      {cargando ? (
        <ActivityIndicator size="large" color="#0d6efd" style={{ marginTop: 40 }} />
      ) : (
        <FlatList
          data={pedidos}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={{ padding: 16 }}
          refreshControl={<RefreshControl refreshing={refrescando} onRefresh={() => cargar(true)} />}
          onEndReached={cargarMas}
          onEndReachedThreshold={0.4}
          ListFooterComponent={<PieListado cargandoMas={cargandoMas} mostrados={pedidos.length} total={total} />}
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
    alignItems: 'center',
    gap: 8,
    padding: 16,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
  },
  buscador: {
    flex: 1,
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
    backgroundColor: '#fff',
    fontSize: 14,
  },
  botonNuevo: { backgroundColor: '#0d6efd', paddingHorizontal: 14, paddingVertical: 10, borderRadius: 8 },
  botonNuevoTexto: { color: '#fff', fontWeight: '600' },
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
