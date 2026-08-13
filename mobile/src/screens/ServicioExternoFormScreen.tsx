import React, { useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { KeyboardAwareScrollView } from 'react-native-keyboard-aware-scroll-view';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RootStackParamList } from '../navigation/RootNavigator';
import {
  Bodega,
  ClienteBusqueda,
  LineaInput,
  ProductoBusqueda,
  PuntoEmision,
  TarifaIva,
  buscarClientes,
  buscarProductos,
  crearOrden,
  obtenerCatalogos,
  obtenerSiguienteSecuencial,
} from '../api/servicioExterno';
import { mensajeError } from '../api/client';
import SelectorLista from '../components/SelectorLista';
import SelectorFechaHora from '../components/SelectorFechaHora';

function aIso(d: Date): string {
  return d.toISOString().slice(0, 10);
}

export default function ServicioExternoFormScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  const [cargando, setCargando] = useState(true);
  const [guardando, setGuardando] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [puntos, setPuntos] = useState<PuntoEmision[]>([]);
  const [bodegas, setBodegas] = useState<Bodega[]>([]);
  const [tarifasIva, setTarifasIva] = useState<TarifaIva[]>([]);

  const [idPunto, setIdPunto] = useState<number | null>(null);
  const [secuencial, setSecuencial] = useState<string | null>(null);
  const [cargandoSecuencial, setCargandoSecuencial] = useState(false);
  const [idBodega, setIdBodega] = useState<number | null>(null);

  // Cliente (autocomplete)
  const [clienteTexto, setClienteTexto] = useState('');
  const [clienteSeleccionado, setClienteSeleccionado] = useState<ClienteBusqueda | null>(null);
  const [clienteResultados, setClienteResultados] = useState<ClienteBusqueda[]>([]);
  const debounceClienteRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Equipo
  const [equipoDescripcion, setEquipoDescripcion] = useState('');
  const [equipoMarca, setEquipoMarca] = useState('');
  const [equipoModelo, setEquipoModelo] = useState('');
  const [equipoSerie, setEquipoSerie] = useState('');
  const [direccionServicio, setDireccionServicio] = useState('');
  const [fechaServicio, setFechaServicio] = useState<Date>(new Date());
  const [descripcionTrabajo, setDescripcionTrabajo] = useState('');
  const [observaciones, setObservaciones] = useState('');

  // Líneas
  const [detalles, setDetalles] = useState<LineaInput[]>([]);
  const [tipoLinea, setTipoLinea] = useState<'producto' | 'servicio'>('servicio');
  const [productoTexto, setProductoTexto] = useState('');
  const [productoResultados, setProductoResultados] = useState<ProductoBusqueda[]>([]);
  const [productoSeleccionado, setProductoSeleccionado] = useState<ProductoBusqueda | null>(null);
  const debounceProductoRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const [lineaDescripcion, setLineaDescripcion] = useState('');
  const [lineaCantidad, setLineaCantidad] = useState('1');
  const [lineaPrecio, setLineaPrecio] = useState('');
  const [lineaDescuento, setLineaDescuento] = useState('0');
  const [lineaTarifaIva, setLineaTarifaIva] = useState<number | null>(null);

  useEffect(() => {
    obtenerCatalogos()
      .then((data) => {
        setPuntos(data.puntos);
        setBodegas(data.bodegas);
        setTarifasIva(data.tarifas_iva);
        if (data.puntos.length === 1) setIdPunto(data.puntos[0].id);
        if (data.bodegas.length === 1) setIdBodega(data.bodegas[0].id);
      })
      .catch((err) => setError(mensajeError(err, 'No se pudieron cargar los catálogos.')))
      .finally(() => setCargando(false));
  }, []);

  useEffect(() => {
    if (!idPunto) {
      setSecuencial(null);
      return;
    }
    setCargandoSecuencial(true);
    obtenerSiguienteSecuencial(idPunto)
      .then((r) => setSecuencial(r.formateado))
      .catch(() => setSecuencial(null))
      .finally(() => setCargandoSecuencial(false));
  }, [idPunto]);

  function buscarClienteDebounced(texto: string) {
    setClienteTexto(texto);
    setClienteSeleccionado(null);
    if (debounceClienteRef.current) clearTimeout(debounceClienteRef.current);
    if (texto.trim().length < 2) {
      setClienteResultados([]);
      return;
    }
    debounceClienteRef.current = setTimeout(async () => {
      try {
        setClienteResultados(await buscarClientes(texto.trim()));
      } catch {
        setClienteResultados([]);
      }
    }, 400);
  }

  function buscarProductoDebounced(texto: string) {
    setProductoTexto(texto);
    setProductoSeleccionado(null);
    if (debounceProductoRef.current) clearTimeout(debounceProductoRef.current);
    if (texto.trim().length < 2) {
      setProductoResultados([]);
      return;
    }
    debounceProductoRef.current = setTimeout(async () => {
      try {
        setProductoResultados(await buscarProductos(texto.trim(), idBodega ?? undefined));
      } catch {
        setProductoResultados([]);
      }
    }, 400);
  }

  function elegirProducto(p: ProductoBusqueda) {
    setProductoSeleccionado(p);
    setProductoTexto(p.nombre);
    setProductoResultados([]);
    setLineaDescripcion(p.nombre);
    setLineaPrecio(String(p.precio_base ?? ''));
    const tar = tarifasIva.find((t) => t.id === p.tarifa_iva);
    if (tar) setLineaTarifaIva(tar.id);
  }

  function agregarLinea() {
    const cantidad = Number(lineaCantidad.replace(',', '.'));
    const precio = Number(lineaPrecio.replace(',', '.'));
    const descuento = Number(lineaDescuento.replace(',', '.')) || 0;
    const descripcion = tipoLinea === 'producto' ? (productoSeleccionado?.nombre ?? lineaDescripcion) : lineaDescripcion.trim();

    if (descripcion === '') {
      Alert.alert('Falta la descripción', 'Escribe o selecciona qué se hizo/usó en esta línea.');
      return;
    }
    if (!cantidad || cantidad <= 0) {
      Alert.alert('Cantidad inválida', 'La cantidad debe ser mayor a cero.');
      return;
    }
    if (tipoLinea === 'producto' && !productoSeleccionado) {
      Alert.alert('Falta el producto', 'Busca y selecciona un repuesto del catálogo, o cambia a "Servicio".');
      return;
    }

    const tarifa = tarifasIva.find((t) => t.id === lineaTarifaIva);

    setDetalles((prev) => [
      ...prev,
      {
        tipo_linea: tipoLinea,
        id_producto: tipoLinea === 'producto' ? productoSeleccionado?.id : undefined,
        descripcion,
        cantidad,
        precio_unitario: precio || 0,
        descuento,
        porcentaje_iva: tarifa ? Number(tarifa.porcentaje_iva) : 0,
        id_tarifa_iva: lineaTarifaIva ?? undefined,
        id_bodega: tipoLinea === 'producto' ? idBodega ?? undefined : undefined,
      },
    ]);

    // Reset mini-formulario de línea
    setProductoSeleccionado(null);
    setProductoTexto('');
    setProductoResultados([]);
    setLineaDescripcion('');
    setLineaCantidad('1');
    setLineaPrecio('');
    setLineaDescuento('0');
    setLineaTarifaIva(null);
  }

  function quitarLinea(idx: number) {
    setDetalles((prev) => prev.filter((_, i) => i !== idx));
  }

  const totalOrden = detalles.reduce((acc, d) => {
    const base = Math.max(0, d.cantidad * d.precio_unitario - (d.descuento ?? 0));
    const iva = base * ((d.porcentaje_iva ?? 0) / 100);
    return acc + base + iva;
  }, 0);

  async function guardar() {
    if (!idPunto || !secuencial) {
      Alert.alert('Falta el punto de emisión', 'Selecciona el punto de emisión de la orden.');
      return;
    }
    if (!clienteSeleccionado) {
      Alert.alert('Falta el cliente', 'Selecciona un cliente de la lista.');
      return;
    }
    if (equipoDescripcion.trim() === '') {
      Alert.alert('Falta el equipo', 'Describe el equipo atendido.');
      return;
    }
    if (detalles.length === 0) {
      Alert.alert('Sin líneas', 'Agrega al menos un repuesto o servicio.');
      return;
    }

    setGuardando(true);
    setError(null);
    try {
      const res = await crearOrden({
        id_punto_emision: idPunto,
        secuencial,
        id_cliente: clienteSeleccionado.id,
        equipo_descripcion: equipoDescripcion.trim(),
        equipo_marca: equipoMarca.trim() || undefined,
        equipo_modelo: equipoModelo.trim() || undefined,
        equipo_serie: equipoSerie.trim() || undefined,
        direccion_servicio: direccionServicio.trim() || undefined,
        fecha_servicio: aIso(fechaServicio),
        descripcion_trabajo: descripcionTrabajo.trim() || undefined,
        observaciones: observaciones.trim() || undefined,
        id_bodega: idBodega ?? undefined,
        detalles,
      });
      navigation.replace('ServicioExternoDetail', { id: res.id });
    } catch (err) {
      setError(mensajeError(err, 'No se pudo registrar la orden.'));
    } finally {
      setGuardando(false);
    }
  }

  if (cargando) {
    return <ActivityIndicator size="large" color="#0d6efd" style={{ marginTop: 40 }} />;
  }

  return (
    <KeyboardAwareScrollView
      style={styles.container}
      contentContainerStyle={{ padding: 16, paddingBottom: 60 }}
      enableOnAndroid
      extraScrollHeight={20}
    >
      {error ? <Text style={styles.error}>{error}</Text> : null}

      {puntos.length === 0 ? (
        <View style={styles.numeroOrden}>
          <Text style={styles.error}>No hay puntos de emisión configurados.</Text>
        </View>
      ) : (
        <SelectorLista<number>
          label="Serie"
          value={idPunto}
          opciones={puntos.map((p) => ({ id: p.id, label: `${p.cod_establecimiento}-${p.codigo_punto}` }))}
          onChange={setIdPunto}
        />
      )}

      {idPunto ? (
        <View style={styles.numeroOrden}>
          {cargandoSecuencial ? (
            <ActivityIndicator color="#0d6efd" />
          ) : (
            <Text style={styles.numeroOrdenTexto}>
              Orden {puntos.find((p) => p.id === idPunto)?.cod_establecimiento}-
              {puntos.find((p) => p.id === idPunto)?.codigo_punto}-{secuencial ?? '—'}
            </Text>
          )}
        </View>
      ) : null}

      <View style={styles.card}>
        <Text style={styles.seccionTitulo}>Cliente</Text>
        {clienteSeleccionado ? (
          <View style={styles.chip}>
            <Text style={{ flex: 1 }}>{clienteSeleccionado.nombre}</Text>
            <TouchableOpacity onPress={() => { setClienteSeleccionado(null); setClienteTexto(''); }}>
              <Text style={styles.quitar}>Quitar</Text>
            </TouchableOpacity>
          </View>
        ) : (
          <View>
            <TextInput
              style={styles.input}
              placeholder="Buscar cliente por nombre o identificación..."
              value={clienteTexto}
              onChangeText={buscarClienteDebounced}
            />
            {clienteResultados.map((c) => (
              <TouchableOpacity key={c.id} style={styles.resultado} onPress={() => setClienteSeleccionado(c)}>
                <Text style={styles.resultadoNombre}>{c.nombre}</Text>
                <Text style={styles.resultadoSub}>{c.identificacion}</Text>
              </TouchableOpacity>
            ))}
          </View>
        )}
      </View>

      <View style={styles.card}>
        <Text style={styles.seccionTitulo}>Equipo atendido</Text>
        <Text style={styles.label}>Descripción</Text>
        <TextInput style={styles.input} value={equipoDescripcion} onChangeText={setEquipoDescripcion} placeholder="Ej: Aire acondicionado split 12000 BTU" />
        <View style={styles.fila2}>
          <View style={{ flex: 1 }}>
            <Text style={styles.label}>Marca</Text>
            <TextInput style={styles.input} value={equipoMarca} onChangeText={setEquipoMarca} />
          </View>
          <View style={{ width: 12 }} />
          <View style={{ flex: 1 }}>
            <Text style={styles.label}>Modelo</Text>
            <TextInput style={styles.input} value={equipoModelo} onChangeText={setEquipoModelo} />
          </View>
        </View>
        <Text style={styles.label}>Serie</Text>
        <TextInput style={styles.input} value={equipoSerie} onChangeText={setEquipoSerie} />
        <Text style={styles.label}>Dirección del servicio</Text>
        <TextInput style={styles.input} value={direccionServicio} onChangeText={setDireccionServicio} multiline />
        <View style={{ marginTop: 10 }}>
          <SelectorFechaHora label="Fecha del servicio" mode="date" value={fechaServicio} permiteQuitar={false} onChange={(d) => d && setFechaServicio(d)} />
        </View>
        <Text style={styles.label}>Descripción del trabajo</Text>
        <TextInput style={styles.input} value={descripcionTrabajo} onChangeText={setDescripcionTrabajo} multiline />
        <Text style={styles.label}>Observaciones</Text>
        <TextInput style={styles.input} value={observaciones} onChangeText={setObservaciones} multiline />
      </View>

      <View style={styles.card}>
        <Text style={styles.seccionTitulo}>Repuestos y servicios</Text>

        {detalles.map((d, idx) => (
          <View key={idx} style={styles.lineaAgregada}>
            <View style={{ flex: 1 }}>
              <Text style={styles.lineaDesc} numberOfLines={2}>
                {d.tipo_linea === 'producto' ? '🔧 ' : '🛠️ '}
                {d.descripcion}
              </Text>
              <Text style={styles.lineaSub}>
                {d.cantidad} x ${d.precio_unitario.toFixed(2)}
                {(d.descuento ?? 0) > 0 ? ` · Desc. $${(d.descuento ?? 0).toFixed(2)}` : ''}
              </Text>
            </View>
            <TouchableOpacity onPress={() => quitarLinea(idx)}>
              <Text style={styles.quitar}>Quitar</Text>
            </TouchableOpacity>
          </View>
        ))}

        <View style={styles.separador} />

        <View style={styles.tipoLineaFila}>
          <TouchableOpacity
            style={[styles.tipoBoton, tipoLinea === 'servicio' && styles.tipoBotonActivo]}
            onPress={() => setTipoLinea('servicio')}
          >
            <Text style={tipoLinea === 'servicio' ? styles.tipoBotonTextoActivo : styles.tipoBotonTexto}>Mano de obra / servicio</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.tipoBoton, tipoLinea === 'producto' && styles.tipoBotonActivo]}
            onPress={() => setTipoLinea('producto')}
          >
            <Text style={tipoLinea === 'producto' ? styles.tipoBotonTextoActivo : styles.tipoBotonTexto}>Repuesto de bodega</Text>
          </TouchableOpacity>
        </View>

        {tipoLinea === 'producto' ? (
          <View>
            {!idBodega ? <Text style={styles.avisoBodega}>Selecciona una bodega abajo para ver el stock disponible.</Text> : null}
            {productoSeleccionado ? (
              <View style={styles.chip}>
                <Text style={{ flex: 1 }}>
                  {productoSeleccionado.nombre}
                  {productoSeleccionado.controla_stock ? ` (stock: ${productoSeleccionado.stock_actual ?? 0})` : ''}
                </Text>
                <TouchableOpacity onPress={() => { setProductoSeleccionado(null); setProductoTexto(''); }}>
                  <Text style={styles.quitar}>Quitar</Text>
                </TouchableOpacity>
              </View>
            ) : (
              <View>
                <TextInput style={styles.input} placeholder="Buscar repuesto..." value={productoTexto} onChangeText={buscarProductoDebounced} />
                {productoResultados.map((p) => (
                  <TouchableOpacity key={p.id} style={styles.resultado} onPress={() => elegirProducto(p)}>
                    <Text style={styles.resultadoNombre}>{p.nombre}</Text>
                    <Text style={styles.resultadoSub}>
                      {p.codigo} · ${Number(p.precio_base).toFixed(2)}
                      {p.controla_stock ? ` · stock: ${p.stock_actual ?? 0}` : ''}
                    </Text>
                  </TouchableOpacity>
                ))}
              </View>
            )}
          </View>
        ) : (
          <View>
            <Text style={styles.label}>Descripción</Text>
            <TextInput style={styles.input} value={lineaDescripcion} onChangeText={setLineaDescripcion} placeholder="Ej: Mano de obra instalación" />
          </View>
        )}

        <View style={styles.fila2}>
          <View style={{ flex: 1 }}>
            <Text style={styles.label}>Cantidad</Text>
            <TextInput style={styles.input} value={lineaCantidad} onChangeText={setLineaCantidad} keyboardType="decimal-pad" />
          </View>
          <View style={{ width: 12 }} />
          <View style={{ flex: 1 }}>
            <Text style={styles.label}>Precio unitario</Text>
            <TextInput style={styles.input} value={lineaPrecio} onChangeText={setLineaPrecio} keyboardType="decimal-pad" />
          </View>
        </View>
        <View style={styles.fila2}>
          <View style={{ flex: 1 }}>
            <Text style={styles.label}>Descuento</Text>
            <TextInput style={styles.input} value={lineaDescuento} onChangeText={setLineaDescuento} keyboardType="decimal-pad" />
          </View>
          <View style={{ width: 12 }} />
          <View style={{ flex: 1 }}>
            <SelectorLista<number>
              label="IVA"
              value={lineaTarifaIva}
              opciones={tarifasIva.map((t) => ({ id: t.id, label: `${t.tarifa} (${t.porcentaje_iva}%)` }))}
              onChange={setLineaTarifaIva}
            />
          </View>
        </View>

        <TouchableOpacity style={styles.botonAgregar} onPress={agregarLinea}>
          <Text style={styles.botonAgregarTexto}>+ Agregar línea</Text>
        </TouchableOpacity>

        <View style={{ marginTop: 14 }}>
          <SelectorLista<number>
            label="Bodega (para repuestos)"
            value={idBodega}
            opciones={bodegas.map((b) => ({ id: b.id, label: b.nombre }))}
            onChange={setIdBodega}
          />
        </View>

        <View style={styles.totalFila}>
          <Text style={styles.totalLabel}>Total estimado</Text>
          <Text style={styles.totalValor}>${totalOrden.toFixed(2)}</Text>
        </View>
      </View>

      <TouchableOpacity style={styles.botonGuardar} onPress={guardar} disabled={guardando}>
        {guardando ? <ActivityIndicator color="#fff" /> : <Text style={styles.botonGuardarTexto}>Registrar orden</Text>}
      </TouchableOpacity>
    </KeyboardAwareScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f6f8' },
  error: { color: '#dc3545', textAlign: 'center', marginBottom: 12 },
  card: { backgroundColor: '#fff', borderRadius: 10, padding: 14, marginBottom: 12, elevation: 1 },
  seccionTitulo: { fontSize: 13, fontWeight: '700', color: '#666', textTransform: 'uppercase', marginBottom: 10 },
  numeroOrden: { backgroundColor: '#e7f1ff', borderRadius: 8, padding: 10, alignItems: 'center', marginTop: 8, marginBottom: 12 },
  numeroOrdenTexto: { color: '#0d6efd', fontWeight: '700', fontSize: 15 },
  label: { fontSize: 13, color: '#333', marginBottom: 4, marginTop: 10, fontWeight: '600' },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    backgroundColor: '#fff',
    fontSize: 14,
  },
  fila2: { flexDirection: 'row' },
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#0d6efd',
    borderRadius: 8,
    padding: 10,
    backgroundColor: '#e7f1ff',
  },
  quitar: { color: '#dc3545', fontSize: 12, fontWeight: '600' },
  resultado: { paddingVertical: 10, borderBottomWidth: 1, borderBottomColor: '#eee' },
  resultadoNombre: { fontSize: 14, fontWeight: '600' },
  resultadoSub: { fontSize: 12, color: '#777', marginTop: 1 },
  separador: { height: 1, backgroundColor: '#eee', marginVertical: 12 },
  lineaAgregada: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
  },
  lineaDesc: { fontSize: 13, fontWeight: '600' },
  lineaSub: { fontSize: 11, color: '#888', marginTop: 1 },
  tipoLineaFila: { flexDirection: 'row', gap: 8, marginBottom: 4 },
  tipoBoton: { flex: 1, paddingVertical: 10, borderRadius: 8, borderWidth: 1, borderColor: '#0d6efd', alignItems: 'center' },
  tipoBotonActivo: { backgroundColor: '#0d6efd' },
  tipoBotonTexto: { color: '#0d6efd', fontWeight: '600', fontSize: 12 },
  tipoBotonTextoActivo: { color: '#fff', fontWeight: '700', fontSize: 12 },
  avisoBodega: { fontSize: 11, color: '#dc3545', marginBottom: 6 },
  botonAgregar: { backgroundColor: '#198754', borderRadius: 8, paddingVertical: 10, marginTop: 14, alignItems: 'center' },
  botonAgregarTexto: { color: '#fff', fontWeight: '700', fontSize: 13 },
  totalFila: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 16, paddingTop: 10, borderTopWidth: 1, borderTopColor: '#eee' },
  totalLabel: { fontSize: 14, fontWeight: '700', color: '#333' },
  totalValor: { fontSize: 18, fontWeight: '800', color: '#0d6efd' },
  botonGuardar: { backgroundColor: '#0d6efd', borderRadius: 8, paddingVertical: 16, alignItems: 'center', marginTop: 4 },
  botonGuardarTexto: { color: '#fff', fontSize: 16, fontWeight: '700' },
});
