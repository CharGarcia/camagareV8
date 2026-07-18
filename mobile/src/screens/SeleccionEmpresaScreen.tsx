import React, { useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useAuth } from '../auth/AuthContext';
import { Empresa } from '../api/auth';
import { mensajeError } from '../api/client';

export default function SeleccionEmpresaScreen() {
  const { listarEmpresas, seleccionarEmpresa, logout } = useAuth();
  const [empresas, setEmpresas] = useState<Empresa[]>([]);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [seleccionando, setSeleccionando] = useState<number | null>(null);

  useEffect(() => {
    (async () => {
      try {
        setEmpresas(await listarEmpresas());
      } catch (err) {
        setError(mensajeError(err, 'No se pudieron cargar las empresas.'));
      } finally {
        setCargando(false);
      }
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function elegir(idEmpresa: number) {
    setSeleccionando(idEmpresa);
    try {
      await seleccionarEmpresa(idEmpresa);
    } catch (err) {
      setError(mensajeError(err, 'No se pudo seleccionar la empresa.'));
    } finally {
      setSeleccionando(null);
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
      <Text style={styles.titulo}>Selecciona tu empresa</Text>
      {error ? <Text style={styles.error}>{error}</Text> : null}
      <FlatList
        data={empresas}
        keyExtractor={(item) => String(item.id_empresa)}
        contentContainerStyle={{ paddingBottom: 24 }}
        ListEmptyComponent={<Text style={styles.vacio}>No tienes empresas asignadas.</Text>}
        renderItem={({ item }) => (
          <TouchableOpacity
            style={styles.item}
            onPress={() => elegir(item.id_empresa)}
            disabled={seleccionando !== null}
          >
            <View style={{ flex: 1 }}>
              <Text style={styles.itemNombre}>{item.nombre}</Text>
              <Text style={styles.itemRuc}>RUC {item.ruc}</Text>
            </View>
            {seleccionando === item.id_empresa ? <ActivityIndicator color="#0d6efd" /> : null}
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
  titulo: { fontSize: 20, fontWeight: '700', marginBottom: 16 },
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
  itemNombre: { fontSize: 16, fontWeight: '600' },
  itemRuc: { fontSize: 13, color: '#777', marginTop: 2 },
  salir: { alignItems: 'center', paddingVertical: 16 },
  salirTexto: { color: '#dc3545', fontWeight: '600' },
});
