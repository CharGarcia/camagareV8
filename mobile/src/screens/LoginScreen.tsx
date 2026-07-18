import React, { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  KeyboardAvoidingView,
  Platform,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { useAuth } from '../auth/AuthContext';
import { mensajeError } from '../api/client';

export default function LoginScreen() {
  const { login, avisoSesionActiva, forzarLoginDesdeAviso, descartarAvisoSesionActiva } = useAuth();
  const [cedula, setCedula] = useState('');
  const [password, setPassword] = useState('');
  const [cargando, setCargando] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!avisoSesionActiva) return;
    Alert.alert(
      'Sesión activa en otro celular',
      `Ya hay una sesión abierta desde otro dispositivo${
        avisoSesionActiva.ultimaActividad ? ` (última actividad: ${avisoSesionActiva.ultimaActividad})` : ''
      }. ¿Cerrar esa sesión y entrar aquí?`,
      [
        { text: 'Cancelar', style: 'cancel', onPress: () => descartarAvisoSesionActiva() },
        {
          text: 'Cerrar la otra e ingresar',
          style: 'destructive',
          onPress: async () => {
            setCargando(true);
            try {
              await forzarLoginDesdeAviso();
            } catch (err) {
              setError(mensajeError(err, 'No se pudo iniciar sesión.'));
            } finally {
              setCargando(false);
            }
          },
        },
      ]
    );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [avisoSesionActiva]);

  async function onSubmit() {
    if (!cedula.trim() || !password.trim()) {
      setError('Ingresa cédula y contraseña.');
      return;
    }
    setError(null);
    setCargando(true);
    try {
      await login(cedula.trim(), password);
    } catch (err) {
      setError(mensajeError(err, 'Usuario o contraseña incorrectos.'));
    } finally {
      setCargando(false);
    }
  }

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <View style={styles.card}>
        <Text style={styles.titulo}>CaMaGaRe</Text>
        <Text style={styles.subtitulo}>Ingresa con tu usuario del sistema</Text>

        <Text style={styles.label}>Cédula</Text>
        <TextInput
          style={styles.input}
          value={cedula}
          onChangeText={setCedula}
          keyboardType="number-pad"
          autoCapitalize="none"
          placeholder="0000000000"
        />

        <Text style={styles.label}>Contraseña</Text>
        <TextInput
          style={styles.input}
          value={password}
          onChangeText={setPassword}
          secureTextEntry
          autoCapitalize="none"
          placeholder="••••••••"
        />

        {error ? <Text style={styles.error}>{error}</Text> : null}

        <TouchableOpacity style={styles.boton} onPress={onSubmit} disabled={cargando}>
          {cargando ? <ActivityIndicator color="#fff" /> : <Text style={styles.botonTexto}>Ingresar</Text>}
        </TouchableOpacity>
      </View>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#0d6efd', justifyContent: 'center', padding: 20 },
  card: { backgroundColor: '#fff', borderRadius: 12, padding: 24 },
  titulo: { fontSize: 26, fontWeight: '700', color: '#0d6efd', textAlign: 'center' },
  subtitulo: { fontSize: 14, color: '#666', textAlign: 'center', marginBottom: 24 },
  label: { fontSize: 13, color: '#333', marginBottom: 4, marginTop: 12 },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 16,
  },
  error: { color: '#dc3545', marginTop: 12, textAlign: 'center' },
  boton: {
    backgroundColor: '#0d6efd',
    borderRadius: 8,
    paddingVertical: 14,
    marginTop: 24,
    alignItems: 'center',
  },
  botonTexto: { color: '#fff', fontSize: 16, fontWeight: '600' },
});
