import React, { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { CameraView, useCameraPermissions, type BarcodeScanningResult } from 'expo-camera';
import * as Location from 'expo-location';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RootStackParamList } from '../navigation/RootNavigator';
import { obtenerInfoQr, registrarMarcacion, TipoMarcacion } from '../api/asistencia';
import { clearCredencialAsistencia, CredencialAsistencia, getCredencialAsistencia, setCredencialAsistencia } from '../auth/tokenStore';

/**
 * Marcación de entrada/salida por QR + selfie, reutilizando tal cual los endpoints públicos
 * de App\controllers\AsistenciaController (los mismos que usa app/views/publica/asistencia/marcar.php
 * desde el navegador). Deliberadamente SIN pasar por el login de `usuarios` de la app: el personal
 * que marca (ej. guardias) no necesariamente tiene cuenta en el sistema — se identifica por el
 * token QR de su credencial personal (empleados_biometria), igual que hoy en la web.
 */

type Paso = 'cargando' | 'vincular' | 'listo' | 'escaneo' | 'marcando' | 'resultado';
type ModoEscaneo = 'empleado' | 'punto';
type PuntoInfo = { token: string; nombre: string; exigeGps: boolean };
type Resultado = { estado: 'ok' | 'warn' | 'err'; titulo: string; texto: string };

/** El QR codifica una URL completa ({BASE}/asistencia/app?e=... o ?p=...); si no se puede
 * extraer el parámetro (p. ej. el usuario pegó el código a mano), se usa el texto tal cual. */
function extraerToken(raw: string, param: 'e' | 'p'): string {
  const texto = raw.trim();
  const match = texto.match(new RegExp(`[?&]${param}=([^&]+)`));
  if (!match) return texto;
  try {
    return decodeURIComponent(match[1]);
  } catch {
    return match[1];
  }
}

export default function AsistenciaScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  const [paso, setPaso] = useState<Paso>('cargando');
  const [modoEscaneo, setModoEscaneo] = useState<ModoEscaneo>('empleado');
  const [credencial, setCredencial] = useState<CredencialAsistencia | null>(null);
  const [puntoInfo, setPuntoInfo] = useState<PuntoInfo | null>(null);
  const [resultado, setResultado] = useState<Resultado | null>(null);
  const [procesando, setProcesando] = useState(false);
  const [tokenManual, setTokenManual] = useState('');
  const [errorVincular, setErrorVincular] = useState<string | null>(null);

  const [permiso, solicitarPermiso] = useCameraPermissions();
  const cameraRef = useRef<CameraView | null>(null);
  const scanLockRef = useRef(false);

  useEffect(() => {
    (async () => {
      const guardada = await getCredencialAsistencia();
      if (!guardada) {
        setPaso('vincular');
        return;
      }
      // Revalida por si el token fue revocado (regenerarToken) desde que se guardó.
      try {
        const info = await obtenerInfoQr({ e: guardada.token });
        if (info.valido) {
          setCredencial({ token: guardada.token, nombre: info.nombre ?? guardada.nombre });
          setPaso('listo');
        } else {
          await clearCredencialAsistencia();
          setErrorVincular('Tu credencial fue revocada. Vincula una nueva.');
          setPaso('vincular');
        }
      } catch {
        // Sin conexión: se usa la credencial guardada tal cual, se validará al marcar.
        setCredencial(guardada);
        setPaso('listo');
      }
    })();
  }, []);

  function abrirEscaneo(modo: ModoEscaneo) {
    scanLockRef.current = false;
    setErrorVincular(null);
    setModoEscaneo(modo);
    setPaso('escaneo');
  }

  async function procesarTokenEmpleado(token: string) {
    setProcesando(true);
    try {
      const info = await obtenerInfoQr({ e: token });
      if (!info.valido) {
        setErrorVincular('Este código no corresponde a ninguna credencial activa.');
        scanLockRef.current = false;
        setPaso('vincular');
        return;
      }
      const cred: CredencialAsistencia = { token, nombre: info.nombre ?? '' };
      await setCredencialAsistencia(cred);
      setCredencial(cred);
      setPaso('listo');
    } catch {
      setErrorVincular('No se pudo validar la credencial. Revisa tu conexión e inténtalo de nuevo.');
      scanLockRef.current = false;
      setPaso('vincular');
    } finally {
      setProcesando(false);
    }
  }

  async function procesarTokenPunto(token: string) {
    setProcesando(true);
    try {
      const info = await obtenerInfoQr({ p: token });
      if (!info.valido) {
        setErrorVincular('Este QR de punto de servicio no es válido o está inactivo.');
        scanLockRef.current = false;
        setPaso('listo');
        return;
      }
      setPuntoInfo({ token, nombre: info.nombre ?? '', exigeGps: !!info.exige_gps });
      setPaso('marcando');
    } catch {
      setErrorVincular('No se pudo leer el punto de servicio. Revisa tu conexión e inténtalo de nuevo.');
      scanLockRef.current = false;
      setPaso('listo');
    } finally {
      setProcesando(false);
    }
  }

  function onBarcodeScanned(result: BarcodeScanningResult) {
    if (scanLockRef.current) return;
    scanLockRef.current = true;
    const token = extraerToken(result.data, modoEscaneo === 'empleado' ? 'e' : 'p');
    if (modoEscaneo === 'empleado') {
      procesarTokenEmpleado(token);
    } else {
      procesarTokenPunto(token);
    }
  }

  function guardarTokenManual() {
    const t = tokenManual.trim();
    if (!t) return;
    setTokenManual('');
    procesarTokenEmpleado(t);
  }

  async function cambiarCredencial() {
    await clearCredencialAsistencia();
    setCredencial(null);
    setPaso('vincular');
  }

  async function marcar(tipo: TipoMarcacion) {
    if (!credencial || !puntoInfo) return;
    setProcesando(true);
    try {
      let latitud: number | undefined;
      let longitud: number | undefined;
      try {
        const permisoUbi = await Location.requestForegroundPermissionsAsync();
        if (permisoUbi.status === 'granted') {
          const pos = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });
          latitud = pos.coords.latitude;
          longitud = pos.coords.longitude;
        }
      } catch {
        // Sin GPS disponible: se intenta igual: el servidor solo lo exige si el punto lo requiere.
      }

      if (puntoInfo.exigeGps && (latitud === undefined || longitud === undefined)) {
        setResultado({ estado: 'err', titulo: 'Ubicación requerida', texto: 'Activa el GPS y permite la ubicación para marcar en este punto.' });
        return;
      }

      let selfie: string | undefined;
      try {
        const foto = await cameraRef.current?.takePictureAsync({ base64: true, quality: 0.5 });
        if (foto?.base64) selfie = `data:image/jpeg;base64,${foto.base64}`;
      } catch {
        // Sin selfie: se marca solo con QR (+GPS si hay), igual que hace la web sin cámara.
      }

      const res = await registrarMarcacion({ tokenEmpleado: credencial.token, tokenPunto: puntoInfo.token, tipo, latitud, longitud, selfie });

      if (!res.ok) {
        setResultado({ estado: 'err', titulo: 'No se registró', texto: res.error || 'Error desconocido.' });
      } else if (res.estado === 'sospechosa') {
        setResultado({
          estado: 'warn',
          titulo: `${(res.tipo ?? tipo) === 'entrada' ? 'Entrada' : 'Salida'} registrada`,
          texto: `Quedó marcada como SOSPECHOSA: ${res.observacion || 'fuera de la ubicación del punto.'}`,
        });
      } else {
        setResultado({
          estado: 'ok',
          titulo: `${(res.tipo ?? tipo) === 'entrada' ? 'Entrada' : 'Salida'} registrada`,
          texto: `¡Gracias, ${credencial.nombre}!`,
        });
      }
    } catch {
      setResultado({ estado: 'err', titulo: 'Sin conexión', texto: 'No se pudo registrar. Revisa tu señal e inténtalo de nuevo.' });
    } finally {
      setProcesando(false);
      setPaso('resultado');
    }
  }

  function aceptarResultado() {
    setResultado(null);
    setPuntoInfo(null);
    setPaso('listo');
  }

  if (paso === 'cargando') {
    return (
      <View style={styles.centrado}>
        <ActivityIndicator size="large" color="#0d6efd" />
      </View>
    );
  }

  if (paso === 'vincular') {
    return (
      <View style={styles.centrado}>
        <View style={styles.panel}>
          <Text style={styles.panelTitulo}>Identifícate</Text>
          <Text style={styles.panelTexto}>Este celular aún no tiene tu credencial. Escanea tu código personal o pégalo abajo.</Text>
          {errorVincular ? <Text style={styles.error}>{errorVincular}</Text> : null}
          <TouchableOpacity style={styles.botonPrimario} onPress={() => abrirEscaneo('empleado')} disabled={procesando}>
            <Text style={styles.botonPrimarioTexto}>Escanear mi credencial</Text>
          </TouchableOpacity>
          <Text style={styles.oSeparador}>— o —</Text>
          <TextInput
            style={styles.input}
            value={tokenManual}
            onChangeText={setTokenManual}
            placeholder="EMP-..."
            autoCapitalize="none"
            autoCorrect={false}
          />
          <TouchableOpacity style={styles.botonSecundario} onPress={guardarTokenManual} disabled={procesando || !tokenManual.trim()}>
            {procesando ? <ActivityIndicator color="#0d6efd" /> : <Text style={styles.botonSecundarioTexto}>Guardar credencial</Text>}
          </TouchableOpacity>
          <TouchableOpacity style={{ marginTop: 20 }} onPress={() => navigation.goBack()}>
            <Text style={styles.volver}>Volver</Text>
          </TouchableOpacity>
        </View>
      </View>
    );
  }

  if (paso === 'listo') {
    return (
      <View style={styles.centrado}>
        <View style={styles.panel}>
          <Text style={styles.panelTitulo}>Marcando como</Text>
          <Text style={styles.nombreEmpleado}>{credencial?.nombre || 'Empleado'}</Text>
          {errorVincular ? <Text style={styles.error}>{errorVincular}</Text> : null}
          <TouchableOpacity style={styles.botonPrimario} onPress={() => abrirEscaneo('punto')} disabled={procesando}>
            {procesando ? <ActivityIndicator color="#fff" /> : <Text style={styles.botonPrimarioTexto}>Escanear punto de servicio</Text>}
          </TouchableOpacity>
          <TouchableOpacity style={{ marginTop: 20 }} onPress={cambiarCredencial}>
            <Text style={styles.volver}>Cambiar credencial</Text>
          </TouchableOpacity>
        </View>
      </View>
    );
  }

  if (paso === 'escaneo') {
    if (!permiso) {
      return (
        <View style={styles.centrado}>
          <ActivityIndicator size="large" color="#0d6efd" />
        </View>
      );
    }
    if (!permiso.granted) {
      return (
        <View style={styles.centrado}>
          <View style={styles.panel}>
            <Text style={styles.panelTitulo}>Permiso de cámara</Text>
            <Text style={styles.panelTexto}>Necesitamos la cámara para escanear el código QR.</Text>
            <TouchableOpacity style={styles.botonPrimario} onPress={solicitarPermiso}>
              <Text style={styles.botonPrimarioTexto}>Dar permiso</Text>
            </TouchableOpacity>
            <TouchableOpacity style={{ marginTop: 20 }} onPress={() => setPaso(modoEscaneo === 'empleado' ? 'vincular' : 'listo')}>
              <Text style={styles.volver}>Cancelar</Text>
            </TouchableOpacity>
          </View>
        </View>
      );
    }
    return (
      <View style={{ flex: 1, backgroundColor: '#000' }}>
        <CameraView
          style={{ flex: 1 }}
          facing="back"
          barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
          onBarcodeScanned={onBarcodeScanned}
        />
        <View style={styles.escaneoOverlay} pointerEvents="box-none">
          <Text style={styles.escaneoTexto}>
            {modoEscaneo === 'empleado' ? 'Escanea tu credencial personal' : 'Escanea el QR del punto de servicio'}
          </Text>
          {procesando ? <ActivityIndicator color="#fff" style={{ marginTop: 12 }} /> : null}
          <TouchableOpacity
            style={styles.escaneoCancelar}
            onPress={() => setPaso(modoEscaneo === 'empleado' ? 'vincular' : 'listo')}
          >
            <Text style={styles.escaneoCancelarTexto}>Cancelar</Text>
          </TouchableOpacity>
        </View>
      </View>
    );
  }

  if (paso === 'marcando') {
    if (!permiso?.granted) {
      return (
        <View style={styles.centrado}>
          <Text style={styles.error}>Falta el permiso de cámara.</Text>
        </View>
      );
    }
    return (
      <View style={{ flex: 1, backgroundColor: '#10131a' }}>
        <View style={styles.marcandoHeader}>
          <Text style={styles.marcandoPunto}>{puntoInfo?.nombre}</Text>
          <Text style={styles.marcandoSub}>Marcación de asistencia</Text>
        </View>
        <View style={styles.camaraBox}>
          <CameraView ref={cameraRef} style={{ flex: 1 }} facing="front" />
        </View>
        <Text style={styles.marcandoWho}>
          Marca: <Text style={{ fontWeight: '700', color: '#66b0ff' }}>{credencial?.nombre}</Text>
        </Text>
        <View style={styles.marcandoBotones}>
          <TouchableOpacity style={[styles.botonMarcar, styles.botonEntrada]} onPress={() => marcar('entrada')} disabled={procesando}>
            <Text style={styles.botonMarcarTexto}>Entrada</Text>
          </TouchableOpacity>
          <TouchableOpacity style={[styles.botonMarcar, styles.botonSalida]} onPress={() => marcar('salida')} disabled={procesando}>
            <Text style={styles.botonMarcarTexto}>Salida</Text>
          </TouchableOpacity>
        </View>
        {procesando ? <ActivityIndicator color="#fff" style={{ marginTop: 10 }} /> : null}
        <TouchableOpacity style={{ alignItems: 'center', marginTop: 10 }} onPress={() => setPaso('listo')} disabled={procesando}>
          <Text style={[styles.volver, { color: '#aab' }]}>Cancelar</Text>
        </TouchableOpacity>
      </View>
    );
  }

  // paso === 'resultado'
  const colorResultado = resultado?.estado === 'ok' ? '#1a9c53' : resultado?.estado === 'warn' ? '#e0a800' : '#d13438';
  const icono = resultado?.estado === 'ok' ? '✓' : resultado?.estado === 'warn' ? '!' : '✕';
  return (
    <View style={styles.resultadoOverlay}>
      <View style={[styles.resultadoIcono, { backgroundColor: colorResultado }]}>
        <Text style={styles.resultadoIconoTexto}>{icono}</Text>
      </View>
      <Text style={styles.resultadoTitulo}>{resultado?.titulo}</Text>
      <Text style={styles.resultadoTexto}>{resultado?.texto}</Text>
      <TouchableOpacity style={styles.botonPrimario} onPress={aceptarResultado}>
        <Text style={styles.botonPrimarioTexto}>Aceptar</Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  centrado: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#f5f6f8', padding: 20 },
  panel: { backgroundColor: '#fff', borderRadius: 16, padding: 24, width: '100%', maxWidth: 360, alignItems: 'center' },
  panelTitulo: { fontSize: 18, fontWeight: '700', marginBottom: 8, textAlign: 'center' },
  panelTexto: { fontSize: 14, color: '#555', textAlign: 'center', marginBottom: 16 },
  nombreEmpleado: { fontSize: 20, fontWeight: '700', color: '#0d6efd', textAlign: 'center', marginBottom: 20 },
  error: { color: '#dc3545', textAlign: 'center', marginBottom: 12, fontSize: 13 },
  botonPrimario: { backgroundColor: '#0d6efd', borderRadius: 12, paddingVertical: 16, paddingHorizontal: 24, alignItems: 'center', width: '100%' },
  botonPrimarioTexto: { color: '#fff', fontSize: 16, fontWeight: '700' },
  oSeparador: { color: '#999', fontSize: 12, marginVertical: 14 },
  input: { borderWidth: 1, borderColor: '#ccc', borderRadius: 10, paddingHorizontal: 12, paddingVertical: 10, fontSize: 15, width: '100%', marginBottom: 10 },
  botonSecundario: { borderWidth: 1, borderColor: '#0d6efd', borderRadius: 10, paddingVertical: 13, alignItems: 'center', width: '100%' },
  botonSecundarioTexto: { color: '#0d6efd', fontWeight: '700' },
  volver: { color: '#666', fontWeight: '600' },
  escaneoOverlay: { position: 'absolute', bottom: 0, left: 0, right: 0, alignItems: 'center', padding: 24 },
  escaneoTexto: { color: '#fff', fontSize: 15, fontWeight: '600', textAlign: 'center', backgroundColor: 'rgba(0,0,0,0.5)', padding: 10, borderRadius: 10 },
  escaneoCancelar: { marginTop: 16, backgroundColor: 'rgba(255,255,255,0.9)', paddingVertical: 12, paddingHorizontal: 28, borderRadius: 12 },
  escaneoCancelarTexto: { color: '#222', fontWeight: '700' },
  marcandoHeader: { padding: 16, alignItems: 'center', backgroundColor: '#0d6efd' },
  marcandoPunto: { color: '#fff', fontWeight: '700', fontSize: 17 },
  marcandoSub: { color: '#fff', opacity: 0.85, fontSize: 12, marginTop: 2 },
  camaraBox: { flex: 1, margin: 18, borderRadius: 18, overflow: 'hidden', backgroundColor: '#000' },
  marcandoWho: { color: '#fff', textAlign: 'center', fontSize: 15, marginBottom: 12 },
  marcandoBotones: { flexDirection: 'row', gap: 12, paddingHorizontal: 18 },
  botonMarcar: { flex: 1, borderRadius: 14, paddingVertical: 18, alignItems: 'center' },
  botonEntrada: { backgroundColor: '#1a9c53' },
  botonSalida: { backgroundColor: '#d13438' },
  botonMarcarTexto: { color: '#fff', fontSize: 16, fontWeight: '700' },
  resultadoOverlay: { flex: 1, backgroundColor: '#10131a', alignItems: 'center', justifyContent: 'center', padding: 30 },
  resultadoIcono: { width: 96, height: 96, borderRadius: 48, alignItems: 'center', justifyContent: 'center', marginBottom: 18 },
  resultadoIconoTexto: { fontSize: 48, color: '#fff', fontWeight: '700' },
  resultadoTitulo: { color: '#fff', fontSize: 19, fontWeight: '700', marginBottom: 6, textAlign: 'center' },
  resultadoTexto: { color: '#cbd', fontSize: 14, textAlign: 'center', marginBottom: 26 },
});
