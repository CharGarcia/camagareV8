import React, { useCallback, useRef, useState } from 'react';
import { ActivityIndicator, FlatList, RefreshControl, StyleSheet, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RootStackParamList } from '../navigation/RootNavigator';
import { FacturaListado, listarFacturas } from '../api/facturasVenta';
import { mensajeError } from '../api/client';

const COLOR_ESTADO: Record<string, string> = {
  BORRADOR: '#fd7e14',
  AUTORIZADO: '#198754',
  APROBADO: '#198754',
  DEVUELTA: '#dc3545',
  NO_AUTORIZADO: '#dc3545',
  ANULADO: '#6c757d',
};

export default function FacturasVentaListScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const [facturas, setFacturas] = useState<FacturaListado[]>([]);
  const [buscar, setBuscar] = useState('');
  const [cargando, setCargando] = useState(true);
  const [refrescando, setRefrescando] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const cargar = useCallback(async (texto: string, esRefresh = false) => {
    esRefresh ? setRefrescando(true) : setCargando(true);
    setError(null);
    try {
      const resp = await listarFacturas({ buscar: texto, page: 1 });
      setFacturas(resp.data);
    } catch (err) {
      setError(mensajeError(err, 'No se pudieron cargar las facturas.'));
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
          placeholder="Buscar por número o cliente..."
          value={buscar}
          onChangeText={onBuscarChange}
        />
        <TouchableOpacity onPress={() => navigation.navigate('FacturaVentaForm', undefined)} style={styles.botonNuevo}>
          <Text style={styles.botonNuevoTexto}>+ Nueva</Text>
        </TouchableOpacity>
      </View>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      {cargando ? (
        <ActivityIndicator size="large" color="#0d6efd" style={{ marginTop: 40 }} />
      ) : (
        <FlatList
          data={facturas}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={{ padding: 16 }}
          refreshControl={<RefreshControl refreshing={refrescando} onRefresh={() => cargar(buscar, true)} />}
          ListEmptyComponent={<Text style={styles.vacio}>No hay facturas todavía.</Text>}
          renderItem={({ item }) => {
            const color = COLOR_ESTADO[(item.estado || '').toUpperCase()] ?? '#6c757d';
            return (
              <TouchableOpacity style={styles.card} onPress={() => navigation.navigate('FacturaVentaForm', { id: item.id })}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.numero}>
                    {item.establecimiento}-{item.punto_emision}-{item.secuencial}
                  </Text>
                  <Text style={styles.cliente} numberOfLines={1}>
                    {item.cliente_nombre}
                  </Text>
                  <Text style={styles.fecha}>{String(item.fecha_emision).slice(0, 10)}</Text>
                </View>
                <View style={{ alignItems: 'flex-end' }}>
                  <Text style={styles.total}>${Number(item.importe_total).toFixed(2)}</Text>
                  <View style={[styles.badge, { backgroundColor: color + '22', borderColor: color }]}>
                    <Text style={[styles.badgeTexto, { color }]}>{item.estado}</Text>
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
    elevation: 1,
  },
  numero: { fontSize: 12, color: '#888' },
  cliente: { fontSize: 15, fontWeight: '600', marginTop: 2 },
  fecha: { fontSize: 13, color: '#777', marginTop: 2 },
  total: { fontSize: 15, fontWeight: '700', color: '#0d6efd' },
  badge: { borderWidth: 1, borderRadius: 20, paddingHorizontal: 8, paddingVertical: 3, marginTop: 4 },
  badgeTexto: { fontSize: 10, fontWeight: '700' },
});
