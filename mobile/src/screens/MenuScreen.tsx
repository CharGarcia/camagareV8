import React, { useCallback, useState } from 'react';
import { ActivityIndicator, SectionList, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RootStackParamList } from '../navigation/RootNavigator';
import { ModuloMenu, obtenerMenu, SubmoduloMenu } from '../api/menu';
import { mensajeError } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { useSerie } from '../pedidos/SerieContext';
import ModuloIcono from '../components/ModuloIcono';

/**
 * Rutas MVC (tal cual vienen de submodulos_menu.ruta) que ya tienen pantalla en la
 * app. El menú solo muestra lo que está en esta lista — según se vaya construyendo
 * cada módulo (Consignaciones, etc.) simplemente se agrega aquí.
 */
const RUTAS_IMPLEMENTADAS = new Set(['modulos/pedidos']);

function soloImplementados(modulos: ModuloMenu[]): ModuloMenu[] {
  return modulos
    .map((m) => ({ ...m, submodulos: m.submodulos.filter((s) => RUTAS_IMPLEMENTADAS.has(s.ruta)) }))
    .filter((m) => m.submodulos.length > 0);
}

export default function MenuScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const { logout } = useAuth();
  const { serie } = useSerie();
  const [modulos, setModulos] = useState<ModuloMenu[]>([]);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      let activo = true;
      (async () => {
        try {
          const data = await obtenerMenu();
          if (activo) setModulos(soloImplementados(data));
        } catch (err) {
          if (activo) setError(mensajeError(err, 'No se pudo cargar el menú.'));
        } finally {
          if (activo) setCargando(false);
        }
      })();
      return () => {
        activo = false;
      };
    }, [])
  );

  function abrirSubmodulo(sub: SubmoduloMenu) {
    if (sub.ruta === 'modulos/pedidos') {
      navigation.navigate(serie ? 'PedidosList' : 'SeleccionSerie');
    }
  }

  if (cargando) {
    return (
      <View style={styles.centrado}>
        <ActivityIndicator size="large" color="#0d6efd" />
      </View>
    );
  }

  const secciones = modulos.map((m) => ({ title: m.nombre_modulo, icono: m.icono_modulo, data: m.submodulos }));

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.titulo}>Módulos</Text>
        <TouchableOpacity onPress={() => logout()}>
          <Text style={styles.salir}>Salir</Text>
        </TouchableOpacity>
      </View>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      <SectionList
        sections={secciones}
        keyExtractor={(item) => String(item.id_submodulo)}
        contentContainerStyle={{ padding: 16, paddingBottom: 40 }}
        stickySectionHeadersEnabled={false}
        ListEmptyComponent={
          <Text style={styles.vacio}>
            Todavía no hay módulos disponibles en la app para tu usuario. Por ahora la app cubre Pedidos.
          </Text>
        }
        renderSectionHeader={({ section }) => (
          <View style={styles.seccionHeader}>
            <ModuloIcono clase={section.icono} size={16} color="#666" />
            <Text style={styles.seccionTitulo}>{section.title}</Text>
          </View>
        )}
        renderItem={({ item }) => (
          <TouchableOpacity style={styles.item} onPress={() => abrirSubmodulo(item)}>
            <ModuloIcono clase={item.icono_submodulo} size={18} color="#0d6efd" />
            <Text style={styles.itemTexto}>{item.nombre_submodulo}</Text>
          </TouchableOpacity>
        )}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f6f8' },
  centrado: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 16,
    paddingTop: 56,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
  },
  titulo: { fontSize: 20, fontWeight: '700' },
  salir: { color: '#dc3545', fontWeight: '600' },
  error: { color: '#dc3545', textAlign: 'center', marginTop: 12 },
  vacio: { color: '#888', textAlign: 'center', marginTop: 40 },
  seccionHeader: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 18, marginBottom: 6 },
  seccionTitulo: { fontSize: 12, fontWeight: '700', color: '#666', textTransform: 'uppercase' },
  item: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    backgroundColor: '#fff',
    borderRadius: 10,
    padding: 14,
    marginBottom: 8,
    elevation: 1,
  },
  itemTexto: { fontSize: 15, fontWeight: '600', flex: 1 },
});
