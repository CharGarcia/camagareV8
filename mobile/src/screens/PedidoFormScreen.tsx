import React, { useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { KeyboardAwareScrollView } from 'react-native-keyboard-aware-scroll-view';
import { useNavigation, useRoute } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RouteProp } from '@react-navigation/native';
import type { RootStackParamList } from '../navigation/RootNavigator';
import {
  buscarClientes,
  buscarProductos,
  ClienteBusqueda,
  crearPedido,
  DetallePedidoInput,
  obtenerPedido,
  obtenerResponsables,
  obtenerSecuencial,
  ProductoBusqueda,
  ResponsableTraslado,
  Secuencial,
} from '../api/pedidos';
import { codigoError, mensajeError } from '../api/client';
import { useSerie } from '../pedidos/SerieContext';
import SelectorFechaHora from '../components/SelectorFechaHora';
import SelectorRangoHoras from '../components/SelectorRangoHoras';
import SelectorLista from '../components/SelectorLista';

const IVA_DEFAULT = 0.15; // Simplificación v1: tarifa estándar vigente (15%). No se muestra al usuario.

type LineaDetalle = DetallePedidoInput & { producto_nombre: string };

function fechaLocalISO(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const dia = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${dia}`;
}

function horaLocalHHMM(d: Date): string {
  const h = String(d.getHours()).padStart(2, '0');
  const min = String(d.getMinutes()).padStart(2, '0');
  return `${h}:${min}`;
}

function sumarMinutos(d: Date, minutos: number): Date {
  return new Date(d.getTime() + minutos * 60000);
}

// Medianoche de hoy: usar "ahora" (con hora actual) como minimumDate hace que Android
// excluya el día de hoy del calendario, porque cualquier toque sobre "hoy" arma un
// timestamp a las 00:00 y eso queda "antes" que la hora exacta capturada.
function inicioDeHoy(): Date {
  const d = new Date();
  d.setHours(0, 0, 0, 0);
  return d;
}

function inicioDelDia(d: Date): Date {
  const copia = new Date(d);
  copia.setHours(0, 0, 0, 0);
  return copia;
}

export default function PedidoFormScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const route = useRoute<RouteProp<RootStackParamList, 'PedidoForm'>>();
  const idPedido = route.params?.id;
  const soloLectura = !!idPedido;
  const { serie } = useSerie();

  const [cargando, setCargando] = useState(soloLectura);
  const [guardando, setGuardando] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Serie/secuencial (solo modo creación)
  const [secuencial, setSecuencial] = useState<Secuencial | null>(null);
  const [cargandoSecuencial, setCargandoSecuencial] = useState(!soloLectura);

  // Modo lectura
  const [cabeceraLectura, setCabeceraLectura] = useState<Record<string, any> | null>(null);
  const [detallesLectura, setDetallesLectura] = useState<Array<Record<string, any>>>([]);

  // Modo creación — cabecera
  const [clienteTexto, setClienteTexto] = useState('');
  const [clienteSeleccionado, setClienteSeleccionado] = useState<ClienteBusqueda | null>(null);
  const [clienteResultados, setClienteResultados] = useState<ClienteBusqueda[]>([]);
  // Fecha/hora de entrega son obligatorias: se precargan con valores por defecto
  // (hoy, y la hora actual +1h) que el usuario puede ajustar, pero no dejar vacíos.
  const [fechaPedido, setFechaPedido] = useState<Date>(() => new Date());
  const [fechaEntrega, setFechaEntrega] = useState<Date>(() => new Date());
  const [horaInicial, setHoraInicial] = useState<Date>(() => new Date());
  const [horaMaxima, setHoraMaxima] = useState<Date>(() => sumarMinutos(new Date(), 60));
  const [responsables, setResponsables] = useState<ResponsableTraslado[]>([]);
  const [responsableId, setResponsableId] = useState<number | null>(null);
  const [observaciones, setObservaciones] = useState('');
  const [detalles, setDetalles] = useState<LineaDetalle[]>([]);

  const [productoTexto, setProductoTexto] = useState('');
  const [productoResultados, setProductoResultados] = useState<ProductoBusqueda[]>([]);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    navigation.setOptions({ title: soloLectura ? 'Detalle del pedido' : 'Nuevo pedido' });
  }, [navigation, soloLectura]);

  useEffect(() => {
    if (!soloLectura || !idPedido) return;
    (async () => {
      try {
        const data = await obtenerPedido(idPedido);
        setCabeceraLectura(data.cabecera);
        setDetallesLectura(data.detalles);
      } catch (err) {
        setError(mensajeError(err, 'No se pudo cargar el pedido.'));
      } finally {
        setCargando(false);
      }
    })();
  }, [soloLectura, idPedido]);

  async function cargarSecuencial() {
    if (!serie) return;
    setCargandoSecuencial(true);
    try {
      setSecuencial(await obtenerSecuencial(serie.id_punto_emision));
    } catch (err) {
      setError(mensajeError(err, 'No se pudo obtener el siguiente número de pedido.'));
    } finally {
      setCargandoSecuencial(false);
    }
  }

  useEffect(() => {
    if (soloLectura) return;
    cargarSecuencial();
    obtenerResponsables()
      .then(setResponsables)
      .catch(() => setResponsables([]));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [soloLectura, serie?.id_punto_emision]);

  function buscarClienteDebounced(texto: string) {
    setClienteTexto(texto);
    setClienteSeleccionado(null);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    if (texto.trim().length < 2) {
      setClienteResultados([]);
      return;
    }
    debounceRef.current = setTimeout(async () => {
      try {
        setClienteResultados(await buscarClientes(texto.trim()));
      } catch {
        setClienteResultados([]);
      }
    }, 350);
  }

  function buscarProductoDebounced(texto: string) {
    setProductoTexto(texto);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    if (texto.trim().length < 2) {
      setProductoResultados([]);
      return;
    }
    debounceRef.current = setTimeout(async () => {
      try {
        setProductoResultados(await buscarProductos(texto.trim()));
      } catch {
        setProductoResultados([]);
      }
    }, 350);
  }

  // Precio/subtotal/iva/total se calculan silenciosamente (el usuario solo ve
  // nombre del producto + cantidad) para que pedidos_detalle no quede en ceros.
  function agregarProducto(p: ProductoBusqueda) {
    const precio = Number(p.precio_base ?? 0);
    setDetalles((prev) => [
      ...prev,
      {
        id_producto: p.id,
        producto_nombre: p.nombre,
        cantidad: 1,
        precio_unitario: precio,
        subtotal: precio,
        iva: Number((precio * IVA_DEFAULT).toFixed(2)),
        total: Number((precio * (1 + IVA_DEFAULT)).toFixed(2)),
      },
    ]);
    setProductoTexto('');
    setProductoResultados([]);
  }

  function actualizarCantidad(index: number, cantidad: number) {
    setDetalles((prev) => {
      const copia = [...prev];
      const linea = copia[index];
      const subtotal = Number((cantidad * linea.precio_unitario).toFixed(2));
      const iva = Number((subtotal * IVA_DEFAULT).toFixed(2));
      copia[index] = { ...linea, cantidad, subtotal, iva, total: Number((subtotal + iva).toFixed(2)) };
      return copia;
    });
  }

  function quitarLinea(index: number) {
    setDetalles((prev) => prev.filter((_, i) => i !== index));
  }

  async function guardar() {
    if (!clienteSeleccionado) {
      Alert.alert('Falta el cliente', 'Selecciona un cliente de la lista.');
      return;
    }
    if (detalles.length === 0) {
      Alert.alert('Sin productos', 'Agrega al menos un producto.');
      return;
    }
    if (!serie || !secuencial) {
      Alert.alert('Falta la serie', 'No se pudo determinar el número de pedido. Vuelve a intentar.');
      return;
    }
    if (horaLocalHHMM(horaInicial) >= horaLocalHHMM(horaMaxima)) {
      Alert.alert('Horario inválido', 'La hora inicial debe ser menor a la hora máxima de entrega.');
      return;
    }
    if (fechaLocalISO(fechaEntrega) < fechaLocalISO(fechaPedido)) {
      Alert.alert(
        'Fechas inválidas',
        'La fecha de entrega no puede ser menor a la fecha del pedido (y la fecha del pedido no puede ser mayor a la de entrega).'
      );
      return;
    }
    setGuardando(true);
    setError(null);
    try {
      const res = await crearPedido(
        {
          id_cliente: clienteSeleccionado.id,
          fecha_pedido: fechaLocalISO(fechaPedido),
          observaciones,
          fecha_entrega: fechaLocalISO(fechaEntrega),
          hora_inicial_entrega: horaLocalHHMM(horaInicial),
          hora_maxima_entrega: horaLocalHHMM(horaMaxima),
          id_responsable_entrega: responsableId ?? undefined,
          id_establecimiento: serie.id_establecimiento,
          id_punto_emision: serie.id_punto_emision,
          establecimiento: serie.establecimiento,
          punto_emision: serie.punto_emision,
          secuencial: secuencial.formateado,
        },
        detalles.map(({ producto_nombre, ...d }) => d)
      );
      Alert.alert('Pedido guardado', `Se creó el pedido ${serie.establecimiento}-${serie.punto_emision}-${secuencial.formateado}.`, [
        { text: 'OK', onPress: () => navigation.goBack() },
      ]);
    } catch (err) {
      if (codigoError(err) === 'SECUENCIAL_NO_DISPONIBLE') {
        setError('Ese número de pedido ya se usó (probablemente otro celular guardó primero). Se generó uno nuevo, revisa e intenta guardar de nuevo.');
        await cargarSecuencial();
      } else {
        setError(mensajeError(err, 'No se pudo guardar el pedido.'));
      }
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

  if (soloLectura) {
    const rangoHorario =
      cabeceraLectura?.hora_inicial_entrega || cabeceraLectura?.hora_maxima_entrega
        ? `${String(cabeceraLectura?.hora_inicial_entrega ?? '--:--').slice(0, 5)} - ${String(
            cabeceraLectura?.hora_maxima_entrega ?? '--:--'
          ).slice(0, 5)}`
        : null;
    return (
      <ScrollView style={styles.container} contentContainerStyle={{ padding: 16 }}>
        {error ? <Text style={styles.error}>{error}</Text> : null}
        {cabeceraLectura ? (
          <View style={styles.bloque}>
            <Text style={styles.tituloBloque}>{cabeceraLectura.cliente_nombre}</Text>
            <Text style={styles.dato}>Estado: {cabeceraLectura.estado}</Text>
            <Text style={styles.dato}>Fecha pedido: {String(cabeceraLectura.fecha_pedido).slice(0, 10)}</Text>
            {cabeceraLectura.fecha_entrega ? (
              <Text style={styles.dato}>Fecha entrega: {String(cabeceraLectura.fecha_entrega).slice(0, 10)}</Text>
            ) : null}
            {rangoHorario ? <Text style={styles.dato}>Horario entrega: {rangoHorario}</Text> : null}
            {cabeceraLectura.responsable_entrega ? (
              <Text style={styles.dato}>Responsable: {cabeceraLectura.responsable_entrega}</Text>
            ) : null}
            {cabeceraLectura.observaciones ? (
              <Text style={styles.dato}>Obs.: {cabeceraLectura.observaciones}</Text>
            ) : null}
          </View>
        ) : null}

        <Text style={styles.tituloSeccion}>Productos</Text>
        {detallesLectura.map((d, i) => (
          <View key={i} style={styles.lineaLectura}>
            <Text style={styles.lineaNombre}>{d.producto_nombre}</Text>
            <Text style={styles.lineaSub}>Cantidad: {d.cantidad}</Text>
          </View>
        ))}
      </ScrollView>
    );
  }

  return (
    <KeyboardAwareScrollView
      style={styles.container}
      contentContainerStyle={{ padding: 16, paddingBottom: 100 }}
      keyboardShouldPersistTaps="handled"
      enableOnAndroid
      extraScrollHeight={30}
      keyboardOpeningTime={0}
    >
      {error ? <Text style={styles.error}>{error}</Text> : null}

      <View style={styles.numeroPedido}>
        {cargandoSecuencial ? (
          <ActivityIndicator color="#0d6efd" />
        ) : serie && secuencial ? (
          <Text style={styles.numeroPedidoTexto}>
            Pedido {serie.establecimiento}-{serie.punto_emision}-{secuencial.formateado}
          </Text>
        ) : (
          <Text style={styles.error}>No se pudo obtener la serie/secuencial.</Text>
        )}
      </View>

      <Text style={styles.label}>Cliente</Text>
      {clienteSeleccionado ? (
        <View style={styles.seleccionado}>
          <Text style={{ flex: 1 }}>{clienteSeleccionado.nombre}</Text>
          <TouchableOpacity onPress={() => { setClienteSeleccionado(null); setClienteTexto(''); }}>
            <Text style={styles.quitar}>Cambiar</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <>
          <TextInput
            style={styles.input}
            value={clienteTexto}
            onChangeText={buscarClienteDebounced}
            placeholder="Buscar por nombre o identificación..."
          />
          {clienteResultados.map((c) => (
            <TouchableOpacity key={c.id} style={styles.resultado} onPress={() => setClienteSeleccionado(c)}>
              <Text style={styles.resultadoNombre}>{c.nombre}</Text>
              <Text style={styles.resultadoSub}>{c.identificacion}</Text>
            </TouchableOpacity>
          ))}
        </>
      )}

      <View style={styles.fila}>
        <SelectorFechaHora
          label="Fecha pedido"
          mode="date"
          value={fechaPedido}
          onChange={(d) => d && setFechaPedido(d)}
          permiteQuitar={false}
        />
        <View style={{ width: 12 }} />
        <SelectorFechaHora
          label="Fecha entrega *"
          mode="date"
          value={fechaEntrega}
          onChange={(d) => d && setFechaEntrega(d)}
          minimumDate={inicioDelDia(fechaPedido) > inicioDeHoy() ? inicioDelDia(fechaPedido) : inicioDeHoy()}
          permiteQuitar={false}
        />
      </View>

      <View style={{ marginTop: 12 }}>
        <SelectorRangoHoras
          label="Horario de entrega *"
          horaInicial={horaInicial}
          horaMaxima={horaMaxima}
          onChange={(inicial, maxima) => {
            if (inicial) setHoraInicial(inicial);
            if (maxima) setHoraMaxima(maxima);
          }}
          permiteQuitar={false}
        />
      </View>

      <View style={{ marginTop: 12 }}>
        <SelectorLista
          label="Responsable de entrega"
          value={responsableId}
          onChange={setResponsableId}
          opciones={responsables.map((r) => ({ id: r.id, label: r.nombre, sublabel: r.identificacion ?? undefined }))}
          placeholder={responsables.length === 0 ? 'No hay responsables configurados' : 'Seleccionar...'}
        />
      </View>

      <Text style={styles.label}>Observaciones</Text>
      <TextInput
        style={styles.input}
        value={observaciones}
        onChangeText={setObservaciones}
        placeholder="Opcional"
        multiline
      />

      <Text style={[styles.label, { marginTop: 20 }]}>Agregar producto</Text>
      <TextInput
        style={styles.input}
        value={productoTexto}
        onChangeText={buscarProductoDebounced}
        placeholder="Buscar por código o nombre..."
      />
      {productoResultados.map((p) => (
        <TouchableOpacity key={p.id} style={styles.resultado} onPress={() => agregarProducto(p)}>
          <Text style={styles.resultadoNombre}>{p.nombre}</Text>
          <Text style={styles.resultadoSub}>{p.codigo}</Text>
        </TouchableOpacity>
      ))}

      <Text style={[styles.tituloSeccion, { marginTop: 16 }]}>Detalle del pedido</Text>
      {detalles.length === 0 ? (
        <Text style={styles.vacio}>Aún no agregas productos.</Text>
      ) : (
        detalles.map((d, i) => (
          <View key={i} style={styles.lineaEdit}>
            <Text style={[styles.lineaNombre, { flex: 1 }]}>{d.producto_nombre}</Text>
            <TextInput
              style={styles.inputCantidad}
              keyboardType="decimal-pad"
              value={String(d.cantidad)}
              onChangeText={(v) => actualizarCantidad(i, Number(v.replace(',', '.')) || 0)}
            />
            <TouchableOpacity onPress={() => quitarLinea(i)}>
              <Text style={styles.quitar}>Quitar</Text>
            </TouchableOpacity>
          </View>
        ))
      )}

      <TouchableOpacity style={styles.botonGuardar} onPress={guardar} disabled={guardando}>
        {guardando ? <ActivityIndicator color="#fff" /> : <Text style={styles.botonGuardarTexto}>Guardar pedido</Text>}
      </TouchableOpacity>
    </KeyboardAwareScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f6f8' },
  centrado: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  error: { color: '#dc3545', marginBottom: 12, textAlign: 'center' },
  numeroPedido: {
    backgroundColor: '#e7f1ff',
    borderRadius: 8,
    padding: 10,
    alignItems: 'center',
    marginBottom: 8,
  },
  numeroPedidoTexto: { color: '#0d6efd', fontWeight: '700', fontSize: 15 },
  fila: { flexDirection: 'row', marginTop: 4 },
  label: { fontSize: 13, color: '#333', marginTop: 12, marginBottom: 4, fontWeight: '600' },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 15,
    backgroundColor: '#fff',
  },
  seleccionado: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#e7f1ff',
    borderRadius: 8,
    padding: 12,
  },
  resultado: { backgroundColor: '#fff', padding: 10, borderBottomWidth: 1, borderBottomColor: '#eee' },
  resultadoNombre: { fontSize: 14, fontWeight: '600' },
  resultadoSub: { fontSize: 12, color: '#777' },
  quitar: { color: '#dc3545', fontWeight: '600' },
  tituloSeccion: { fontSize: 15, fontWeight: '700', marginTop: 8, marginBottom: 8 },
  vacio: { color: '#888' },
  lineaEdit: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fff',
    borderRadius: 8,
    padding: 12,
    marginBottom: 8,
    gap: 10,
  },
  lineaNombre: { fontSize: 14, fontWeight: '600' },
  inputCantidad: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 6,
    paddingHorizontal: 8,
    paddingVertical: 4,
    width: 56,
    fontSize: 13,
    textAlign: 'center',
  },
  botonGuardar: { backgroundColor: '#0d6efd', borderRadius: 8, paddingVertical: 14, marginTop: 20, alignItems: 'center' },
  botonGuardarTexto: { color: '#fff', fontSize: 16, fontWeight: '600' },
  bloque: { backgroundColor: '#fff', borderRadius: 10, padding: 16, marginBottom: 16 },
  tituloBloque: { fontSize: 16, fontWeight: '700', marginBottom: 6 },
  dato: { fontSize: 14, color: '#444', marginTop: 2 },
  lineaLectura: { backgroundColor: '#fff', borderRadius: 8, padding: 12, marginBottom: 8 },
  lineaSub: { fontSize: 13, color: '#666', marginTop: 2 },
});
