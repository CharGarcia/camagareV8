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
import { CompraListado, listarCompras } from '../api/compras';
import { mensajeError } from '../api/client';

export default function ComprasListScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const [compras, setCompras] = useState<CompraListado[]>([]);
  const [buscar, setBuscar] = useState('');
  const [cargando, setCargando] = useState(true);
  const [refrescando, setRefrescando] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const cargar = useCallback(async (texto: string, esRefresh = false) => {
    esRefresh ? setRefrescando(true) : setCargando(true);
    setError(null);
    try {
      const resp = await listarCompras({ buscar: texto, page: 1 });
      setCompras(resp.data);
    } catch (err) {
      setError(mensajeError(err, 'No se pudieron cargar las compras.'));
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
          placeholder="Buscar por proveedor, RUC, número..."
          value={buscar}
          onChangeText={onBuscarChange}
        />
        <TouchableOpacity onPress={() => navigation.navigate('CompraCargar')} style={styles.botonNuevo}>
          <Text style={styles.botonNuevoTexto}>+ Nueva compra</Text>
        </TouchableOpacity>
      </View>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      {cargando ? (
        <ActivityIndicator size="large" color="#0d6efd" style={{ marginTop: 40 }} />
      ) : (
        <FlatList
          data={compras}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={{ padding: 16 }}
          refreshControl={<RefreshControl refreshing={refrescando} onRefresh={() => cargar(buscar, true)} />}
          ListEmptyComponent={<Text style={styles.vacio}>No hay compras registradas todavía.</Text>}
          renderItem={({ item }) => {
            const numero = `${item.establecimiento_prov}-${item.punto_emision_prov}-${item.secuencial_prov}`;
            const fecha = item.fecha_emision ? new Date(item.fecha_emision).toLocaleDateString('es-EC') : '';
            const saldo = Math.max(
              0,
              Number(item.importe_total) - Number(item.total_pagado) - Number(item.total_nc) - Number(item.total_retencion)
            );
            return (
              <TouchableOpacity style={styles.card} onPress={() => navigation.navigate('CompraDetail', { id: item.id })}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.proveedor} numberOfLines={1}>
                    {item.proveedor_nombre}
                  </Text>
                  <Text style={styles.subtexto}>
                    {item.proveedor_ruc} · {item.tipo_comprobante_nombre ?? 'Comprobante'} {numero}
                  </Text>
                  <Text style={styles.subtexto}>{fecha}</Text>
                </View>
                <View style={{ alignItems: 'flex-end' }}>
                  <Text style={styles.total}>${Number(item.importe_total).toFixed(2)}</Text>
                  {saldo > 0.01 ? (
                    <View style={styles.badgeSaldo}>
                      <Text style={styles.badgeSaldoTexto}>Saldo: ${saldo.toFixed(2)}</Text>
                    </View>
                  ) : (
                    <View style={styles.badgePagado}>
                      <Text style={styles.badgePagadoTexto}>Pagado</Text>
                    </View>
                  )}
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
    gap: 12,
    elevation: 1,
  },
  proveedor: { fontSize: 15, fontWeight: '600' },
  subtexto: { fontSize: 12, color: '#777', marginTop: 2 },
  total: { fontSize: 15, fontWeight: '700', color: '#0d6efd' },
  badgeSaldo: { borderWidth: 1, borderColor: '#fd7e14', backgroundColor: '#fd7e1222', borderRadius: 20, paddingHorizontal: 8, paddingVertical: 3, marginTop: 4 },
  badgeSaldoTexto: { fontSize: 10, fontWeight: '700', color: '#fd7e14' },
  badgePagado: { borderWidth: 1, borderColor: '#198754', backgroundColor: '#19875422', borderRadius: 20, paddingHorizontal: 8, paddingVertical: 3, marginTop: 4 },
  badgePagadoTexto: { fontSize: 10, fontWeight: '700', color: '#198754' },
});
