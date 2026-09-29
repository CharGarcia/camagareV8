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
import { ProveedorListado, listarProveedores } from '../api/proveedores';
import { useListadoPaginado } from '../hooks/useListadoPaginado';
import PieListado from '../components/PieListado';

export default function ProveedoresListScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const [buscar, setBuscar] = useState('');
  const buscarRef = useRef('');
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const {
    items: proveedores,
    total,
    cargando,
    refrescando,
    cargandoMas,
    error,
    cargar,
    cargarMas,
  } = useListadoPaginado<ProveedorListado>(
    (page) => listarProveedores({ buscar: buscarRef.current, page }),
    'No se pudieron cargar los proveedores.'
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
          placeholder="Buscar por razón social o identificación..."
          value={buscar}
          onChangeText={onBuscarChange}
        />
        <TouchableOpacity onPress={() => navigation.navigate('ProveedorForm', undefined)} style={styles.botonNuevo}>
          <Text style={styles.botonNuevoTexto}>+ Nuevo</Text>
        </TouchableOpacity>
      </View>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      {cargando ? (
        <ActivityIndicator size="large" color="#0d6efd" style={{ marginTop: 40 }} />
      ) : (
        <FlatList
          data={proveedores}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={{ padding: 16 }}
          refreshControl={<RefreshControl refreshing={refrescando} onRefresh={() => cargar(true)} />}
          onEndReached={cargarMas}
          onEndReachedThreshold={0.4}
          ListFooterComponent={<PieListado cargandoMas={cargandoMas} mostrados={proveedores.length} total={total} />}
          ListEmptyComponent={<Text style={styles.vacio}>No hay proveedores todavía.</Text>}
          renderItem={({ item }) => (
            <TouchableOpacity style={styles.card} onPress={() => navigation.navigate('ProveedorForm', { id: item.id })}>
              <View style={{ flex: 1 }}>
                <Text style={styles.nombre} numberOfLines={1}>
                  {item.razon_social}
                </Text>
                <Text style={styles.identificacion}>
                  {item.nombre_tipo_id ?? item.tipo_id_proveedor} · {item.identificacion}
                </Text>
                {item.telefono ? <Text style={styles.dato}>{item.telefono}</Text> : null}
              </View>
              <View style={[styles.badge, item.status ? styles.badgeActivo : styles.badgeInactivo]}>
                <Text style={[styles.badgeTexto, item.status ? styles.badgeTextoActivo : styles.badgeTextoInactivo]}>
                  {item.status ? 'Activo' : 'Inactivo'}
                </Text>
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
  nombre: { fontSize: 16, fontWeight: '600' },
  identificacion: { fontSize: 13, color: '#777', marginTop: 2 },
  dato: { fontSize: 13, color: '#777', marginTop: 2 },
  badge: { borderWidth: 1, borderRadius: 20, paddingHorizontal: 10, paddingVertical: 4 },
  badgeActivo: { backgroundColor: '#19875422', borderColor: '#198754' },
  badgeInactivo: { backgroundColor: '#6c757d22', borderColor: '#6c757d' },
  badgeTexto: { fontSize: 12, fontWeight: '700' },
  badgeTextoActivo: { color: '#198754' },
  badgeTextoInactivo: { color: '#6c757d' },
});
