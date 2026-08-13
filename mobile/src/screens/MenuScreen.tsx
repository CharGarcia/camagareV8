import React, { useCallback, useState } from 'react';
import { ActivityIndicator, SectionList, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RootStackParamList } from '../navigation/RootNavigator';
import { ModuloMenu, obtenerMenu, SubmoduloMenu } from '../api/menu';
import { mensajeError } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import ModuloIcono from '../components/ModuloIcono';

/**
 * Rutas MVC (tal cual vienen de submodulos_menu.ruta) que ya tienen pantalla en la
 * app. El menú solo muestra lo que está en esta lista — según se vaya construyendo
 * cada módulo (Consignaciones, etc.) simplemente se agrega aquí.
 */
const RUTAS_IMPLEMENTADAS = new Set([
  'modulos/pedidos',
  'modulos/entregas-consignaciones',
  'modulos/clientes',
  'modulos/productos',
  'modulos/factura-venta',
  'modulos/compras',
  'modulos/dashboard',
  'modulos/proveedores',
  'modulos/reporte_ventas',
  'modulos/reporte_compras',
  'modulos/servicio-externo',
]);

function soloImplementados(modulos: ModuloMenu[]): ModuloMenu[] {
  return modulos
    .map((m) => ({ ...m, submodulos: m.submodulos.filter((s) => RUTAS_IMPLEMENTADAS.has(s.ruta)) }))
    .filter((m) => m.submodulos.length > 0);
}

export default function MenuScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const { logout, nombreEmpresa, cambiarEmpresa } = useAuth();
  const [modulos, setModulos] = useState<ModuloMenu[]>([]);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [colapsados, setColapsados] = useState<Set<string>>(new Set());

  function toggleSeccion(titulo: string) {
    setColapsados((prev) => {
      const copia = new Set(prev);
      if (copia.has(titulo)) {
        copia.delete(titulo);
      } else {
        copia.add(titulo);
      }
      return copia;
    });
  }

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
      navigation.navigate('PedidosList');
    } else if (sub.ruta === 'modulos/entregas-consignaciones') {
      navigation.navigate('EntregasList');
    } else if (sub.ruta === 'modulos/clientes') {
      navigation.navigate('ClientesList');
    } else if (sub.ruta === 'modulos/productos') {
      navigation.navigate('ProductosList');
    } else if (sub.ruta === 'modulos/factura-venta') {
      navigation.navigate('FacturasVentaList');
    } else if (sub.ruta === 'modulos/compras') {
      navigation.navigate('ComprasList');
    } else if (sub.ruta === 'modulos/dashboard') {
      navigation.navigate('Dashboard');
    } else if (sub.ruta === 'modulos/proveedores') {
      navigation.navigate('ProveedoresList');
    } else if (sub.ruta === 'modulos/reporte_ventas') {
      navigation.navigate('ReporteVentas');
    } else if (sub.ruta === 'modulos/reporte_compras') {
      navigation.navigate('ReporteCompras');
    } else if (sub.ruta === 'modulos/servicio-externo') {
      navigation.navigate('ServicioExternoList');
    }
  }

  if (cargando) {
    return (
      <View style={styles.centrado}>
        <ActivityIndicator size="large" color="#0d6efd" />
      </View>
    );
  }

  const secciones = modulos.map((m) => ({
    title: m.nombre_modulo,
    icono: m.icono_modulo,
    data: colapsados.has(m.nombre_modulo) ? [] : m.submodulos,
  }));

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <View style={{ flex: 1 }}>
          <Text style={styles.titulo}>Módulos</Text>
          {nombreEmpresa ? (
            <View style={styles.empresaFila}>
              <Ionicons name="business-outline" size={14} color="#666" />
              <Text style={styles.empresaNombre} numberOfLines={1}>
                {nombreEmpresa}
              </Text>
              <TouchableOpacity onPress={() => cambiarEmpresa()} style={styles.botonCambiar}>
                <Text style={styles.botonCambiarTexto}>Cambiar</Text>
              </TouchableOpacity>
            </View>
          ) : null}
        </View>
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
            Todavía no hay módulos disponibles en la app para tu usuario. Por ahora la app cubre Pedidos y Entregas.
          </Text>
        }
        renderSectionHeader={({ section }) => {
          const colapsada = colapsados.has(section.title);
          return (
            <TouchableOpacity
              style={styles.seccionHeader}
              onPress={() => toggleSeccion(section.title)}
              activeOpacity={0.6}
            >
              <ModuloIcono clase={section.icono} size={16} color="#666" />
              <Text style={styles.seccionTitulo}>{section.title}</Text>
              <View style={{ flex: 1 }} />
              <Ionicons name={colapsada ? 'chevron-down' : 'chevron-up'} size={16} color="#999" />
            </TouchableOpacity>
          );
        }}
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
  empresaFila: { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 4 },
  empresaNombre: { fontSize: 13, color: '#666', flexShrink: 1 },
  botonCambiar: {
    borderWidth: 1,
    borderColor: '#0d6efd',
    borderRadius: 20,
    paddingHorizontal: 10,
    paddingVertical: 3,
    marginLeft: 4,
  },
  botonCambiarTexto: { fontSize: 11, color: '#0d6efd', fontWeight: '700' },
  salir: { color: '#dc3545', fontWeight: '600' },
  error: { color: '#dc3545', textAlign: 'center', marginTop: 12 },
  vacio: { color: '#888', textAlign: 'center', marginTop: 40 },
  seccionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginTop: 18,
    marginBottom: 6,
    paddingVertical: 4,
  },
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
