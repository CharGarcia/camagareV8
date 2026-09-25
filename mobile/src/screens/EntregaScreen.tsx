import React, { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Alert, ScrollView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import * as Location from 'expo-location';
import SignatureView, { SignatureViewRef } from 'react-native-signature-canvas';
import { useNavigation, useRoute } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RouteProp } from '@react-navigation/native';
import type { RootStackParamList } from '../navigation/RootNavigator';
import { ConsignacionDetalle, obtenerConsignacion, registrarEntrega } from '../api/entregas';
import { codigoError, mensajeError } from '../api/client';
import { generarUuid } from '../utils/uuid';
import { getOrCreateDispositivoId } from '../auth/tokenStore';

type Ubicacion = { latitud: number; longitud: number; precision: number | null };

// Precisión (m) con la que se deja de muestrear, tiempo máximo de muestreo y umbral a
// partir del cual se avisa que la ubicación es aproximada. Mismos valores que el módulo
// web (public/js/modulos/entregas_consignaciones.js).
const GPS_PRECISION_OBJETIVO = 20;
const GPS_TIEMPO_MAX_MS = 20000;
const GPS_PRECISION_AVISO = 100;

function fechaHoraLocalSQL(): string {
  const d = new Date();
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const dia = String(d.getDate()).padStart(2, '0');
  const h = String(d.getHours()).padStart(2, '0');
  const min = String(d.getMinutes()).padStart(2, '0');
  const s = String(d.getSeconds()).padStart(2, '0');
  return `${y}-${m}-${dia} ${h}:${min}:${s}`;
}

export default function EntregaScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const route = useRoute<RouteProp<RootStackParamList, 'Entrega'>>();
  const { id } = route.params;

  const [cargando, setCargando] = useState(true);
  const [consignacion, setConsignacion] = useState<ConsignacionDetalle | null>(null);
  const [error, setError] = useState<string | null>(null);

  const [ubicacion, setUbicacion] = useState<Ubicacion | null>(null);
  const [obteniendoUbicacion, setObteniendoUbicacion] = useState(false);
  const [errorUbicacion, setErrorUbicacion] = useState<string | null>(null);

  const [guardando, setGuardando] = useState(false);
  const firmaRef = useRef<SignatureViewRef>(null);
  const uuidRef = useRef(generarUuid());

  useEffect(() => {
    (async () => {
      try {
        setConsignacion(await obtenerConsignacion(id));
      } catch (err) {
        setError(mensajeError(err, 'No se pudo cargar la consignación.'));
      } finally {
        setCargando(false);
      }
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  // Muestreo GPS en curso (suscripción + temporizador), para poder cortarlo al lograr la
  // precisión objetivo, al reintentar o al salir de la pantalla.
  // `id` identifica el muestreo vigente: una suscripción que termina de crearse después de
  // que ese muestreo acabó (o fue reemplazado por "Actualizar ubicación") se descarta.
  const muestreoRef = useRef<{ id: number; sub: Location.LocationSubscription | null; timer: ReturnType<typeof setTimeout> | null }>({
    id: 0,
    sub: null,
    timer: null,
  });

  function detenerMuestreo() {
    const m = muestreoRef.current;
    m.sub?.remove();
    if (m.timer) clearTimeout(m.timer);
    muestreoRef.current = { id: m.id + 1, sub: null, timer: null };
  }

  useEffect(() => {
    capturarUbicacion();
    return detenerMuestreo;
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // No usa getCurrentPositionAsync(): devuelve UNA lectura, a menudo la aproximada por
  // red (cientos de metros) porque el GPS aún no fija satélites. Con watchPositionAsync se
  // muestrea hasta lograr GPS_PRECISION_OBJETIVO o agotar GPS_TIEMPO_MAX_MS, mostrando en
  // pantalla la mejor lectura recibida hasta el momento.
  async function capturarUbicacion() {
    detenerMuestreo();
    const idMuestreo = muestreoRef.current.id;
    const vigente = () => muestreoRef.current.id === idMuestreo;
    setErrorUbicacion(null);
    setUbicacion(null);
    setObteniendoUbicacion(true);
    try {
      const permiso = await Location.requestForegroundPermissionsAsync();
      if (!vigente()) return;
      if (permiso.status !== 'granted') {
        setErrorUbicacion('Necesitamos permiso de ubicación para registrar la entrega.');
        setObteniendoUbicacion(false);
        return;
      }
      const gpsActivo = await Location.hasServicesEnabledAsync();
      if (!vigente()) return;
      if (!gpsActivo) {
        setErrorUbicacion('El GPS está desactivado. Actívalo y toca "Actualizar ubicación".');
        setObteniendoUbicacion(false);
        return;
      }

      let mejor: Ubicacion | null = null;
      const terminar = () => {
        if (!vigente()) return;
        detenerMuestreo();
        setObteniendoUbicacion(false);
        if (!mejor) {
          setErrorUbicacion('No se pudo obtener la ubicación. Verifica que el GPS esté activado e intenta de nuevo.');
        }
      };

      muestreoRef.current.timer = setTimeout(terminar, GPS_TIEMPO_MAX_MS);
      const sub = await Location.watchPositionAsync(
        { accuracy: Location.Accuracy.BestForNavigation, timeInterval: 1000, distanceInterval: 0 },
        (pos) => {
          if (!vigente()) return;
          const precision = pos.coords.accuracy ?? null;
          if (!mejor || (precision != null && (mejor.precision == null || precision < mejor.precision))) {
            mejor = { latitud: pos.coords.latitude, longitud: pos.coords.longitude, precision };
            setUbicacion(mejor);
          }
          if (precision != null && precision <= GPS_PRECISION_OBJETIVO) terminar();
        }
      );
      // Si el muestreo ya terminó (temporizador, precisión lograda, reintento o salida de la
      // pantalla) mientras se creaba la suscripción, se descarta.
      if (vigente()) muestreoRef.current.sub = sub;
      else sub.remove();
    } catch {
      if (!vigente()) return;
      detenerMuestreo();
      setErrorUbicacion('No se pudo obtener la ubicación. Verifica que el GPS esté activado e intenta de nuevo.');
      setObteniendoUbicacion(false);
    }
  }

  async function confirmarEntrega(firmaBase64?: string) {
    if (!ubicacion) {
      Alert.alert('Falta la ubicación', 'Espera a que se capture la ubicación GPS, o toca "Actualizar ubicación".');
      return;
    }
    if (ubicacion.precision != null && ubicacion.precision > GPS_PRECISION_AVISO) {
      const registrarIgual = await new Promise<boolean>((resolve) =>
        Alert.alert(
          'Ubicación aproximada',
          `La precisión es de ±${Math.round(ubicacion.precision!)} m, así que el punto puede no ser exacto. ` +
            'Sal a un lugar abierto y toca "Actualizar ubicación" para mejorarla.',
          [
            { text: 'Cancelar', style: 'cancel', onPress: () => resolve(false) },
            { text: 'Registrar igual', onPress: () => resolve(true) },
          ],
          { cancelable: true, onDismiss: () => resolve(false) }
        )
      );
      if (!registrarIgual) return;
    }
    setGuardando(true);
    setError(null);
    try {
      const dispositivoId = await getOrCreateDispositivoId();
      const resultado = await registrarEntrega({
        id_consignacion: id,
        uuid_cliente: uuidRef.current,
        capturado_en: fechaHoraLocalSQL(),
        latitud: ubicacion.latitud,
        longitud: ubicacion.longitud,
        precision_m: ubicacion.precision ?? undefined,
        firma_base64: firmaBase64,
        dispositivo_id: dispositivoId,
      });
      Alert.alert(
        'Entrega registrada',
        resultado.ya_entregada ? 'Esta entrega ya había quedado registrada.' : 'Se registró la entrega correctamente.',
        [{ text: 'OK', onPress: () => navigation.goBack() }]
      );
    } catch (err) {
      if (codigoError(err) === 'NO_ADMITE_ENTREGA') {
        Alert.alert('No se pudo registrar', mensajeError(err));
      } else {
        setError(mensajeError(err, 'No se pudo registrar la entrega.'));
      }
    } finally {
      setGuardando(false);
    }
  }

  // La firma es opcional: si el pad está vacío, se confirma la entrega igual (sin firma)
  // en vez de bloquear con un aviso.
  function onConfirmarPress() {
    firmaRef.current?.readSignature();
  }

  if (cargando) {
    return (
      <View style={styles.centrado}>
        <ActivityIndicator size="large" color="#0d6efd" />
      </View>
    );
  }

  if (!consignacion) {
    return (
      <View style={styles.centrado}>
        <Text style={styles.error}>{error ?? 'No se pudo cargar la consignación.'}</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <ScrollView style={{ flex: 1 }} contentContainerStyle={{ padding: 16 }} keyboardShouldPersistTaps="handled">
        {error ? <Text style={styles.error}>{error}</Text> : null}

        <View style={styles.bloque}>
          <Text style={styles.numero}>
            {consignacion.serie}-{consignacion.secuencial}
          </Text>
          <Text style={styles.cliente}>{consignacion.cliente_nombre}</Text>
          {consignacion.cliente_direccion ? <Text style={styles.dato}>{consignacion.cliente_direccion}</Text> : null}
          {consignacion.punto_llegada ? <Text style={styles.dato}>Destino: {consignacion.punto_llegada}</Text> : null}
        </View>

        <Text style={styles.tituloSeccion}>Productos</Text>
        {consignacion.detalles.map((d, i) => (
          <View key={i} style={styles.linea}>
            <Text style={styles.lineaNombre}>{d.producto_nombre}</Text>
            <Text style={styles.lineaSub}>Cantidad: {d.cantidad}</Text>
          </View>
        ))}

        <Text style={styles.tituloSeccion}>Ubicación de la entrega</Text>
        <View style={styles.ubicacionBox}>
          {obteniendoUbicacion ? (
            <View style={styles.ubicacionMuestreo}>
              <ActivityIndicator color="#0d6efd" />
              <Text style={styles.ubicacionTextoMuestreo}>
                {ubicacion?.precision != null
                  ? `Afinando GPS… precisión ±${Math.round(ubicacion.precision)} m`
                  : 'Esperando señal GPS…'}
              </Text>
            </View>
          ) : ubicacion ? (
            <View style={{ flex: 1 }}>
              <Text style={styles.ubicacionTexto}>
                {ubicacion.latitud.toFixed(6)}, {ubicacion.longitud.toFixed(6)}
                {ubicacion.precision != null ? ` (±${Math.round(ubicacion.precision)}m)` : ''}
              </Text>
              {ubicacion.precision != null && ubicacion.precision > GPS_PRECISION_AVISO ? (
                <Text style={styles.avisoPrecision}>Ubicación aproximada: sal a un lugar abierto y actualízala.</Text>
              ) : null}
            </View>
          ) : (
            <Text style={styles.error}>{errorUbicacion ?? 'Sin ubicación'}</Text>
          )}
          <TouchableOpacity onPress={capturarUbicacion} disabled={obteniendoUbicacion}>
            <Text style={styles.reintentar}>Actualizar ubicación</Text>
          </TouchableOpacity>
        </View>
      </ScrollView>

      {/* Fuera del ScrollView a propósito: si la pantalla se mueve mientras se dibuja
          la firma, el trazo queda deformado (el dedo y el dibujo se desalinean). */}
      <View style={styles.firmaSeccionFija}>
        <Text style={styles.tituloSeccion}>Firma de quien recibe (opcional)</Text>
        <View style={styles.firmaBox}>
          <SignatureView
            ref={firmaRef}
            onOK={confirmarEntrega}
            onEmpty={() => confirmarEntrega(undefined)}
            descriptionText=""
            webStyle={firmaWebStyle}
          />
        </View>

        <TouchableOpacity
          style={[styles.botonGuardar, (obteniendoUbicacion || !ubicacion) && styles.botonDeshabilitado]}
          onPress={onConfirmarPress}
          disabled={guardando || obteniendoUbicacion || !ubicacion}
        >
          {guardando ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.botonGuardarTexto}>{obteniendoUbicacion ? 'Obteniendo ubicación…' : 'Confirmar entrega'}</Text>
          )}
        </TouchableOpacity>
      </View>
    </View>
  );
}

const firmaWebStyle = `
  .m-signature-pad--footer { display: none; margin: 0; }
  .m-signature-pad--body { border: none; }
  .m-signature-pad { box-shadow: none; border: none; height: 100%; }
  body,html { background-color: #f5f6f8; height: 100%; }
`;

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f6f8' },
  centrado: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 24 },
  error: { color: '#dc3545', marginBottom: 12, textAlign: 'center' },
  bloque: { backgroundColor: '#fff', borderRadius: 10, padding: 16, marginBottom: 16 },
  numero: { fontSize: 12, color: '#888' },
  cliente: { fontSize: 17, fontWeight: '700', marginTop: 2 },
  dato: { fontSize: 14, color: '#444', marginTop: 2 },
  tituloSeccion: { fontSize: 15, fontWeight: '700', marginTop: 8, marginBottom: 8 },
  linea: { backgroundColor: '#fff', borderRadius: 8, padding: 12, marginBottom: 8 },
  lineaNombre: { fontSize: 14, fontWeight: '600' },
  lineaSub: { fontSize: 13, color: '#666', marginTop: 2 },
  ubicacionBox: {
    backgroundColor: '#fff',
    borderRadius: 10,
    padding: 14,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  ubicacionTexto: { fontSize: 14, color: '#333', flex: 1 },
  ubicacionMuestreo: { flexDirection: 'row', alignItems: 'center', flex: 1 },
  ubicacionTextoMuestreo: { fontSize: 13, color: '#555', marginLeft: 8, flex: 1 },
  avisoPrecision: { fontSize: 12, color: '#b58105', marginTop: 4 },
  reintentar: { color: '#0d6efd', fontWeight: '600', fontSize: 13, marginLeft: 8 },
  firmaSeccionFija: {
    backgroundColor: '#f5f6f8',
    paddingHorizontal: 16,
    paddingBottom: 16,
    paddingTop: 4,
    borderTopWidth: 1,
    borderTopColor: '#e3e3e3',
  },
  firmaBox: {
    backgroundColor: '#fff',
    borderRadius: 10,
    height: 160,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: '#ddd',
  },
  botonGuardar: { backgroundColor: '#0d6efd', borderRadius: 8, paddingVertical: 14, marginTop: 20, alignItems: 'center' },
  botonDeshabilitado: { opacity: 0.6 },
  botonGuardarTexto: { color: '#fff', fontSize: 16, fontWeight: '600' },
});
