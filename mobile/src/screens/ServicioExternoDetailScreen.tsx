import React, { useCallback, useState } from 'react';
import { ActivityIndicator, Alert, ScrollView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useFocusEffect, useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RootStackParamList } from '../navigation/RootNavigator';
import {
  Bodega,
  FormaPago,
  OrdenDetalle,
  generarDocumento,
  obtenerCatalogos,
  obtenerOrden,
} from '../api/servicioExterno';
import { mensajeError } from '../api/client';
import SelectorLista from '../components/SelectorLista';

const ESTADO_LABEL: Record<string, string> = { borrador: 'Borrador', facturado: 'Facturado', anulado: 'Anulado' };
const ESTADO_COLOR: Record<string, string> = { borrador: '#6c757d', facturado: '#198754', anulado: '#dc3545' };

export default function ServicioExternoDetailScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const route = useRoute<RouteProp<RootStackParamList, 'ServicioExternoDetail'>>();
  const { id } = route.params;

  const [orden, setOrden] = useState<OrdenDetalle | null>(null);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [formasPago, setFormasPago] = useState<FormaPago[]>([]);
  const [bodegas, setBodegas] = useState<Bodega[]>([]);
  const [mostrarGenerar, setMostrarGenerar] = useState(false);
  const [tipoDoc, setTipoDoc] = useState<'FACTURA' | 'RECIBO'>('FACTURA');
  const [formaPago, setFormaPago] = useState<string | null>(null);
  const [idBodega, setIdBodega] = useState<number | null>(null);
  const [generando, setGenerando] = useState(false);

  useFocusEffect(
    useCallback(() => {
      let activo = true;
      setCargando(true);
      setError(null);
      Promise.all([obtenerOrden(id), obtenerCatalogos()])
        .then(([o, cat]) => {
          if (!activo) return;
          setOrden(o);
          setFormasPago(cat.formas_pago);
          setBodegas(cat.bodegas);
          if (cat.formas_pago.length === 1) setFormaPago(cat.formas_pago[0].codigo);
          if (cat.bodegas.length === 1) setIdBodega(cat.bodegas[0].id);
        })
        .catch((err) => activo && setError(mensajeError(err, 'No se pudo cargar la orden.')))
        .finally(() => activo && setCargando(false));
      return () => {
        activo = false;
      };
    }, [id])
  );

  async function confirmarGenerar() {
    if (!formaPago) {
      Alert.alert('Falta la forma de pago', 'Selecciona la forma de pago.');
      return;
    }
    const hayLineasProducto = orden?.detalles.some((d) => d.tipo_linea === 'producto');
    if (hayLineasProducto && !idBodega) {
      Alert.alert('Falta la bodega', 'Esta orden tiene repuestos; selecciona la bodega para descontar el stock.');
      return;
    }
    setGenerando(true);
    try {
      const res = await generarDocumento(id, tipoDoc, formaPago, idBodega ?? undefined);
      setMostrarGenerar(false);
      Alert.alert(
        `${tipoDoc === 'FACTURA' ? 'Factura' : 'Recibo'} generado`,
        `Documento N° ${res.numero_documento}`,
        [{ text: 'OK', onPress: () => navigation.goBack() }]
      );
    } catch (err) {
      Alert.alert('No se pudo generar el documento', mensajeError(err, 'Intenta nuevamente.'));
    } finally {
      setGenerando(false);
    }
  }

  if (cargando) {
    return <ActivityIndicator size="large" color="#0d6efd" style={{ marginTop: 40 }} />;
  }

  if (error || !orden) {
    return (
      <View style={{ padding: 20 }}>
        <Text style={styles.error}>{error ?? 'Orden no encontrada.'}</Text>
      </View>
    );
  }

  const color = ESTADO_COLOR[orden.estado] ?? '#6c757d';
  const fecha = orden.fecha_servicio ? new Date(orden.fecha_servicio).toLocaleDateString('es-EC') : '';
  const puedeGenerar = orden.estado === 'borrador';

  return (
    <ScrollView style={styles.container} contentContainerStyle={{ padding: 16, paddingBottom: 60 }}>
      <View style={styles.card}>
        <View style={styles.encabezado}>
          <Text style={styles.numero}>{orden.numero_orden}</Text>
          <View style={[styles.badge, { borderColor: color, backgroundColor: color + '22' }]}>
            <Text style={[styles.badgeTexto, { color }]}>{ESTADO_LABEL[orden.estado] ?? orden.estado}</Text>
          </View>
        </View>
        <Text style={styles.cliente}>{orden.cliente_nombre}</Text>
        <Text style={styles.subtexto}>{orden.cliente_identificacion} · {fecha}</Text>
        {orden.numero_documento ? (
          <Text style={styles.subtexto}>
            {orden.tipo_documento === 'RECIBO' ? 'Recibo' : 'Factura'} N° {orden.numero_documento}
          </Text>
        ) : null}
      </View>

      <View style={styles.card}>
        <Text style={styles.seccionTitulo}>Equipo</Text>
        <Text style={styles.valor}>{orden.equipo_descripcion}</Text>
        {orden.equipo_marca || orden.equipo_modelo ? (
          <Text style={styles.subtexto}>
            {[orden.equipo_marca, orden.equipo_modelo].filter(Boolean).join(' · ')}
          </Text>
        ) : null}
        {orden.equipo_serie ? <Text style={styles.subtexto}>Serie: {orden.equipo_serie}</Text> : null}
        {orden.direccion_servicio ? (
          <>
            <Text style={styles.label}>Dirección del servicio</Text>
            <Text style={styles.valor}>{orden.direccion_servicio}</Text>
          </>
        ) : null}
        {orden.descripcion_trabajo ? (
          <>
            <Text style={styles.label}>Trabajo realizado</Text>
            <Text style={styles.valor}>{orden.descripcion_trabajo}</Text>
          </>
        ) : null}
        {orden.observaciones ? (
          <>
            <Text style={styles.label}>Observaciones</Text>
            <Text style={styles.valor}>{orden.observaciones}</Text>
          </>
        ) : null}
      </View>

      <View style={styles.card}>
        <Text style={styles.seccionTitulo}>Repuestos y servicios</Text>
        {orden.detalles.map((d) => (
          <View key={d.id} style={styles.linea}>
            <View style={{ flex: 1 }}>
              <Text style={styles.lineaDesc}>
                {d.tipo_linea === 'producto' ? '🔧 ' : '🛠️ '}
                {d.descripcion}
              </Text>
              <Text style={styles.lineaSub}>
                {Number(d.cantidad)} x ${Number(d.precio_unitario).toFixed(2)}
              </Text>
            </View>
            <Text style={styles.lineaTotal}>${Number(d.total_linea).toFixed(2)}</Text>
          </View>
        ))}

        <View style={styles.totales}>
          <View style={styles.totalFila}>
            <Text style={styles.totalLabel}>Subtotal</Text>
            <Text style={styles.totalValor}>${Number(orden.subtotal).toFixed(2)}</Text>
          </View>
          <View style={styles.totalFila}>
            <Text style={styles.totalLabel}>IVA</Text>
            <Text style={styles.totalValor}>${Number(orden.iva).toFixed(2)}</Text>
          </View>
          <View style={styles.totalFila}>
            <Text style={styles.totalLabelGrande}>Total</Text>
            <Text style={styles.totalValorGrande}>${Number(orden.total).toFixed(2)}</Text>
          </View>
        </View>
      </View>

      {puedeGenerar && !mostrarGenerar ? (
        <TouchableOpacity style={styles.botonGenerar} onPress={() => setMostrarGenerar(true)}>
          <Text style={styles.botonGenerarTexto}>Generar documento</Text>
        </TouchableOpacity>
      ) : null}

      {mostrarGenerar ? (
        <View style={styles.card}>
          <Text style={styles.seccionTitulo}>Generar documento</Text>

          <View style={styles.tipoLineaFila}>
            <TouchableOpacity
              style={[styles.tipoBoton, tipoDoc === 'FACTURA' && styles.tipoBotonActivo]}
              onPress={() => setTipoDoc('FACTURA')}
            >
              <Text style={tipoDoc === 'FACTURA' ? styles.tipoBotonTextoActivo : styles.tipoBotonTexto}>Factura</Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={[styles.tipoBoton, tipoDoc === 'RECIBO' && styles.tipoBotonActivo]}
              onPress={() => setTipoDoc('RECIBO')}
            >
              <Text style={tipoDoc === 'RECIBO' ? styles.tipoBotonTextoActivo : styles.tipoBotonTexto}>Recibo</Text>
            </TouchableOpacity>
          </View>

          <View style={{ marginTop: 10 }}>
            <SelectorLista<string>
              label="Forma de pago"
              value={formaPago}
              opciones={formasPago.map((f) => ({ id: f.codigo, label: f.nombre }))}
              onChange={setFormaPago}
            />
          </View>

          {orden.detalles.some((d) => d.tipo_linea === 'producto') ? (
            <View style={{ marginTop: 10 }}>
              <SelectorLista<number>
                label="Bodega (descuenta stock de repuestos)"
                value={idBodega}
                opciones={bodegas.map((b) => ({ id: b.id, label: b.nombre }))}
                onChange={setIdBodega}
              />
            </View>
          ) : null}

          <View style={styles.accionesGenerar}>
            <TouchableOpacity style={styles.botonCancelar} onPress={() => setMostrarGenerar(false)}>
              <Text style={styles.botonCancelarTexto}>Cancelar</Text>
            </TouchableOpacity>
            <TouchableOpacity style={styles.botonConfirmar} onPress={confirmarGenerar} disabled={generando}>
              {generando ? <ActivityIndicator color="#fff" /> : <Text style={styles.botonConfirmarTexto}>Confirmar</Text>}
            </TouchableOpacity>
          </View>
        </View>
      ) : null}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f6f8' },
  error: { color: '#dc3545', textAlign: 'center' },
  card: { backgroundColor: '#fff', borderRadius: 10, padding: 14, marginBottom: 12, elevation: 1 },
  encabezado: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  numero: { fontSize: 15, fontWeight: '700', color: '#0d6efd' },
  badge: { borderWidth: 1, borderRadius: 20, paddingHorizontal: 10, paddingVertical: 3 },
  badgeTexto: { fontSize: 11, fontWeight: '700' },
  cliente: { fontSize: 17, fontWeight: '700', marginTop: 8 },
  subtexto: { fontSize: 12, color: '#777', marginTop: 2 },
  seccionTitulo: { fontSize: 13, fontWeight: '700', color: '#666', textTransform: 'uppercase', marginBottom: 8 },
  label: { fontSize: 12, color: '#888', marginTop: 10, fontWeight: '600' },
  valor: { fontSize: 14, color: '#222', marginTop: 2 },
  linea: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
  },
  lineaDesc: { fontSize: 13, fontWeight: '600' },
  lineaSub: { fontSize: 11, color: '#888', marginTop: 1 },
  lineaTotal: { fontSize: 13, fontWeight: '700' },
  totales: { marginTop: 10, paddingTop: 10, borderTopWidth: 1, borderTopColor: '#eee' },
  totalFila: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 4 },
  totalLabel: { fontSize: 13, color: '#666' },
  totalValor: { fontSize: 13, color: '#333' },
  totalLabelGrande: { fontSize: 15, fontWeight: '700', marginTop: 4 },
  totalValorGrande: { fontSize: 17, fontWeight: '800', color: '#0d6efd', marginTop: 4 },
  botonGenerar: { backgroundColor: '#0d6efd', borderRadius: 8, paddingVertical: 16, alignItems: 'center', marginBottom: 12 },
  botonGenerarTexto: { color: '#fff', fontSize: 16, fontWeight: '700' },
  tipoLineaFila: { flexDirection: 'row', gap: 8 },
  tipoBoton: { flex: 1, paddingVertical: 10, borderRadius: 8, borderWidth: 1, borderColor: '#0d6efd', alignItems: 'center' },
  tipoBotonActivo: { backgroundColor: '#0d6efd' },
  tipoBotonTexto: { color: '#0d6efd', fontWeight: '600', fontSize: 13 },
  tipoBotonTextoActivo: { color: '#fff', fontWeight: '700', fontSize: 13 },
  accionesGenerar: { flexDirection: 'row', gap: 10, marginTop: 16 },
  botonCancelar: { flex: 1, paddingVertical: 12, borderRadius: 8, borderWidth: 1, borderColor: '#ccc', alignItems: 'center' },
  botonCancelarTexto: { color: '#666', fontWeight: '600' },
  botonConfirmar: { flex: 1, paddingVertical: 12, borderRadius: 8, backgroundColor: '#198754', alignItems: 'center' },
  botonConfirmarTexto: { color: '#fff', fontWeight: '700' },
});
