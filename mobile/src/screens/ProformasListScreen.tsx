import React, { useCallback, useRef, useState } from 'react';
import { ActivityIndicator, FlatList, RefreshControl, StyleSheet, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RootStackParamList } from '../navigation/RootNavigator';
import { ESTADO_PROFORMA, listarProformas, ProformaListado } from '../api/proformas';
import { useListadoPaginado } from '../hooks/useListadoPaginado';
import PieListado from '../components/PieListado';

export default function ProformasListScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const [buscar, setBuscar] = useState('');
  const buscarRef = useRef('');
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const {
    items: proformas,
    total,
    cargando,
    refrescando,
    cargandoMas,
    error,
    cargar,
    cargarMas,
  } = useListadoPaginado<ProformaListado>(
    (page) => listarProformas({ buscar: buscarRef.current, page }),
    'No se pudieron cargar las proformas.'
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
        <TouchableOpacity onPress={() => navigation.navigate('ProformaForm', undefined)} style={styles.botonNuevo}>
          <Text style={styles.botonNuevoTexto}>+ Nueva</Text>
        </TouchableOpacity>
      </View>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      {cargando ? (
        <ActivityIndicator size="large" color="#0d6efd" style={{ marginTop: 40 }} />
      ) : (
        <FlatList
          data={proformas}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={{ padding: 16 }}
          refreshControl={<RefreshControl refreshing={refrescando} onRefresh={() => cargar(true)} />}
          onEndReached={cargarMas}
          onEndReachedThreshold={0.4}
          ListFooterComponent={<PieListado cargandoMas={cargandoMas} mostrados={proformas.length} total={total} />}
          ListEmptyComponent={<Text style={styles.vacio}>No hay proformas todavía.</Text>}
          renderItem={({ item }) => {
            const estado = ESTADO_PROFORMA[item.estado] ?? { label: item.estado, color: '#6c757d' };
            const color = estado.color;
            return (
              <TouchableOpacity style={styles.card} onPress={() => navigation.navigate('ProformaForm', { id: item.id })}>
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
                    <Text style={[styles.badgeTexto, { color }]}>{estado.label}</Text>
                  </View>
                  {item.estado_correo === 'enviado' ? <Text style={styles.correo}>Correo enviado</Text> : null}
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
  correo: { fontSize: 11, color: '#198754', marginTop: 4 },
});
