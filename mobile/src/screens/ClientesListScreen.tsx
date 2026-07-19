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
import { ClienteListado, listarClientes } from '../api/clientes';
import { mensajeError } from '../api/client';

export default function ClientesListScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const [clientes, setClientes] = useState<ClienteListado[]>([]);
  const [buscar, setBuscar] = useState('');
  const [cargando, setCargando] = useState(true);
  const [refrescando, setRefrescando] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const cargar = useCallback(async (texto: string, esRefresh = false) => {
    esRefresh ? setRefrescando(true) : setCargando(true);
    setError(null);
    try {
      const resp = await listarClientes({ buscar: texto, page: 1 });
      setClientes(resp.data);
    } catch (err) {
      setError(mensajeError(err, 'No se pudieron cargar los clientes.'));
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
          placeholder="Buscar por nombre o identificación..."
          value={buscar}
          onChangeText={onBuscarChange}
        />
        <TouchableOpacity onPress={() => navigation.navigate('ClienteForm', undefined)} style={styles.botonNuevo}>
          <Text style={styles.botonNuevoTexto}>+ Nuevo</Text>
        </TouchableOpacity>
      </View>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      {cargando ? (
        <ActivityIndicator size="large" color="#0d6efd" style={{ marginTop: 40 }} />
      ) : (
        <FlatList
          data={clientes}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={{ padding: 16 }}
          refreshControl={<RefreshControl refreshing={refrescando} onRefresh={() => cargar(buscar, true)} />}
          ListEmptyComponent={<Text style={styles.vacio}>No hay clientes todavía.</Text>}
          renderItem={({ item }) => (
            <TouchableOpacity style={styles.card} onPress={() => navigation.navigate('ClienteForm', { id: item.id })}>
              <View style={{ flex: 1 }}>
                <Text style={styles.nombre} numberOfLines={1}>
                  {item.nombre}
                </Text>
                <Text style={styles.identificacion}>
                  {item.nombre_tipo_id ?? item.tipo_id} · {item.identificacion}
                </Text>
                {item.telefono ? <Text style={styles.dato}>{item.telefono}</Text> : null}
              </View>
              <View style={[styles.badge, item.status === 1 ? styles.badgeActivo : styles.badgeInactivo]}>
                <Text style={[styles.badgeTexto, item.status === 1 ? styles.badgeTextoActivo : styles.badgeTextoInactivo]}>
                  {item.status === 1 ? 'Activo' : 'Inactivo'}
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
