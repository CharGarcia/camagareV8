import React, { useEffect, useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRoute, RouteProp } from '@react-navigation/native';
import type { RootStackParamList } from '../navigation/RootNavigator';
import { CompraDetalle, obtenerCompra } from '../api/compras';
import { mensajeError } from '../api/client';

export default function CompraDetailScreen() {
  const { params } = useRoute<RouteProp<RootStackParamList, 'CompraDetail'>>();
  const [compra, setCompra] = useState<CompraDetalle | null>(null);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let activo = true;
    (async () => {
      try {
        const data = await obtenerCompra(params.id);
        if (activo) setCompra(data);
      } catch (err) {
        if (activo) setError(mensajeError(err, 'No se pudo cargar la compra.'));
      } finally {
        if (activo) setCargando(false);
      }
    })();
    return () => {
      activo = false;
    };
  }, [params.id]);

  if (cargando) {
    return <ActivityIndicator size="large" color="#0d6efd" style={{ marginTop: 40 }} />;
  }
  if (error || !compra) {
    return <Text style={styles.error}>{error ?? 'Compra no encontrada.'}</Text>;
  }

  const numero = `${compra.establecimiento_prov}-${compra.punto_emision_prov}-${compra.secuencial_prov}`;
  const fecha = compra.fecha_emision ? new Date(compra.fecha_emision).toLocaleDateString('es-EC') : '';

  return (
    <ScrollView style={styles.container} contentContainerStyle={{ padding: 16 }}>
      <View style={styles.card}>
        <Text style={styles.proveedor}>{compra.proveedor_nombre}</Text>
        <Text style={styles.linea}>RUC/CI: {compra.proveedor_ruc}</Text>
        <Text style={styles.linea}>
          {compra.tipo_comprobante_nombre ?? 'Comprobante'} {numero}
        </Text>
        <Text style={styles.linea}>Autorización: {compra.numero_autorizacion}</Text>
        <Text style={styles.linea}>Fecha de emisión: {fecha}</Text>
        {compra.sustento_nombre ? <Text style={styles.linea}>Sustento tributario: {compra.sustento_nombre}</Text> : null}
      </View>

      <View style={styles.card}>
        <Text style={styles.seccionTitulo}>Totales</Text>
        <Fila label="Subtotal" valor={compra.total_sin_impuestos} />
        <Fila label="IVA" valor={compra.monto_iva} />
        <Fila label="Total" valor={compra.importe_total} destacado />
        <Fila label="Pagado" valor={compra.total_pagado} />
        {Number(compra.total_nc) > 0 ? <Fila label="Notas de crédito" valor={compra.total_nc} /> : null}
        {Number(compra.total_retencion) > 0 ? <Fila label="Retenido" valor={compra.total_retencion} /> : null}
      </View>

      <View style={styles.card}>
        <Text style={styles.seccionTitulo}>Detalle</Text>
        {compra.detalles.length === 0 ? (
          <Text style={styles.linea}>Sin líneas de detalle.</Text>
        ) : (
          compra.detalles.map((d) => (
            <View key={d.id} style={styles.detalleLinea}>
              <View style={{ flex: 1 }}>
                <Text style={styles.detalleNombre} numberOfLines={2}>
                  {d.producto_nombre ?? d.descripcion}
                </Text>
                <Text style={styles.subtexto}>
                  {Number(d.cantidad)} x ${Number(d.precio_unitario).toFixed(2)}
                </Text>
              </View>
              <Text style={styles.detalleTotal}>${Number(d.precio_total).toFixed(2)}</Text>
            </View>
          ))
        )}
      </View>
    </ScrollView>
  );
}

function Fila({ label, valor, destacado = false }: { label: string; valor: string; destacado?: boolean }) {
  return (
    <View style={styles.filaTotal}>
      <Text style={destacado ? styles.filaLabelDestacado : styles.filaLabel}>{label}</Text>
      <Text style={destacado ? styles.filaValorDestacado : styles.filaValor}>${Number(valor).toFixed(2)}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f6f8' },
  error: { color: '#dc3545', textAlign: 'center', marginTop: 40 },
  card: { backgroundColor: '#fff', borderRadius: 10, padding: 14, marginBottom: 12, elevation: 1 },
  proveedor: { fontSize: 16, fontWeight: '700', marginBottom: 4 },
  linea: { fontSize: 13, color: '#444', marginTop: 2 },
  subtexto: { fontSize: 12, color: '#777', marginTop: 2 },
  seccionTitulo: { fontSize: 13, fontWeight: '700', color: '#666', textTransform: 'uppercase', marginBottom: 8 },
  filaTotal: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 4 },
  filaLabel: { fontSize: 13, color: '#666' },
  filaValor: { fontSize: 13, color: '#333' },
  filaLabelDestacado: { fontSize: 14, fontWeight: '700', color: '#333' },
  filaValorDestacado: { fontSize: 14, fontWeight: '700', color: '#0d6efd' },
  detalleLinea: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 8,
    borderTopWidth: 1,
    borderTopColor: '#f0f0f0',
  },
  detalleNombre: { fontSize: 13, fontWeight: '600' },
  detalleTotal: { fontSize: 13, fontWeight: '700', color: '#333' },
});
