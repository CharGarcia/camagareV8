import React, { useCallback, useState } from 'react';
import { ActivityIndicator, FlatList, RefreshControl, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RootStackParamList } from '../navigation/RootNavigator';
import { ConsignacionPendiente, listarPendientesEntrega } from '../api/entregas';
import { mensajeError } from '../api/client';

export default function EntregasListScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const [items, setItems] = useState<ConsignacionPendiente[]>([]);
  const [cargando, setCargando] = useState(true);
  const [refrescando, setRefrescando] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const cargar = useCallback(async (esRefresh = false) => {
    esRefresh ? setRefrescando(true) : setCargando(true);
    setError(null);
    try {
      const resp = await listarPendientesEntrega({ page: 1 });
      setItems(resp.data);
    } catch (err) {
      setError(mensajeError(err, 'No se pudieron cargar las entregas pendientes.'));
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
      {error ? <Text style={styles.error}>{error}</Text> : null}

      {cargando ? (
        <ActivityIndicator size="large" color="#0d6efd" style={{ marginTop: 40 }} />
      ) : (
        <FlatList
          data={items}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={{ padding: 16 }}
          refreshControl={<RefreshControl refreshing={refrescando} onRefresh={() => cargar(true)} />}
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
