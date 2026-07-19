import React, { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Alert, StyleSheet, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { KeyboardAwareScrollView } from 'react-native-keyboard-aware-scroll-view';
import { useNavigation, useRoute } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RouteProp } from '@react-navigation/native';
import type { RootStackParamList } from '../navigation/RootNavigator';
import {
  actualizarCliente,
  Ciudad,
  consultarSri,
  crearCliente,
  obtenerCatalogosClientes,
  obtenerCiudadesPorProvincia,
  obtenerCliente,
  Provincia,
  TipoIdentificacion,
} from '../api/clientes';
import { mensajeError } from '../api/client';
import SelectorLista from '../components/SelectorLista';

const RUC = '04';
const CEDULA = '05';
const PASAPORTE = '06';

type EstadoSri = 'idle' | 'buscando' | 'encontrado' | 'no_encontrado' | 'error';

export default function ClienteFormScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const route = useRoute<RouteProp<RootStackParamList, 'ClienteForm'>>();
  const idCliente = route.params?.id;
  const editando = !!idCliente;

  const [cargando, setCargando] = useState(editando);
  const [guardando, setGuardando] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [tiposId, setTiposId] = useState<TipoIdentificacion[]>([]);
  const [provincias, setProvincias] = useState<Provincia[]>([]);
  const [ciudades, setCiudades] = useState<Ciudad[]>([]);

  const [tipoId, setTipoId] = useState<string | null>(null);
  const tipoIdTocado = useRef(false);
  const [identificacion, setIdentificacion] = useState('');
  const [nombre, setNombre] = useState('');
  const nombreTocado = useRef(false);
  const [email, setEmail] = useState('');
  const [telefono, setTelefono] = useState('');
  const [direccion, setDireccion] = useState('');
  const direccionTocado = useRef(false);
  const [provinciaCod, setProvinciaCod] = useState<string | null>(null);
  const provinciaTocada = useRef(false);
  const [ciudadCod, setCiudadCod] = useState<string | null>(null);

  const [sriEstado, setSriEstado] = useState<EstadoSri>('idle');
  const debounceSriRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    navigation.setOptions({ title: editando ? 'Editar cliente' : 'Nuevo cliente' });
  }, [navigation, editando]);

  useEffect(() => {
    obtenerCatalogosClientes()
      .then((data) => {
        setTiposId(data.tipos_id);
        setProvincias(data.provincias);
      })
      .catch(() => {
        setTiposId([]);
        setProvincias([]);
      });
  }, []);

  useEffect(() => {
    if (!editando || !idCliente) return;
    (async () => {
      try {
        const c = await obtenerCliente(idCliente);
        setTipoId(c.tipo_id);
        tipoIdTocado.current = true;
        setIdentificacion(c.identificacion);
        setNombre(c.nombre);
        nombreTocado.current = true;
        setEmail(c.email ?? '');
        setTelefono(c.telefono ?? '');
        setDireccion(c.direccion ?? '');
        direccionTocado.current = true;
        setProvinciaCod(c.provincia);
        provinciaTocada.current = true;
        setCiudadCod(c.ciudad);
      } catch (err) {
        setError(mensajeError(err, 'No se pudo cargar el cliente.'));
      } finally {
        setCargando(false);
      }
    })();
  }, [editando, idCliente]);

  useEffect(() => {
    if (!provinciaCod) {
      setCiudades([]);
      return;
    }
    obtenerCiudadesPorProvincia(provinciaCod)
      .then(setCiudades)
      .catch(() => setCiudades([]));
  }, [provinciaCod]);

  function onIdentificacionChange(texto: string) {
    setIdentificacion(texto);
    setSriEstado('idle');
    if (debounceSriRef.current) clearTimeout(debounceSriRef.current);

    const digitos = texto.replace(/\D/g, '');
    if (digitos.length !== 10 && digitos.length !== 13) return;

    debounceSriRef.current = setTimeout(async () => {
      setSriEstado('buscando');
      try {
        const resultado = await consultarSri(digitos);
        if (!resultado.ok || !resultado.data) {
          setSriEstado(resultado.error ? 'error' : 'no_encontrado');
          return;
        }
        setSriEstado('encontrado');

        if (!tipoIdTocado.current) {
          setTipoId(digitos.length === 13 ? RUC : CEDULA);
        }
        if (!nombreTocado.current && resultado.data.nombre) {
          setNombre(resultado.data.nombre);
        }
        if (!direccionTocado.current && resultado.data.direccion) {
          setDireccion(resultado.data.direccion);
        }
        if (resultado.data.cod_prov && !provinciaTocada.current) {
          // Cambiar provinciaCod dispara el useEffect que carga las ciudades de esa
          // provincia; una vez lleguen, el <SelectorLista> resuelve ciudadCod al nombre.
          setProvinciaCod(resultado.data.cod_prov);
          if (resultado.data.cod_ciudad) {
            setCiudadCod(resultado.data.cod_ciudad);
          }
        }
      } catch {
        setSriEstado('error');
      }
    }, 700);
  }

  function validar(): string | null {
    if (nombre.trim() === '') return 'El nombre es obligatorio.';
    if (!tipoId) return 'El tipo de identificación es obligatorio.';
    if (identificacion.trim() === '') return 'La identificación es obligatoria.';
    if (tipoId === CEDULA && !/^[0-9]{10}$/.test(identificacion)) return 'La cédula debe tener 10 dígitos.';
    if (tipoId === RUC && !/^[0-9]{13}$/.test(identificacion)) return 'El RUC debe tener 13 dígitos.';
    if (email.trim() === '') return 'El correo electrónico es obligatorio.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) return 'El formato del correo electrónico no es válido.';
    return null;
  }

  async function guardar() {
    const errorValidacion = validar();
    if (errorValidacion) {
      Alert.alert('Datos incompletos', errorValidacion);
      return;
    }

    setGuardando(true);
    setError(null);
    const input = {
      nombre: nombre.trim(),
      tipo_id: tipoId as string,
      identificacion: identificacion.trim(),
      email: email.trim(),
      telefono: telefono.trim() || undefined,
      direccion: direccion.trim() || undefined,
      provincia: provinciaCod ?? undefined,
      ciudad: ciudadCod ?? undefined,
    };
    try {
      if (editando && idCliente) {
        await actualizarCliente(idCliente, input);
        Alert.alert('Cliente actualizado', 'Los cambios se guardaron correctamente.', [
          { text: 'OK', onPress: () => navigation.goBack() },
        ]);
      } else {
        await crearCliente(input);
        Alert.alert('Cliente creado', 'El cliente se registró correctamente.', [
          { text: 'OK', onPress: () => navigation.goBack() },
        ]);
      }
    } catch (err) {
      setError(mensajeError(err, 'No se pudo guardar el cliente.'));
    } finally {
      setGuardando(false);
    }
  }

  if (cargando) {
    return (
      <View style={styles.centrado}>
        <ActivityIndicator size="large" color="#0d6efd" />
      </View>
    );
  }

  return (
    <KeyboardAwareScrollView
      style={styles.container}
      contentContainerStyle={{ padding: 16, paddingBottom: 40 }}
      enableOnAndroid
      extraScrollHeight={20}
    >
      {error ? <Text style={styles.error}>{error}</Text> : null}

      <SelectorLista<string>
        label="Tipo de identificación"
        value={tipoId}
        opciones={tiposId.map((t) => ({ id: t.codigo, label: t.nombre }))}
        onChange={(id) => {
          tipoIdTocado.current = true;
          setTipoId(id);
        }}
      />

      <View style={styles.campo}>
        <Text style={styles.label}>Identificación</Text>
        <View style={styles.filaIdentificacion}>
          <TextInput
            style={[styles.input, { flex: 1 }]}
            value={identificacion}
            onChangeText={onIdentificacionChange}
            keyboardType={tipoId === PASAPORTE ? 'default' : 'number-pad'}
            maxLength={tipoId === RUC ? 13 : tipoId === CEDULA ? 10 : 20}
            placeholder="Cédula, RUC o pasaporte"
          />
          {sriEstado === 'buscando' ? <ActivityIndicator size="small" color="#0d6efd" style={styles.sriIcono} /> : null}
        </View>
        {sriEstado === 'encontrado' ? <Text style={styles.sriOk}>Datos completados desde el SRI.</Text> : null}
        {sriEstado === 'no_encontrado' ? <Text style={styles.sriInfo}>No se encontró en el SRI, complete manualmente.</Text> : null}
        {sriEstado === 'error' ? <Text style={styles.sriError}>No se pudo consultar el SRI, complete manualmente.</Text> : null}
      </View>

      <View style={styles.campo}>
        <Text style={styles.label}>Nombre / Razón social</Text>
        <TextInput
          style={styles.input}
          value={nombre}
          onChangeText={(t) => {
            nombreTocado.current = true;
            setNombre(t);
          }}
          placeholder="Nombre completo o razón social"
        />
      </View>

      <View style={styles.campo}>
        <Text style={styles.label}>Correo electrónico</Text>
        <TextInput
          style={styles.input}
          value={email}
          onChangeText={setEmail}
          keyboardType="email-address"
          autoCapitalize="none"
          placeholder="correo@ejemplo.com"
        />
      </View>

      <View style={styles.campo}>
        <Text style={styles.label}>Teléfono</Text>
        <TextInput style={styles.input} value={telefono} onChangeText={setTelefono} keyboardType="phone-pad" />
      </View>

      <View style={styles.campo}>
        <Text style={styles.label}>Dirección</Text>
        <TextInput
          style={styles.input}
          value={direccion}
          onChangeText={(t) => {
            direccionTocado.current = true;
            setDireccion(t);
          }}
          multiline
        />
      </View>

      <SelectorLista<string>
        label="Provincia"
        value={provinciaCod}
        opciones={provincias.map((p) => ({ id: p.codigo, label: p.nombre }))}
        onChange={(id) => {
          provinciaTocada.current = true;
          setProvinciaCod(id);
          setCiudadCod(null);
        }}
      />

      <View style={{ marginTop: 12 }}>
        <SelectorLista<string>
          label="Ciudad"
          value={ciudadCod}
          opciones={ciudades.map((c) => ({ id: c.codigo, label: c.nombre }))}
          onChange={setCiudadCod}
          placeholder={provinciaCod ? 'Seleccionar...' : 'Elige primero una provincia'}
        />
      </View>

      <TouchableOpacity style={styles.botonGuardar} onPress={guardar} disabled={guardando}>
        {guardando ? <ActivityIndicator color="#fff" /> : <Text style={styles.botonGuardarTexto}>Guardar</Text>}
      </TouchableOpacity>
    </KeyboardAwareScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f6f8' },
  centrado: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  error: { color: '#dc3545', marginBottom: 12, textAlign: 'center' },
  campo: { marginTop: 12 },
  label: { fontSize: 13, color: '#333', marginBottom: 4, fontWeight: '600' },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    backgroundColor: '#fff',
    fontSize: 15,
  },
  filaIdentificacion: { flexDirection: 'row', alignItems: 'center' },
  sriIcono: { marginLeft: 8 },
  sriOk: { color: '#198754', fontSize: 12, marginTop: 4 },
  sriInfo: { color: '#888', fontSize: 12, marginTop: 4 },
  sriError: { color: '#dc3545', fontSize: 12, marginTop: 4 },
  botonGuardar: { backgroundColor: '#0d6efd', borderRadius: 8, paddingVertical: 14, marginTop: 24, alignItems: 'center' },
  botonGuardarTexto: { color: '#fff', fontSize: 16, fontWeight: '600' },
});
