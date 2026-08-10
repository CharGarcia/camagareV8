import React, { useEffect, useState } from 'react';
import { ActivityIndicator, Alert, ScrollView, StyleSheet, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RootStackParamList } from '../navigation/RootNavigator';
import {
  CompraDetalle,
  ConceptoEgreso,
  FormaPago,
  PuntoEmision,
  obtenerCompra,
  obtenerDependenciasPago,
  registrarPagoCompra,
} from '../api/compras';
import { mensajeError } from '../api/client';
import SelectorLista from '../components/SelectorLista';

function saldoPendiente(c: CompraDetalle): number {
  const saldo = Number(c.importe_total) - Number(c.total_pagado) - Number(c.total_nc) - Number(c.total_retencion);
  return Math.max(0, saldo);
}

export default function CompraPagoScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const { params } = useRoute<RouteProp<RootStackParamList, 'CompraPago'>>();

  const [cargando, setCargando] = useState(true);
  const [guardando, setGuardando] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [compra, setCompra] = useState<CompraDetalle | null>(null);
  const [formasPago, setFormasPago] = useState<FormaPago[]>([]);
  const [conceptos, setConceptos] = useState<ConceptoEgreso[]>([]);
  const [puntos, setPuntos] = useState<PuntoEmision[]>([]);

  const [monto, setMonto] = useState('');
  const [idFormaPago, setIdFormaPago] = useState<number | null>(null);
  const [idConcepto, setIdConcepto] = useState<number | null>(null);
  const [idPunto, setIdPunto] = useState<number | null>(null);

  useEffect(() => {
    (async () => {
      try {
        const [c, deps] = await Promise.all([obtenerCompra(params.id), obtenerDependenciasPago()]);
        setCompra(c);
        setFormasPago(deps.formas_pago);
        setConceptos(deps.conceptos);
        setPuntos(deps.puntos);
        setMonto(saldoPendiente(c).toFixed(2));
        if (deps.puntos.length === 1) setIdPunto(deps.puntos[0].id_punto);
      } catch (err) {
        setError(mensajeError(err, 'No se pudo cargar la información de pago.'));
      } finally {
        setCargando(false);
      }
    })();
  }, [params.id]);

  async function guardar() {
    const montoNum = Number(monto.replace(',', '.'));
    if (!compra) return;
    if (!montoNum || montoNum <= 0) {
      Alert.alert('Monto inválido', 'Ingresa un monto mayor a cero.');
      return;
    }
    if (!idPunto) {
      Alert.alert('Falta el punto de emisión', 'Selecciona el punto de emisión del egreso.');
      return;
    }
    if (!idFormaPago) {
      Alert.alert('Falta la forma de pago', 'Selecciona la forma de pago.');
      return;
    }

    setGuardando(true);
    setError(null);
    try {
      const res = await registrarPagoCompra({
        id_compra: compra.id,
        monto_pagar: montoNum,
        id_punto_emision: idPunto,
        id_forma_pago: idFormaPago,
        id_egreso_concepto: idConcepto ?? undefined,
      });
      Alert.alert(
        'Pago registrado',
        `Egreso #${res.numero_egreso} generado.\nSaldo restante: $${res.saldo_restante.toFixed(2)}`,
        [{ text: 'OK', onPress: () => navigation.goBack() }]
      );
    } catch (err) {
      setError(mensajeError(err, 'No se pudo registrar el pago.'));
    } finally {
      setGuardando(false);
    }
  }

  if (cargando) {
    return <ActivityIndicator size="large" color="#0d6efd" style={{ marginTop: 40 }} />;
  }
  if (!compra) {
    return <Text style={styles.error}>{error ?? 'Compra no encontrada.'}</Text>;
  }

  const saldo = saldoPendiente(compra);

  return (
    <ScrollView style={styles.container} contentContainerStyle={{ padding: 16 }}>
      <View style={styles.card}>
        <Text style={styles.proveedor}>{compra.proveedor_nombre}</Text>
        <Text style={styles.linea}>
          {compra.tipo_comprobante_nombre ?? 'Comprobante'} {compra.establecimiento_prov}-{compra.punto_emision_prov}-
          {compra.secuencial_prov}
        </Text>
        <View style={styles.filaSaldo}>
          <Text style={styles.saldoLabel}>Saldo pendiente</Text>
          <Text style={styles.saldoValor}>${saldo.toFixed(2)}</Text>
        </View>
      </View>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      <View style={styles.card}>
        <Text style={styles.label}>Monto a pagar</Text>
        <TextInput
          style={styles.input}
          value={monto}
          onChangeText={setMonto}
          keyboardType="decimal-pad"
          placeholder="0.00"
        />

        <View style={{ marginTop: 14 }}>
          <SelectorLista<number>
            label="Punto de emisión"
            value={idPunto}
            opciones={puntos.map((p) => ({ id: p.id_punto, label: `${p.estab}-${p.punto}` }))}
            onChange={setIdPunto}
          />
        </View>

        <View style={{ marginTop: 14 }}>
          <SelectorLista<number>
            label="Forma de pago"
            value={idFormaPago}
            opciones={formasPago.map((f) => ({ id: f.id, label: f.nombre }))}
            onChange={setIdFormaPago}
          />
        </View>

        <View style={{ marginTop: 14 }}>
          <SelectorLista<number>
            label="Concepto (opcional)"
            value={idConcepto}
            opciones={conceptos.map((c) => ({ id: c.id, label: c.nombre }))}
            onChange={setIdConcepto}
          />
        </View>

        <TouchableOpacity style={styles.boton} onPress={guardar} disabled={guardando}>
          {guardando ? <ActivityIndicator color="#fff" /> : <Text style={styles.botonTexto}>Registrar pago</Text>}
        </TouchableOpacity>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f6f8' },
  error: { color: '#dc3545', textAlign: 'center', marginVertical: 12 },
  card: { backgroundColor: '#fff', borderRadius: 10, padding: 16, marginBottom: 12, elevation: 1 },
  proveedor: { fontSize: 15, fontWeight: '700' },
  linea: { fontSize: 13, color: '#666', marginTop: 2 },
  filaSaldo: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 12 },
  saldoLabel: { fontSize: 14, color: '#666', fontWeight: '600' },
  saldoValor: { fontSize: 20, fontWeight: '800', color: '#dc3545' },
  label: { fontSize: 13, color: '#333', marginBottom: 4, fontWeight: '600' },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    backgroundColor: '#fff',
    fontSize: 18,
    fontWeight: '700',
  },
  boton: { backgroundColor: '#0d6efd', borderRadius: 8, paddingVertical: 14, marginTop: 20, alignItems: 'center' },
  botonTexto: { color: '#fff', fontSize: 16, fontWeight: '600' },
});
