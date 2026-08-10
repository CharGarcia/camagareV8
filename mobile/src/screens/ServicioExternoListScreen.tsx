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
import { OrdenListado, listarOrdenes } from '../api/servicioExterno';
import { mensajeError } from '../api/client';

const ESTADO_LABEL: Record<string, string> = { borrador: 'Borrador', facturado: 'Facturado', anulado: 'Anulado' };
const ESTADO_COLOR: Record<string, string> = { borrador: '#6c757d', facturado: '#198754', anulado: '#dc3545' };

export default function ServicioExternoListScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const [ordenes, setOrdenes] = useState<OrdenListado[]>([]);
  const [buscar, setBuscar] = useState('');
  const [cargando, setCargando] = useState(true);
  const [refrescando, setRefrescando] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const cargar = useCallback(async (texto: string, esRefresh = false) => {
    esRefresh ? setRefrescando(true) : setCargando(true);
    setError(null);
    try {
      const resp = await listarOrdenes({ buscar: texto, page: 1 });
      setOrdenes(resp.data);
    } catch (err) {
      setError(mensajeError(err, 'No se pudieron cargar las órdenes.'));
    } finally {
      esRefresh ? setRefrescando(false) : setCargando(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      cargar(buscar);
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [cargar])
  );

  function onBuscarChange(texto: string) {
    setBuscar(texto);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => cargar(texto), 350);
  }

  return (
    <View style={styles.container}>
      <View style={styles.toolbar}>
        <TextInput
          style={styles.buscador}
          placeholder="Buscar por cliente, equipo, orden..."
          value={buscar}
          onChangeText={onBuscarChange}
        />
        <TouchableOpacity onPress={() => navigation.navigate('ServicioExternoForm')} style={styles.botonNuevo}>
          <Text style={styles.botonNuevoTexto}>+ Nueva orden</Text>
        </TouchableOpacity>
      </View>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      {cargando ? (
        <ActivityIndicator size="large" color="#0d6efd" style={{ marginTop: 40 }} />
      ) : (
        <FlatList
          data={ordenes}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={{ padding: 16 }}
          refreshControl={<RefreshControl refreshing={refrescando} onRefresh={() => cargar(buscar, true)} />}
          ListEmptyComponent={<Text style={styles.vacio}>No hay órdenes de servicio externo todavía.</Text>}
          renderItem={({ item }) => {
            const fecha = item.fecha_servicio ? new Date(item.fecha_servicio).toLocaleDateString('es-EC') : '';
            const color = ESTADO_COLOR[item.estado] ?? '#6c757d';
            return (
              <TouchableOpacity
                style={styles.card}
                onPress={() => navigation.navigate('ServicioExternoDetail', { id: item.id })}
              >
                <View style={{ flex: 1 }}>
                  <Text style={styles.numero}>{item.numero_orden}</Text>
                  <Text style={styles.equipo} numberOfLines={1}>
                    {item.equipo_descripcion}
                  </Text>
                  <Text style={styles.subtexto}>
                    {item.cliente_nombre} · {fecha}
                  </Text>
                </View>
                <View style={{ alignItems: 'flex-end' }}>
                  <Text style={styles.total}>${Number(item.total).toFixed(2)}</Text>
                  <View style={[styles.badge, { borderColor: color, backgroundColor: color + '22' }]}>
                    <Text style={[styles.badgeTexto, { color }]}>{ESTADO_LABEL[item.estado] ?? item.estado}</Text>
                  </View>
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
    gap: 10,
    elevation: 1,
  },
  numero: { fontSize: 13, fontWeight: '700', color: '#0d6efd' },
  equipo: { fontSize: 15, fontWeight: '600', marginTop: 2 },
  subtexto: { fontSize: 12, color: '#777', marginTop: 2 },
  total: { fontSize: 15, fontWeight: '700' },
  badge: { borderWidth: 1, borderRadius: 20, paddingHorizontal: 8, paddingVertical: 2, marginTop: 4 },
  badgeTexto: { fontSize: 10, fontWeight: '700' },
});
