import React, { useCallback, useRef, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Image,
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
import { listarProductos, ProductoListado, urlImagenProducto } from '../api/productos';
import { mensajeError } from '../api/client';

export default function ProductosListScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const [productos, setProductos] = useState<ProductoListado[]>([]);
  const [buscar, setBuscar] = useState('');
  const [cargando, setCargando] = useState(true);
  const [refrescando, setRefrescando] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const cargar = useCallback(async (texto: string, esRefresh = false) => {
    esRefresh ? setRefrescando(true) : setCargando(true);
    setError(null);
    try {
      const resp = await listarProductos({ buscar: texto, page: 1 });
      setProductos(resp.data);
    } catch (err) {
      setError(mensajeError(err, 'No se pudieron cargar los productos.'));
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
          placeholder="Buscar por código o nombre..."
          value={buscar}
          onChangeText={onBuscarChange}
        />
        <TouchableOpacity onPress={() => navigation.navigate('ProductoForm', undefined)} style={styles.botonNuevo}>
          <Text style={styles.botonNuevoTexto}>+ Nuevo</Text>
        </TouchableOpacity>
      </View>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      {cargando ? (
        <ActivityIndicator size="large" color="#0d6efd" style={{ marginTop: 40 }} />
      ) : (
        <FlatList
          data={productos}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={{ padding: 16 }}
          refreshControl={<RefreshControl refreshing={refrescando} onRefresh={() => cargar(buscar, true)} />}
          ListEmptyComponent={<Text style={styles.vacio}>No hay productos todavía.</Text>}
          renderItem={({ item }) => {
            const urlImg = urlImagenProducto(item.imagen);
            return (
              <TouchableOpacity style={styles.card} onPress={() => navigation.navigate('ProductoForm', { id: item.id })}>
                {urlImg ? (
                  <Image source={{ uri: urlImg }} style={styles.imagen} />
                ) : (
                  <View style={[styles.imagen, styles.imagenVacia]}>
                    <Text style={styles.imagenVaciaTexto}>Sin foto</Text>
                  </View>
                )}
                <View style={{ flex: 1 }}>
                  <Text style={styles.nombre} numberOfLines={1}>
                    {item.nombre}
                  </Text>
                  <Text style={styles.codigo}>
                    {item.codigo}
                    {item.nombre_categoria ? ` · ${item.nombre_categoria}` : ''}
                  </Text>
                  <Text style={styles.precio}>${Number(item.pvp ?? item.precio_base).toFixed(2)}</Text>
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
    padding: 10,
    marginBottom: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    elevation: 1,
  },
  imagen: { width: 52, height: 52, borderRadius: 8, backgroundColor: '#eee' },
  imagenVacia: { justifyContent: 'center', alignItems: 'center' },
  imagenVaciaTexto: { fontSize: 9, color: '#999', textAlign: 'center' },
  nombre: { fontSize: 15, fontWeight: '600' },
  codigo: { fontSize: 12, color: '#777', marginTop: 2 },
  precio: { fontSize: 14, fontWeight: '700', color: '#0d6efd', marginTop: 2 },
});
