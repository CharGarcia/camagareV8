import React, { useCallback, useRef, useState } from 'react';
import { ActivityIndicator, FlatList, RefreshControl, StyleSheet, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RootStackParamList } from '../navigation/RootNavigator';
import { ConsignacionPendiente, listarPendientesEntrega } from '../api/entregas';
import { useListadoPaginado } from '../hooks/useListadoPaginado';
import PieListado from '../components/PieListado';

export default function EntregasListScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const [buscar, setBuscar] = useState('');
  const buscarRef = useRef('');
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const {
    items: items,
    total,
    cargando,
    refrescando,
    cargandoMas,
    error,
    cargar,
    cargarMas,
  } = useListadoPaginado<ConsignacionPendiente>(
    (page) => listarPendientesEntrega({ buscar: buscarRef.current, page }),
    'No se pudieron cargar las entregas pendientes.'
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

  return (
    <View style={styles.container}>
      <View style={styles.toolbar}>
        <TextInput
          style={styles.buscador}
          placeholder="Buscar por número o cliente..."
          value={buscar}
          onChangeText={onBuscarChange}
        />
      </View>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      {cargando ? (
        <ActivityIndicator size="large" color="#0d6efd" style={{ marginTop: 40 }} />
      ) : (
        <FlatList
          data={items}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={{ padding: 16 }}
          refreshControl={<RefreshControl refreshing={refrescando} onRefresh={() => cargar(true)} />}
          onEndReached={cargarMas}
          onEndReachedThreshold={0.4}
          ListFooterComponent={<PieListado cargandoMas={cargandoMas} mostrados={items.length} total={total} />}
          ListEmptyComponent={<Text style={styles.vacio}>No hay entregas pendientes.</Text>}
          renderItem={({ item }) => (
            <TouchableOpacity style={styles.card} onPress={() => navigation.navigate('Entrega', { id: item.id })}>
              <View style={{ flex: 1 }}>
                <Text style={styles.numero}>{item.serie}-{item.secuencial}</Text>
                <Text style={styles.cliente} numberOfLines={1}>
                  {item.cliente_nombre}
                </Text>
                {item.cliente_direccion ? (
                  <Text style={styles.direccion} numberOfLines={1}>
                    {item.cliente_direccion}
                  </Text>
                ) : null}
                {item.fecha_entrega ? (
                  <Text style={styles.fecha}>
                    Entrega: {String(item.fecha_entrega).slice(0, 10)}
                    {item.hora_entrega_desde ? ` · ${String(item.hora_entrega_desde).slice(0, 5)}` : ''}
                    {item.hora_entrega_hasta ? ` - ${String(item.hora_entrega_hasta).slice(0, 5)}` : ''}
                  </Text>
                ) : null}
              </View>
              <View style={styles.badge}>
                <Text style={styles.badgeTexto}>Pendiente</Text>
              </View>
            </TouchableOpacity>
          )}
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
  direccion: { fontSize: 13, color: '#666', marginTop: 2 },
  fecha: { fontSize: 12, color: '#0d6efd', marginTop: 4, fontWeight: '600' },
  badge: {
    borderWidth: 1,
    borderColor: '#fd7e14',
    backgroundColor: '#fd7e1422',
    borderRadius: 20,
    paddingHorizontal: 10,
    paddingVertical: 4,
  },
  badgeTexto: { fontSize: 11, fontWeight: '700', color: '#fd7e14' },
});
