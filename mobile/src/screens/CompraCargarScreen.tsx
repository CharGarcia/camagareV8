import React, { useState } from 'react';
import { ActivityIndicator, Alert, ScrollView, StyleSheet, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RootStackParamList } from '../navigation/RootNavigator';
import { cargarCompraPorAutorizacion } from '../api/compras';
import { mensajeError } from '../api/client';

export default function CompraCargarScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const [numeroAutorizacion, setNumeroAutorizacion] = useState('');
  const [cargando, setCargando] = useState(false);

  const soloDigitos = numeroAutorizacion.replace(/\D/g, '');
  const longitudValida = soloDigitos.length === 49;

  async function cargar() {
    if (!longitudValida) {
      Alert.alert('Número inválido', 'El número de autorización debe tener 49 dígitos (clave de acceso electrónica del SRI).');
      return;
    }

    setCargando(true);
    try {
      const res = await cargarCompraPorAutorizacion(soloDigitos);

      if (res.existe) {
        Alert.alert('Ya estaba registrada', res.mensaje, [{ text: 'OK', onPress: () => navigation.goBack() }]);
        return;
      }

      const detalle = [res.tipo_nombre, res.emisor, res.numero_documento].filter(Boolean).join(' · ');
      Alert.alert('Compra registrada', detalle ? `${res.mensaje}\n\n${detalle}` : res.mensaje, [
        { text: 'OK', onPress: () => navigation.goBack() },
      ]);
    } catch (err) {
      Alert.alert('No se pudo cargar', mensajeError(err, 'No se pudo consultar/registrar el documento.'));
    } finally {
      setCargando(false);
    }
  }

  return (
    <ScrollView style={styles.container} contentContainerStyle={{ padding: 16 }}>
      <View style={styles.card}>
        <Text style={styles.titulo}>Cargar desde el SRI</Text>
        <Text style={styles.ayuda}>
          Ingresa el número de autorización (clave de acceso electrónica, 49 dígitos) de la factura del proveedor.
          Se consulta directo al SRI y, si está autorizada, la compra se registra automáticamente.
        </Text>

        <TextInput
          style={styles.input}
          placeholder="49 dígitos"
          keyboardType="number-pad"
          value={numeroAutorizacion}
          onChangeText={setNumeroAutorizacion}
          maxLength={49}
          editable={!cargando}
        />
        <Text style={styles.contador}>{soloDigitos.length}/49</Text>

        <TouchableOpacity
          style={[styles.boton, (!longitudValida || cargando) && styles.botonDeshabilitado]}
          onPress={cargar}
          disabled={!longitudValida || cargando}
        >
          {cargando ? <ActivityIndicator color="#fff" /> : <Text style={styles.botonTexto}>Consultar y registrar</Text>}
        </TouchableOpacity>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f6f8' },
  card: { backgroundColor: '#fff', borderRadius: 10, padding: 16, elevation: 1 },
  titulo: { fontSize: 16, fontWeight: '700', marginBottom: 8 },
  ayuda: { fontSize: 13, color: '#666', marginBottom: 16, lineHeight: 18 },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    backgroundColor: '#fff',
    fontSize: 14,
  },
  contador: { fontSize: 11, color: '#999', textAlign: 'right', marginTop: 4 },
  boton: { backgroundColor: '#0d6efd', paddingVertical: 14, borderRadius: 8, marginTop: 16, alignItems: 'center' },
  botonDeshabilitado: { backgroundColor: '#a7c8fb' },
  botonTexto: { color: '#fff', fontWeight: '700', fontSize: 15 },
});
