import React, { useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Modal,
  Platform,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useAuth } from '../auth/AuthContext';
import { mensajeError } from '../api/client';

export default function LoginScreen() {
  const { login, avisoSesionActiva, forzarLoginDesdeAviso, descartarAvisoSesionActiva } = useAuth();
  const [cedula, setCedula] = useState('');
  const [password, setPassword] = useState('');
  const [verPassword, setVerPassword] = useState(false);
  const [cargando, setCargando] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Errores propios del panel "sesión activa en otro celular" (no del formulario de login).
  const [cargandoForzar, setCargandoForzar] = useState(false);
  const [errorForzar, setErrorForzar] = useState<string | null>(null);

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

  async function onForzarIngreso() {
    setErrorForzar(null);
    setCargandoForzar(true);
    try {
      await forzarLoginDesdeAviso();
    } catch (err) {
      setErrorForzar(mensajeError(err, 'No se pudo iniciar sesión.'));
    } finally {
      setCargandoForzar(false);
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
        <View style={styles.inputConIcono}>
          <TextInput
            style={styles.inputTexto}
            value={password}
            onChangeText={setPassword}
            secureTextEntry={!verPassword}
            autoCapitalize="none"
            autoCorrect={false}
            placeholder="••••••••"
          />
          <TouchableOpacity onPress={() => setVerPassword((v) => !v)} hitSlop={10}>
            <Ionicons name={verPassword ? 'eye-off-outline' : 'eye-outline'} size={20} color="#666" />
          </TouchableOpacity>
        </View>

        {error ? <Text style={styles.error}>{error}</Text> : null}

        <TouchableOpacity style={styles.boton} onPress={onSubmit} disabled={cargando}>
          {cargando ? <ActivityIndicator color="#fff" /> : <Text style={styles.botonTexto}>Ingresar</Text>}
        </TouchableOpacity>
      </View>

      {/* Panel propio (no Alert.alert nativo) para poder controlar el flujo y mostrar
          errores en pantalla en vez de que falle en silencio. */}
      <Modal visible={!!avisoSesionActiva} animationType="fade" transparent onRequestClose={() => {}}>
        <View style={styles.overlay}>
          <View style={styles.dialogo}>
            <Text style={styles.dialogoTitulo}>Sesión activa en otro celular</Text>
            <Text style={styles.dialogoTexto}>
              Ya hay una sesión abierta desde otro dispositivo
              {avisoSesionActiva?.ultimaActividad ? ` (última actividad: ${avisoSesionActiva.ultimaActividad})` : ''}.
              ¿Cerrar esa sesión y entrar aquí?
            </Text>

            {errorForzar ? <Text style={styles.error}>{errorForzar}</Text> : null}

            <View style={styles.dialogoAcciones}>
              <TouchableOpacity
                onPress={() => {
                  setErrorForzar(null);
                  descartarAvisoSesionActiva();
                }}
                disabled={cargandoForzar}
                style={styles.dialogoBotonCancelar}
              >
                <Text style={styles.dialogoCancelarTexto}>Cancelar</Text>
              </TouchableOpacity>
              <TouchableOpacity
                onPress={onForzarIngreso}
                disabled={cargandoForzar}
                style={styles.dialogoBotonConfirmar}
              >
                {cargandoForzar ? (
                  <ActivityIndicator color="#fff" />
                ) : (
                  <Text style={styles.dialogoConfirmarTexto}>Cerrar la otra e ingresar</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
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
  inputConIcono: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: 12,
  },
  inputTexto: { flex: 1, paddingVertical: 10, fontSize: 16 },
  error: { color: '#dc3545', marginTop: 12, textAlign: 'center' },
  boton: {
    backgroundColor: '#0d6efd',
    borderRadius: 8,
    paddingVertical: 14,
    marginTop: 24,
    alignItems: 'center',
  },
  botonTexto: { color: '#fff', fontSize: 16, fontWeight: '600' },
  overlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'center', padding: 24 },
  dialogo: { backgroundColor: '#fff', borderRadius: 12, padding: 20 },
  dialogoTitulo: { fontSize: 17, fontWeight: '700', marginBottom: 8 },
  dialogoTexto: { fontSize: 14, color: '#444', lineHeight: 20 },
  dialogoAcciones: { flexDirection: 'row', justifyContent: 'flex-end', gap: 12, marginTop: 20 },
  dialogoBotonCancelar: { paddingVertical: 10, paddingHorizontal: 8 },
  dialogoCancelarTexto: { color: '#666', fontWeight: '600' },
  dialogoBotonConfirmar: {
    backgroundColor: '#dc3545',
    borderRadius: 8,
    paddingVertical: 10,
    paddingHorizontal: 16,
    minWidth: 170,
    alignItems: 'center',
  },
  dialogoConfirmarTexto: { color: '#fff', fontWeight: '700' },
});
