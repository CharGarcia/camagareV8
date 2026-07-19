import React, { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Alert, ScrollView, StyleSheet, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { KeyboardAwareScrollView } from 'react-native-keyboard-aware-scroll-view';
import * as Sharing from 'expo-sharing';
import { useNavigation, useRoute } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RouteProp } from '@react-navigation/native';
import type { RootStackParamList } from '../navigation/RootNavigator';
import {
  actualizarFactura,
  Bodega,
  crearFactura,
  descargarPdfFactura,
  enviarFacturaSri,
  EstablecimientoFactura,
  FacturaCabecera,
  FacturaDetalleLinea,
  FacturaPago,
  FormaPagoSri,
  obtenerCatalogosFacturas,
  obtenerFactura,
  obtenerSecuencial,
  obtenerSeries,
  TotalPorTarifa,
  VendedorFactura,
} from '../api/facturasVenta';
import { ClienteListado, listarClientes } from '../api/clientes';
import { ProductoListado, listarProductos } from '../api/productos';
import { mensajeError } from '../api/client';
import SelectorFechaHora from '../components/SelectorFechaHora';
import SelectorLista from '../components/SelectorLista';

type LineaFactura = {
  id_producto: number;
  producto_nombre: string;
  codigo: string;
  precioBase: number;
  pvp: number;
  cantidad: number;
};

type Modo = 'ver' | 'crear' | 'editar';

const COLOR_ESTADO: Record<string, string> = {
  BORRADOR: '#fd7e14',
  AUTORIZADO: '#198754',
  APROBADO: '#198754',
  DEVUELTA: '#dc3545',
  NO_AUTORIZADO: '#dc3545',
  ANULADO: '#6c757d',
};

function fechaLocalISO(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const dia = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${dia}`;
}

function fechaDesdeISO(iso: string): Date {
  const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
  return new Date(y, (m || 1) - 1, d || 1);
}

export default function FacturaVentaFormScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const route = useRoute<RouteProp<RootStackParamList, 'FacturaVentaForm'>>();
  const idFactura = route.params?.id;

  const [modo, setModo] = useState<Modo>(idFactura ? 'ver' : 'crear');
  const [cargando, setCargando] = useState(!!idFactura);
  const [guardando, setGuardando] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Modo ver
  const [cabeceraLectura, setCabeceraLectura] = useState<FacturaCabecera | null>(null);
  const [detallesLectura, setDetallesLectura] = useState<FacturaDetalleLinea[]>([]);
  const [pagosLectura, setPagosLectura] = useState<FacturaPago[]>([]);
  const [totalesIva, setTotalesIva] = useState<TotalPorTarifa[]>([]);
  const [descargandoPdf, setDescargandoPdf] = useState(false);
  const [enviandoSri, setEnviandoSri] = useState(false);

  // Serie/secuencial (solo modo creación; en edición quedan fijos)
  const [establecimientos, setEstablecimientos] = useState<EstablecimientoFactura[]>([]);
  const [idEstablecimiento, setIdEstablecimiento] = useState<number | null>(null);
  const [idPuntoEmision, setIdPuntoEmision] = useState<number | null>(null);
  const [establecimientoCodigo, setEstablecimientoCodigo] = useState('');
  const [puntoEmisionCodigo, setPuntoEmisionCodigo] = useState('');
  const [secuencial, setSecuencial] = useState<string | null>(null);
  const [cargandoSecuencial, setCargandoSecuencial] = useState(false);

  // Catálogos (crear y editar)
  const [bodegas, setBodegas] = useState<Bodega[]>([]);
  const [vendedores, setVendedores] = useState<VendedorFactura[]>([]);
  const [formasPagoSri, setFormasPagoSri] = useState<FormaPagoSri[]>([]);

  // Cabecera (crear y editar)
  const [clienteTexto, setClienteTexto] = useState('');
  const [clienteSeleccionado, setClienteSeleccionado] = useState<ClienteListado | null>(null);
  const [clienteResultados, setClienteResultados] = useState<ClienteListado[]>([]);
  const [fechaEmision, setFechaEmision] = useState<Date>(() => new Date());
  const [idBodega, setIdBodega] = useState<number | null>(null);
  const [idVendedor, setIdVendedor] = useState<number | null>(null);
  const [diasCredito, setDiasCredito] = useState('0');
  const [observaciones, setObservaciones] = useState('');
  const [formaPago, setFormaPago] = useState<string | null>(null);
  const formaPagoTocada = useRef(false);

  const [productoTexto, setProductoTexto] = useState('');
  const [productoResultados, setProductoResultados] = useState<ProductoListado[]>([]);
  const [lineas, setLineas] = useState<LineaFactura[]>([]);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    const titulos: Record<Modo, string> = { ver: 'Detalle de la factura', crear: 'Nueva factura', editar: 'Editar factura' };
    navigation.setOptions({ title: titulos[modo] });
  }, [navigation, modo]);

  const cargarFactura = React.useCallback(async () => {
    if (!idFactura) return;
    try {
      const data = await obtenerFactura(idFactura);
      setCabeceraLectura(data.cabecera);
      setDetallesLectura(data.detalles);
      setPagosLectura(data.pagos);
      setTotalesIva(data.totales_iva);
    } catch (err) {
      setError(mensajeError(err, 'No se pudo cargar la factura.'));
    } finally {
      setCargando(false);
    }
  }, [idFactura]);

  useEffect(() => {
    cargarFactura();
  }, [cargarFactura]);

  // Catálogos: siempre se cargan (los necesita tanto crear como editar).
  useEffect(() => {
    obtenerCatalogosFacturas()
      .then((catalogos) => {
        setBodegas(catalogos.bodegas);
        setVendedores(catalogos.vendedores);
        setFormasPagoSri(catalogos.formas_pago_sri);
      })
      .catch(() => {
        setBodegas([]);
        setVendedores([]);
        setFormasPagoSri([]);
      });
  }, []);

  // Series: solo hacen falta para CREAR (en editar la serie ya está fija).
  // Auto-selección: la serie favorita del usuario (misma estrellita que en la
  // web); si no tiene ninguna marcada, la primera disponible.
  useEffect(() => {
    if (idFactura) return;
    obtenerSeries()
      .then(({ establecimientos: series, id_punto_emision_favorito }) => {
        setEstablecimientos(series);
        const opciones = aSerieOpciones(series);
        if (opciones.length === 0) return;
        const favorita = opciones.find((o) => o.id === id_punto_emision_favorito);
        onSerieChange((favorita ?? opciones[0]).id, series, formasPagoSri);
      })
      .catch((err) => setError(mensajeError(err, 'No se pudo cargar la configuración de facturación.')));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [idFactura]);

  function aSerieOpciones(seriesDisponibles: EstablecimientoFactura[]) {
    return seriesDisponibles.flatMap((e) =>
      e.puntos_emision.map((p) => ({
        id: p.id_punto_emision,
        label: `${e.establecimiento}-${p.punto_emision}`,
        idEstablecimiento: e.id_establecimiento,
        establecimientoCodigo: e.establecimiento,
        puntoEmisionCodigo: p.punto_emision,
      }))
    );
  }

  async function onSerieChange(
    idPunto: number | null,
    seriesDisponibles: EstablecimientoFactura[] = establecimientos,
    formasDisponibles: FormaPagoSri[] = formasPagoSri
  ) {
    setIdPuntoEmision(idPunto);
    setSecuencial(null);
    if (!idPunto) {
      setIdEstablecimiento(null);
      return;
    }

    const opcion = aSerieOpciones(seriesDisponibles).find((s) => s.id === idPunto);
    setIdEstablecimiento(opcion?.idEstablecimiento ?? null);
    setEstablecimientoCodigo(opcion?.establecimientoCodigo ?? '');
    setPuntoEmisionCodigo(opcion?.puntoEmisionCodigo ?? '');
    const est = seriesDisponibles.find((e) => e.id_establecimiento === opcion?.idEstablecimiento);
    if (est && !formaPagoTocada.current && est.id_forma_pago_sri_def) {
      const fp = formasDisponibles.find((f) => f.id === est.id_forma_pago_sri_def);
      if (fp) setFormaPago(fp.codigo);
    }

    setCargandoSecuencial(true);
    try {
      const res = await obtenerSecuencial(idPunto);
      setSecuencial(res.formateado);
    } catch (err) {
      setError(mensajeError(err, 'No se pudo obtener el siguiente número de factura.'));
    } finally {
      setCargandoSecuencial(false);
    }
  }

  const serieOpciones = aSerieOpciones(establecimientos);

  /** Copia los datos de la factura ya guardada al formulario y pasa a modo edición. */
  function iniciarEdicion() {
    if (!cabeceraLectura) return;
    setEstablecimientoCodigo(cabeceraLectura.establecimiento);
    setPuntoEmisionCodigo(cabeceraLectura.punto_emision);
    setSecuencial(cabeceraLectura.secuencial);
    setIdEstablecimiento(cabeceraLectura.id_establecimiento);
    setIdPuntoEmision(cabeceraLectura.id_punto_emision);

    setClienteSeleccionado({
      id: cabeceraLectura.id_cliente,
      nombre: cabeceraLectura.cliente_nombre,
      identificacion: cabeceraLectura.cliente_ruc,
      tipo_id: '',
      nombre_tipo_id: null,
      email: '',
      telefono: null,
      direccion: null,
      provincia: null,
      ciudad: null,
      nombre_provincia: null,
      nombre_ciudad: null,
      status: 1,
    });
    setFechaEmision(fechaDesdeISO(cabeceraLectura.fecha_emision));
    setIdBodega(detallesLectura[0]?.id_bodega ?? null);
    setIdVendedor(cabeceraLectura.id_vendedor ?? null);
    setDiasCredito(String(cabeceraLectura.dias_credito ?? 0));
    setObservaciones(cabeceraLectura.observaciones ?? '');
    formaPagoTocada.current = true;
    setFormaPago(pagosLectura[0]?.forma_pago ?? null);

    setLineas(
      detallesLectura.map((d) => {
        const cantidad = Number(d.cantidad);
        const base = Number(d.precio_unitario);
        const ivaTotal = d.impuestos.reduce((s, i) => s + Number(i.valor), 0);
        const pvpUnit = cantidad > 0 ? (Number(d.precio_total_sin_impuesto) + ivaTotal) / cantidad : base;
        return {
          id_producto: d.id_producto ?? 0,
          producto_nombre: d.producto_nombre,
          codigo: d.producto_codigo ?? '',
          precioBase: base,
          pvp: pvpUnit,
          cantidad,
        };
      })
    );
    setModo('editar');
  }

  function buscarClienteDebounced(texto: string) {
    setClienteTexto(texto);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    if (texto.trim().length < 2) {
      setClienteResultados([]);
      return;
    }
    debounceRef.current = setTimeout(async () => {
      try {
        const resp = await listarClientes({ buscar: texto.trim(), page: 1 });
        setClienteResultados(resp.data);
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
        const resp = await listarProductos({ buscar: texto.trim(), page: 1 });
        setProductoResultados(resp.data);
      } catch {
        setProductoResultados([]);
      }
    }, 350);
  }

  function agregarProducto(p: ProductoListado) {
    setLineas((prev) => [
      ...prev,
      {
        id_producto: p.id,
        producto_nombre: p.nombre,
        codigo: p.codigo,
        precioBase: Number(p.precio_base),
        pvp: Number(p.pvp ?? p.precio_base),
        cantidad: 1,
      },
    ]);
    setProductoTexto('');
    setProductoResultados([]);
  }

  function actualizarCantidad(index: number, cantidad: number) {
    setLineas((prev) => {
      const copia = [...prev];
      copia[index] = { ...copia[index], cantidad };
      return copia;
    });
  }

  function quitarLinea(index: number) {
    setLineas((prev) => prev.filter((_, i) => i !== index));
  }

  const subtotal = lineas.reduce((acc, l) => acc + l.precioBase * l.cantidad, 0);
  const total = lineas.reduce((acc, l) => acc + l.pvp * l.cantidad, 0);
  const iva = total - subtotal;

  async function guardar() {
    if (!clienteSeleccionado) {
      Alert.alert('Falta el cliente', 'Selecciona un cliente de la lista.');
      return;
    }
    if (lineas.length === 0) {
      Alert.alert('Sin productos', 'Agrega al menos un producto.');
      return;
    }
    if (!idPuntoEmision || !secuencial) {
      Alert.alert('Falta la serie', 'No se pudo determinar el número de factura. Vuelve a intentar.');
      return;
    }
    if (!formaPago) {
      Alert.alert('Falta la forma de pago', 'Selecciona una forma de pago.');
      return;
    }

    setGuardando(true);
    setError(null);
    try {
      const detalles = lineas.map((l) => ({ id_producto: l.id_producto, cantidad: l.cantidad }));
      if (modo === 'editar' && idFactura) {
        await actualizarFactura(idFactura, {
          fecha_emision: fechaLocalISO(fechaEmision),
          id_cliente: clienteSeleccionado.id,
          dias_credito: Number(diasCredito) || 0,
          observaciones: observaciones.trim() || undefined,
          id_vendedor: idVendedor ?? undefined,
          id_bodega: idBodega ?? undefined,
          forma_pago: formaPago,
          detalles,
        });
        Alert.alert('Factura actualizada', 'Los cambios se guardaron correctamente.');
        setModo('ver');
        setCargando(true);
        await cargarFactura();
      } else {
        const res = await crearFactura({
          fecha_emision: fechaLocalISO(fechaEmision),
          id_cliente: clienteSeleccionado.id,
          id_establecimiento: idEstablecimiento as number,
          id_punto_emision: idPuntoEmision,
          establecimiento: establecimientoCodigo,
          punto_emision: puntoEmisionCodigo,
          secuencial,
          dias_credito: Number(diasCredito) || 0,
          observaciones: observaciones.trim() || undefined,
          id_vendedor: idVendedor ?? undefined,
          id_bodega: idBodega ?? undefined,
          forma_pago: formaPago,
          detalles,
        });
        Alert.alert(
          'Factura guardada',
          `Se creó la factura ${establecimientoCodigo}-${puntoEmisionCodigo}-${secuencial} como borrador.`,
          [{ text: 'OK', onPress: () => navigation.replace('FacturaVentaForm', { id: res.id }) }]
        );
      }
    } catch (err) {
      setError(mensajeError(err, 'No se pudo guardar la factura.'));
    } finally {
      setGuardando(false);
    }
  }

  async function descargarYCompartir(id: number) {
    setDescargandoPdf(true);
    setError(null);
    try {
      const uri = await descargarPdfFactura(id);
      if (await Sharing.isAvailableAsync()) {
        await Sharing.shareAsync(uri, { mimeType: 'application/pdf', dialogTitle: 'Factura' });
      } else {
        Alert.alert('PDF descargado', 'El PDF se guardó en el dispositivo, pero no se pudo abrir el diálogo para compartirlo.');
      }
    } catch (err) {
      setError(mensajeError(err, 'No se pudo descargar el PDF.'));
    } finally {
      setDescargandoPdf(false);
    }
  }

  function confirmarEnvioSri(id: number) {
    Alert.alert(
      'Enviar al SRI',
      'Esto firma y transmite la factura al SRI. Puede tardar hasta 20 segundos: no cierres la app ni pierdas la conexión mientras tanto. ¿Continuar?',
      [
        { text: 'Cancelar', style: 'cancel' },
        { text: 'Enviar', onPress: () => enviarSri(id) },
      ]
    );
  }

  async function enviarSri(id: number) {
    setEnviandoSri(true);
    setError(null);
    try {
      const resultado = await enviarFacturaSri(id);
      if (resultado.enviado_ok) {
        Alert.alert(
          'Factura enviada al SRI',
          `Estado: ${resultado.estado}.${resultado.numero_autorizacion ? `\nAutorización: ${resultado.numero_autorizacion}` : ''}`
        );
      } else {
        Alert.alert('El SRI no autorizó la factura', resultado.mensaje || 'Revisa el detalle en el sistema web.');
      }
      await cargarFactura();
    } catch (err) {
      setError(
        mensajeError(
          err,
          'No se pudo confirmar el envío al SRI. Puede que ya se haya procesado del lado del servidor: revisa el estado en unos minutos (recarga esta pantalla) antes de reintentar.'
        )
      );
    } finally {
      setEnviandoSri(false);
    }
  }

  if (cargando) {
    return (
      <View style={styles.centrado}>
        <ActivityIndicator size="large" color="#0d6efd" />
      </View>
    );
  }

  if (modo === 'ver') {
    if (!cabeceraLectura) {
      return (
        <View style={styles.centrado}>
          <Text style={styles.error}>{error ?? 'No se pudo cargar la factura.'}</Text>
        </View>
      );
    }
    const esBorrador = cabeceraLectura.estado === 'borrador';
    const color = COLOR_ESTADO[(cabeceraLectura.estado || '').toUpperCase()] ?? '#6c757d';
    return (
      <ScrollView style={styles.container} contentContainerStyle={{ padding: 16 }}>
        {error ? <Text style={styles.error}>{error}</Text> : null}

        <View style={styles.bloque}>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
            <Text style={styles.tituloBloque}>
              {cabeceraLectura.establecimiento}-{cabeceraLectura.punto_emision}-{cabeceraLectura.secuencial}
            </Text>
            <View style={[styles.badge, { backgroundColor: color + '22', borderColor: color }]}>
              <Text style={[styles.badgeTexto, { color }]}>{cabeceraLectura.estado}</Text>
            </View>
          </View>
          <Text style={styles.dato}>{cabeceraLectura.cliente_nombre}</Text>
          <Text style={styles.datoSub}>{cabeceraLectura.cliente_ruc}</Text>
          <Text style={styles.dato}>Fecha: {String(cabeceraLectura.fecha_emision).slice(0, 10)}</Text>
          {cabeceraLectura.vendedor_nombre ? <Text style={styles.dato}>Vendedor: {cabeceraLectura.vendedor_nombre}</Text> : null}
          {cabeceraLectura.observaciones ? <Text style={styles.dato}>Obs.: {cabeceraLectura.observaciones}</Text> : null}
          {cabeceraLectura.fecha_autorizacion ? (
            <Text style={styles.dato}>Autorizada: {String(cabeceraLectura.fecha_autorizacion).slice(0, 19).replace('T', ' ')}</Text>
          ) : null}
        </View>

        <Text style={styles.tituloSeccion}>Productos</Text>
        {detallesLectura.map((d, i) => (
          <View key={i} style={styles.lineaLectura}>
            <Text style={styles.lineaNombre}>{d.producto_nombre}</Text>
            <Text style={styles.lineaSub}>
              {d.cantidad} x ${Number(d.precio_unitario).toFixed(2)} = ${Number(d.precio_total_sin_impuesto).toFixed(2)}
            </Text>
          </View>
        ))}

        <View style={styles.totalesBox}>
          <View style={[styles.totalFila, styles.totalFilaBorde]}>
            <Text style={[styles.totalLabel, styles.totalLabelFuerte]}>Subtotal</Text>
            <Text style={styles.totalValor}>${Number(cabeceraLectura.total_sin_impuestos).toFixed(2)}</Text>
          </View>
          {totalesIva.map((g) => (
            <View style={styles.totalFila} key={`sub-${g.codigo_porcentaje}`}>
              <Text style={styles.totalLabel}>Subtotal {g.nombre_tarifa_iva}</Text>
              <Text style={styles.totalValor}>${g.base.toFixed(2)}</Text>
            </View>
          ))}
          {totalesIva
            .filter((g) => g.porcentaje > 0 && g.iva > 0)
            .map((g) => (
              <View style={styles.totalFila} key={`iva-${g.codigo_porcentaje}`}>
                <Text style={styles.totalLabel}>(+) IVA {g.nombre_tarifa_iva}</Text>
                <Text style={styles.totalValor}>${g.iva.toFixed(2)}</Text>
              </View>
            ))}
          <View style={[styles.totalFila, styles.totalFilaFinal]}>
            <Text style={styles.totalLabel}>Total</Text>
            <Text style={[styles.totalValor, styles.totalFinal]}>${Number(cabeceraLectura.importe_total).toFixed(2)}</Text>
          </View>
        </View>

        {pagosLectura.length > 0 ? (
          <Text style={styles.dato}>Forma de pago: {pagosLectura[0].nombre_forma_pago}</Text>
        ) : null}

        {esBorrador ? (
          <TouchableOpacity style={styles.botonEditar} onPress={iniciarEdicion} disabled={enviandoSri || descargandoPdf}>
            <Text style={styles.botonGuardarTexto}>Editar factura</Text>
          </TouchableOpacity>
        ) : null}

        {esBorrador ? (
          <TouchableOpacity
            style={styles.botonSri}
            onPress={() => confirmarEnvioSri(cabeceraLectura.id)}
            disabled={enviandoSri || descargandoPdf}
          >
            {enviandoSri ? <ActivityIndicator color="#fff" /> : <Text style={styles.botonGuardarTexto}>Enviar al SRI</Text>}
          </TouchableOpacity>
        ) : null}

        <TouchableOpacity
          style={styles.botonPdf}
          onPress={() => descargarYCompartir(cabeceraLectura.id)}
          disabled={descargandoPdf || enviandoSri}
        >
          {descargandoPdf ? <ActivityIndicator color="#fff" /> : <Text style={styles.botonGuardarTexto}>Descargar / compartir PDF</Text>}
        </TouchableOpacity>
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
    >
      {error ? <Text style={styles.error}>{error}</Text> : null}

      {modo === 'editar' ? (
        <View style={styles.numeroFactura}>
          <Text style={styles.numeroFacturaTexto}>
            Factura {establecimientoCodigo}-{puntoEmisionCodigo}-{secuencial} (no editable)
          </Text>
        </View>
      ) : establecimientos.length === 0 ? (
        <View style={styles.numeroFactura}>
          <Text style={styles.error}>No hay establecimientos configurados para facturar.</Text>
        </View>
      ) : (
        <SelectorLista<number>
          label="Serie"
          value={idPuntoEmision}
          opciones={serieOpciones.map((s) => ({ id: s.id, label: s.label }))}
          onChange={(id) => onSerieChange(id)}
        />
      )}

      {modo !== 'editar' && idPuntoEmision ? (
        <View style={styles.numeroFactura}>
          {cargandoSecuencial ? (
            <ActivityIndicator color="#0d6efd" />
          ) : (
            <Text style={styles.numeroFacturaTexto}>
              Factura {establecimientoCodigo}-{puntoEmisionCodigo}-{secuencial ?? '—'}
            </Text>
          )}
        </View>
      ) : null}

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

      <View style={{ marginTop: 12 }}>
        <SelectorFechaHora label="Fecha de emisión" mode="date" value={fechaEmision} onChange={(d) => d && setFechaEmision(d)} permiteQuitar={false} />
      </View>

      <View style={{ marginTop: 12 }}>
        <SelectorLista<string>
          label="Forma de pago"
          value={formaPago}
          opciones={formasPagoSri.map((f) => ({ id: f.codigo, label: f.nombre }))}
          onChange={(id) => {
            formaPagoTocada.current = true;
            setFormaPago(id);
          }}
        />
      </View>

      <View style={{ marginTop: 12 }}>
        <SelectorLista<number>
          label="Bodega"
          value={idBodega}
          opciones={bodegas.map((b) => ({ id: b.id, label: b.nombre }))}
          onChange={setIdBodega}
          placeholder="Opcional"
        />
      </View>

      <View style={{ marginTop: 12 }}>
        <SelectorLista<number>
          label="Vendedor"
          value={idVendedor}
          opciones={vendedores.map((v) => ({ id: v.id, label: v.nombre }))}
          onChange={setIdVendedor}
          placeholder="Opcional"
        />
      </View>

      <Text style={styles.label}>Días de crédito</Text>
      <TextInput style={styles.input} value={diasCredito} onChangeText={setDiasCredito} keyboardType="number-pad" />

      <Text style={styles.label}>Observaciones</Text>
      <TextInput style={styles.input} value={observaciones} onChangeText={setObservaciones} placeholder="Opcional" multiline />

      <Text style={[styles.label, { marginTop: 20 }]}>Agregar producto</Text>
      <TextInput style={styles.input} value={productoTexto} onChangeText={buscarProductoDebounced} placeholder="Buscar por código o nombre..." />
      {productoResultados.map((p) => (
        <TouchableOpacity key={p.id} style={styles.resultado} onPress={() => agregarProducto(p)}>
          <Text style={styles.resultadoNombre}>{p.nombre}</Text>
          <Text style={styles.resultadoSub}>
            {p.codigo} · ${Number(p.precio_base).toFixed(2)}
          </Text>
        </TouchableOpacity>
      ))}

      <Text style={[styles.tituloSeccion, { marginTop: 16 }]}>Detalle de la factura</Text>
      {lineas.length === 0 ? (
        <Text style={styles.vacio}>Aún no agregas productos.</Text>
      ) : (
        lineas.map((l, i) => (
          <View key={i} style={styles.lineaEdit}>
            <View style={{ flex: 1 }}>
              <Text style={styles.lineaNombre}>{l.producto_nombre}</Text>
              <Text style={styles.lineaSub}>
                ${l.precioBase.toFixed(2)} c/u · ${(l.precioBase * l.cantidad).toFixed(2)}
              </Text>
            </View>
            <TextInput
              style={styles.inputCantidad}
              keyboardType="decimal-pad"
              value={String(l.cantidad)}
              onChangeText={(v) => actualizarCantidad(i, Number(v.replace(',', '.')) || 0)}
            />
            <TouchableOpacity onPress={() => quitarLinea(i)}>
              <Text style={styles.quitar}>Quitar</Text>
            </TouchableOpacity>
          </View>
        ))
      )}

      {lineas.length > 0 ? (
        <View style={styles.totalesBox}>
          <View style={styles.totalFila}>
            <Text style={styles.totalLabel}>Subtotal</Text>
            <Text style={styles.totalValor}>${subtotal.toFixed(2)}</Text>
          </View>
          <View style={styles.totalFila}>
            <Text style={styles.totalLabel}>IVA</Text>
            <Text style={styles.totalValor}>${iva.toFixed(2)}</Text>
          </View>
          <View style={styles.totalFila}>
            <Text style={styles.totalLabel}>Total</Text>
            <Text style={[styles.totalValor, styles.totalFinal]}>${total.toFixed(2)}</Text>
          </View>
        </View>
      ) : null}

      {modo === 'editar' ? (
        <TouchableOpacity
          style={styles.botonCancelar}
          onPress={() => {
            setModo('ver');
          }}
          disabled={guardando}
        >
          <Text style={styles.botonCancelarTexto}>Cancelar edición</Text>
        </TouchableOpacity>
      ) : null}

      <TouchableOpacity style={styles.botonGuardar} onPress={guardar} disabled={guardando}>
        {guardando ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={styles.botonGuardarTexto}>{modo === 'editar' ? 'Guardar cambios' : 'Guardar factura'}</Text>
        )}
      </TouchableOpacity>
    </KeyboardAwareScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f6f8' },
  centrado: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  error: { color: '#dc3545', marginBottom: 12, textAlign: 'center' },
  numeroFactura: { backgroundColor: '#e7f1ff', borderRadius: 8, padding: 10, alignItems: 'center', marginBottom: 8, gap: 8 },
  numeroFacturaTexto: { color: '#0d6efd', fontWeight: '700', fontSize: 15 },
  label: { fontSize: 13, color: '#333', marginTop: 12, marginBottom: 4, fontWeight: '600' },
  input: { borderWidth: 1, borderColor: '#ccc', borderRadius: 8, paddingHorizontal: 12, paddingVertical: 10, fontSize: 15, backgroundColor: '#fff' },
  seleccionado: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#e7f1ff', borderRadius: 8, padding: 12 },
  resultado: { backgroundColor: '#fff', padding: 10, borderBottomWidth: 1, borderBottomColor: '#eee' },
  resultadoNombre: { fontSize: 14, fontWeight: '600' },
  resultadoSub: { fontSize: 12, color: '#777' },
  quitar: { color: '#dc3545', fontWeight: '600' },
  tituloSeccion: { fontSize: 15, fontWeight: '700', marginTop: 8, marginBottom: 8 },
  vacio: { color: '#888' },
  lineaEdit: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#fff', borderRadius: 8, padding: 12, marginBottom: 8, gap: 10 },
  lineaNombre: { fontSize: 14, fontWeight: '600' },
  inputCantidad: { borderWidth: 1, borderColor: '#ccc', borderRadius: 6, paddingHorizontal: 8, paddingVertical: 4, width: 56, fontSize: 13, textAlign: 'center' },
  botonGuardar: { backgroundColor: '#0d6efd', borderRadius: 8, paddingVertical: 14, marginTop: 20, alignItems: 'center' },
  botonEditar: { backgroundColor: '#0d6efd', borderRadius: 8, paddingVertical: 14, marginTop: 20, alignItems: 'center' },
  botonSri: { backgroundColor: '#6f42c1', borderRadius: 8, paddingVertical: 14, marginTop: 12, alignItems: 'center' },
  botonPdf: { backgroundColor: '#198754', borderRadius: 8, paddingVertical: 14, marginTop: 12, alignItems: 'center' },
  botonCancelar: { borderRadius: 8, paddingVertical: 12, marginTop: 20, alignItems: 'center', borderWidth: 1, borderColor: '#ccc' },
  botonCancelarTexto: { color: '#666', fontWeight: '600' },
  botonGuardarTexto: { color: '#fff', fontSize: 16, fontWeight: '600' },
  bloque: { backgroundColor: '#fff', borderRadius: 10, padding: 16, marginBottom: 16 },
  tituloBloque: { fontSize: 16, fontWeight: '700' },
  dato: { fontSize: 14, color: '#444', marginTop: 2 },
  datoSub: { fontSize: 12, color: '#777' },
  lineaLectura: { backgroundColor: '#fff', borderRadius: 8, padding: 12, marginBottom: 8 },
  lineaSub: { fontSize: 13, color: '#666', marginTop: 2 },
  badge: { borderWidth: 1, borderRadius: 20, paddingHorizontal: 10, paddingVertical: 4 },
  badgeTexto: { fontSize: 11, fontWeight: '700' },
  totalesBox: { backgroundColor: '#fff', borderRadius: 8, padding: 12, marginTop: 8 },
  totalFila: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 3 },
  totalFilaBorde: { borderBottomWidth: 1, borderBottomColor: '#eee', paddingBottom: 6, marginBottom: 2 },
  totalFilaFinal: { borderTopWidth: 1, borderTopColor: '#eee', paddingTop: 6, marginTop: 2 },
  totalLabel: { fontSize: 13, color: '#666' },
  totalLabelFuerte: { fontWeight: '700', color: '#333' },
  totalValor: { fontSize: 13, fontWeight: '600' },
  totalFinal: { fontSize: 16, color: '#0d6efd', fontWeight: '700' },
});
