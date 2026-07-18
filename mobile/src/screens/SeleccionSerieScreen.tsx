import React, { useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RootStackParamList } from '../navigation/RootNavigator';
import { useSerie } from '../pedidos/SerieContext';
import { useAuth } from '../auth/AuthContext';
import { Establecimiento } from '../api/pedidos';
import { mensajeError } from '../api/client';

type FilaSerie = {
  key: string;
  est: Establecimiento;
  punto: Establecimiento['puntos_emision'][number];
};

export default function SeleccionSerieScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const { establecimientos, cargarEstablecimientos, seleccionarSerie } = useSerie();
  const { logout } = useAuth();
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [eligiendo, setEligiendo] = useState<string | null>(null);

  useEffect(() => {
    (async () => {
      try {
        await cargarEstablecimientos();
      } catch (err) {
        setError(mensajeError(err, 'No se pudieron cargar las series disponibles.'));
      } finally {
        setCargando(false);
      }
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const filas: FilaSerie[] = establecimientos.flatMap((est) =>
    est.puntos_emision.map((punto) => ({
      key: `${est.id_establecimiento}-${punto.id_punto_emision}`,
      est,
      punto,
    }))
  );

  async function elegir(fila: FilaSerie) {
    setEligiendo(fila.key);
    try {
      await seleccionarSerie(fila.est, fila.punto);
      navigation.replace('PedidosList');
    } catch (err) {
      setError(mensajeError(err, 'No se pudo seleccionar la serie.'));
    } finally {
      setEligiendo(null);
    }
  }

  if (cargando) {
    return (
      <View style={styles.centrado}>
        <ActivityIndicator size="large" color="#0d6efd" />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <Text style={styles.titulo}>¿Con qué serie vas a trabajar?</Text>
      <Text style={styles.subtitulo}>Establecimiento y punto de emisión para numerar tus pedidos.</Text>
      {error ? <Text style={styles.error}>{error}</Text> : null}
      <FlatList
        data={filas}
        keyExtractor={(item) => item.key}
        contentContainerStyle={{ paddingBottom: 24 }}
        ListEmptyComponent={<Text style={styles.vacio}>No hay puntos de emisión activos configurados.</Text>}
        renderItem={({ item }) => (
          <TouchableOpacity style={styles.item} onPress={() => elegir(item)} disabled={eligiendo !== null}>
            <View style={{ flex: 1 }}>
              <Text style={styles.itemSerie}>
                {item.est.establecimiento}-{item.punto.punto_emision}
              </Text>
              {item.est.direccion ? <Text style={styles.itemDireccion}>{item.est.direccion}</Text> : null}
            </View>
            {eligiendo === item.key ? <ActivityIndicator color="#0d6efd" /> : null}
          </TouchableOpacity>
        )}
      />
      <TouchableOpacity onPress={() => logout()} style={styles.salir}>
        <Text style={styles.salirTexto}>Cerrar sesión</Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f6f8', paddingTop: 60, paddingHorizontal: 16 },
  centrado: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  titulo: { fontSize: 20, fontWeight: '700' },
  subtitulo: { fontSize: 13, color: '#777', marginTop: 4, marginBottom: 16 },
  error: { color: '#dc3545', marginBottom: 12 },
  vacio: { color: '#888', textAlign: 'center', marginTop: 40 },
  item: {
    backgroundColor: '#fff',
    borderRadius: 10,
    padding: 16,
    marginBottom: 10,
    flexDirection: 'row',
    alignItems: 'center',
    elevation: 1,
  },
  itemSerie: { fontSize: 18, fontWeight: '700', color: '#0d6efd' },
  itemDireccion: { fontSize: 13, color: '#777', marginTop: 2 },
  salir: { alignItems: 'center', paddingVertical: 16 },
  salirTexto: { color: '#dc3545', fontWeight: '600' },
});
