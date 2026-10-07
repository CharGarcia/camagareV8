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
  EstablecimientoIngreso,
  FacturaCabecera,
  FacturaDetalleLinea,
  FacturaPago,
  FormaCobro,
  FormaPagoSri,
  obtenerCatalogosFacturas,
  obtenerFactura,
  obtenerFormasCobro,
  obtenerSecuencial,
  obtenerSeries,
  obtenerSeriesIngreso,
  registrarCobro,
  TipoOperacionBancaria,
  TotalPorTarifa,
  VendedorFactura,
} from '../api/facturasVenta';
import { ClienteListado, listarClientes } from '../api/clientes';
import { ProductoListado, listarProductos } from '../api/productos';
import { mensajeError } from '../api/client';
import SelectorFechaHora from '../components/SelectorFechaHora';
import SelectorLista from '../components/SelectorLista';
import { modoIva, r2, redondear, repartirIva } from '../utils/iva';
import { generarUuid } from '../utils/uuid';

// Precio y descuento se guardan como texto mientras se editan (para poder escribir
// "1." o dejar el campo vacío); se convierten con aNumero() al calcular y al guardar.
type LineaFactura = {
  id_producto: number;
  producto_nombre: string;
  codigo: string;
  precioTexto: string;
  descuentoTexto: string;
  ivaPct: number;
  cantidad: number;
};

function aNumero(texto: string): number {
  const n = Number(texto.replace(',', '.'));
  return Number.isFinite(n) ? n : 0;
}

type Decimales = { precio: number; cantidad: number };

/**
 * Subtotal (sin IVA, ya con descuento) de una línea, con los decimales de precio y cantidad
 * de la configuración de facturación, igual que el servidor. El IVA no va aquí: depende de
 * todas las líneas cuando la serie calcula al subtotal (ver calcularTotales).
 */
function calcularLinea(l: LineaFactura, dec: Decimales) {
  const bruto = r2(redondear(aNumero(l.precioTexto), dec.precio) * redondear(l.cantidad, dec.cantidad));
  const descuento = Math.min(Math.max(r2(aNumero(l.descuentoTexto)), 0), bruto);
  return { bruto, descuento, subtotal: r2(bruto - descuento) };
}

/** Líneas con su IVA según el modo de la serie (§9: al subtotal o línea por línea). */
function calcularTotales(lineas: LineaFactura[], dec: Decimales, modo: string | undefined) {
  const bases = lineas.map((l) => calcularLinea(l, dec));
  const ivas = repartirIva(
    bases.map((b, i) => ({ base: b.subtotal, pct: lineas[i].ivaPct })),
    modoIva(modo)
  );
  return bases.map((b, i) => ({ ...b, iva: ivas[i] }));
}

type Modo = 'ver' | 'crear' | 'editar';

const COLOR_ESTADO: Record<string, string> = {
  BORRADOR: '#fd7e14',
  AUTORIZADO: '#198754',
  APROBADO: '#198754',
  DEVUELTA: '#dc3545',
  NO_AUTORIZADO: '#dc3545',
  ANULADO: '#6c757d',
};

const TIPOS_OPERACION_BANCARIA: { id: TipoOperacionBancaria; label: string }[] = [
  { id: 'TRANSFERENCIA', label: 'Transferencia' },
  { id: 'DEPOSITO', label: 'Depósito' },
  { id: 'DEBITO', label: 'Débito' },
  { id: 'CHEQUE', label: 'Cheque' },
];

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
  const guardandoRef = useRef(false);
  // Guardado único (§8): una clave por factura nueva, la misma en todos sus intentos.
  const tokenGuardado = useRef(generarUuid());
  const cobrandoRef = useRef(false);
  const tokenCobro = useRef(generarUuid());
  const [error, setError] = useState<string | null>(null);

  // Modo ver
  const [cabeceraLectura, setCabeceraLectura] = useState<FacturaCabecera | null>(null);
  const [detallesLectura, setDetallesLectura] = useState<FacturaDetalleLinea[]>([]);
  const [pagosLectura, setPagosLectura] = useState<FacturaPago[]>([]);
  const [totalesIva, setTotalesIva] = useState<TotalPorTarifa[]>([]);
  const [saldoPendiente, setSaldoPendiente] = useState(0);
  const [descargandoPdf, setDescargandoPdf] = useState(false);
  const [enviandoSri, setEnviandoSri] = useState(false);

  // Cobrar (solo si estado === 'autorizado' y saldoPendiente > 0)
  const [mostrarFormCobro, setMostrarFormCobro] = useState(false);
  const [formasCobro, setFormasCobro] = useState<FormaCobro[]>([]);
  const [montoCobro, setMontoCobro] = useState('');
  const [idFormaCobro, setIdFormaCobro] = useState<number | null>(null);
  const [observacionesCobro, setObservacionesCobro] = useState('');
  const [cobrando, setCobrando] = useState(false);
  // Serie (establecimiento-punto) e fecha del Ingreso que se genera con el cobro —
  // igual que en la web (selects "Serie"/"Fecha" del cobro rápido); por defecto la
  // favorita del módulo Ingresos (o la primera) y hoy, pero el usuario puede cambiarlas.
  const [establecimientosIngreso, setEstablecimientosIngreso] = useState<EstablecimientoIngreso[]>([]);
  const [idPuntoEmisionCobro, setIdPuntoEmisionCobro] = useState<number | null>(null);
  const [fechaEmisionCobro, setFechaEmisionCobro] = useState<Date>(() => new Date());
  const [cargandoSeriesCobro, setCargandoSeriesCobro] = useState(false);
  // Solo aplican si la forma de cobro elegida es tipo BANCO (igual que el div
  // condicional "fvPagoDivBanco" de la web).
  const [tipoOperacionBancaria, setTipoOperacionBancaria] = useState<TipoOperacionBancaria | null>(null);
  const [numeroReferenciaCobro, setNumeroReferenciaCobro] = useState('');
  const [fechaCobroCheque, setFechaCobroCheque] = useState<Date | null>(null);

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
      setSaldoPendiente(data.saldo_pendiente);
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

  // Series: la serie solo se elige al CREAR (en editar ya está fija), pero se cargan
  // siempre porque traen la configuración de facturación de cada establecimiento
  // (si se puede editar el precio y el descuento), que también aplica al editar.
  // Auto-selección: la serie favorita del usuario (misma estrellita que en la
  // web); si no tiene ninguna marcada, la primera disponible.
  useEffect(() => {
    obtenerSeries()
      .then(({ establecimientos: series, id_punto_emision_favorito }) => {
        setEstablecimientos(series);
        if (idFactura) return;
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

  function aSerieOpcionesIngreso(seriesDisponibles: EstablecimientoIngreso[]) {
    return seriesDisponibles.flatMap((e) =>
      e.puntos_emision.map((p) => ({ id: p.id_punto_emision, label: `${e.establecimiento}-${p.punto_emision}` }))
    );
  }

  const serieCobroOpciones = aSerieOpcionesIngreso(establecimientosIngreso);

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

  // Mientras no se conozca la configuración, se asume lo mismo que la web por
  // defecto (permitido); la API vuelve a validarlo al guardar.
  const configEstablecimiento = establecimientos.find((e) => e.id_establecimiento === idEstablecimiento);
  const puedeEditarPrecio = configEstablecimiento?.editar_precio_factura ?? true;
  const puedeEditarDescuento = configEstablecimiento?.editar_descuento_factura ?? true;

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
        const iva = d.impuestos.find((i) => i.codigo_impuesto === '2') ?? d.impuestos[0];
        const descuento = Number(d.descuento ?? 0);
        return {
          id_producto: d.id_producto ?? 0,
          producto_nombre: d.producto_nombre,
          codigo: d.producto_codigo ?? '',
          precioTexto: String(Number(d.precio_unitario)),
          descuentoTexto: descuento > 0 ? descuento.toFixed(2) : '',
          ivaPct: Number(iva?.tarifa ?? 0),
          cantidad: Number(d.cantidad),
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
    const base = Number(p.precio_base);
    // % de IVA del producto; si el listado no lo trae, se deduce del PVP.
    const ivaPct =
      p.porcentaje_iva_final != null
        ? Number(p.porcentaje_iva_final)
        : base > 0
          ? r2((Number(p.pvp ?? base) / base - 1) * 100)
          : 0;
    setLineas((prev) => [
      ...prev,
      {
        id_producto: p.id,
        producto_nombre: p.nombre,
        codigo: p.codigo,
        precioTexto: String(base),
        descuentoTexto: '',
        ivaPct,
        cantidad: 1,
      },
    ]);
    setProductoTexto('');
    setProductoResultados([]);
  }

  function actualizarLinea(index: number, cambios: Partial<LineaFactura>) {
    setLineas((prev) => {
      const copia = [...prev];
      copia[index] = { ...copia[index], ...cambios };
      return copia;
    });
  }

  function quitarLinea(index: number) {
    setLineas((prev) => prev.filter((_, i) => i !== index));
  }

  // Configuración de facturación de la serie elegida: decimales y modo del IVA.
  const decimales: Decimales = {
    precio: configEstablecimiento?.decimales_precio ?? 2,
    cantidad: configEstablecimiento?.decimales_cantidad ?? 2,
  };
  const modoIvaSerie = configEstablecimiento?.puntos_emision.find((p) => p.id_punto_emision === idPuntoEmision)?.calculo_iva;
  const calculos = calcularTotales(lineas, decimales, modoIvaSerie);
  const totalDescuento = r2(calculos.reduce((acc, c) => acc + c.descuento, 0));
  const subtotal = r2(calculos.reduce((acc, c) => acc + c.subtotal, 0));
  const iva = r2(calculos.reduce((acc, c) => acc + c.iva, 0));
  const total = r2(subtotal + iva);

  async function guardar() {
    // Candado síncrono (§8): un doble toque llega antes de que React desactive el botón.
    if (guardandoRef.current) return;
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

    for (const l of lineas) {
      const c = calcularLinea(l, decimales);
      if (aNumero(l.precioTexto) < 0) {
        Alert.alert('Precio inválido', `El precio de ${l.producto_nombre} no puede ser negativo.`);
        return;
      }
      if (aNumero(l.descuentoTexto) < 0 || aNumero(l.descuentoTexto) > c.bruto) {
        Alert.alert('Descuento inválido', `El descuento de ${l.producto_nombre} debe estar entre $0.00 y $${c.bruto.toFixed(2)}.`);
        return;
      }
    }

    guardandoRef.current = true;
    let creada = false;
    setGuardando(true);
    setError(null);
    try {
      // Precio y descuento solo viajan si el establecimiento los permite; la API
      // igual los ignora si no.
      const detalles = lineas.map((l) => ({
        id_producto: l.id_producto,
        cantidad: l.cantidad,
        ...(puedeEditarPrecio ? { precio_unitario: aNumero(l.precioTexto) } : {}),
        ...(puedeEditarDescuento ? { descuento: r2(aNumero(l.descuentoTexto)) } : {}),
      }));
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
          token_guardado: tokenGuardado.current,
        });
        // Creada: el botón queda desactivado hasta abrir la factura (si se reactivara, otro
        // toque crearía otra).
        creada = true;
        Alert.alert(
          res.ya_existia ? 'Factura ya registrada' : 'Factura guardada',
          res.ya_existia
            ? 'Esta factura ya se había guardado en un intento anterior; no se creó otra.'
            : `Se creó la factura ${establecimientoCodigo}-${puntoEmisionCodigo}-${secuencial} como borrador.`,
          [{ text: 'OK', onPress: () => navigation.replace('FacturaVentaForm', { id: res.id }) }],
          { cancelable: false }
        );
      }
    } catch (err) {
      setError(mensajeError(err, 'No se pudo guardar la factura.'));
    } finally {
      if (!creada) {
        guardandoRef.current = false;
        setGuardando(false);
      }
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

  async function abrirFormCobro() {
    // Clave nueva por cobro (§8): se mantiene en los reintentos de ESTE cobro.
    tokenCobro.current = generarUuid();
    setMostrarFormCobro(true);
    setMontoCobro(saldoPendiente.toFixed(2));
    setFechaEmisionCobro(new Date());
    if (formasCobro.length === 0) {
      try {
        setFormasCobro(await obtenerFormasCobro());
      } catch (err) {
        setError(mensajeError(err, 'No se pudieron cargar las formas de cobro.'));
      }
    }
    if (establecimientosIngreso.length === 0) {
      setCargandoSeriesCobro(true);
      try {
        const { establecimientos: series, id_punto_emision_favorito } = await obtenerSeriesIngreso();
        setEstablecimientosIngreso(series);
        const opciones = aSerieOpcionesIngreso(series);
        if (opciones.length > 0) {
          const favorita = opciones.find((o) => o.id === id_punto_emision_favorito);
          setIdPuntoEmisionCobro((favorita ?? opciones[0]).id);
        }
      } catch (err) {
        setError(mensajeError(err, 'No se pudo cargar la serie para el cobro.'));
      } finally {
        setCargandoSeriesCobro(false);
      }
    }
  }

  function onCambiarFormaCobro(id: number | null) {
    setIdFormaCobro(id);
    // Al cambiar de forma de cobro se limpian los datos bancarios: si la nueva
    // forma no es tipo BANCO no aplican, y si sí lo es, mejor no arrastrar los
    // de una forma distinta.
    setTipoOperacionBancaria(null);
    setNumeroReferenciaCobro('');
    setFechaCobroCheque(null);
  }

  const formaCobroSeleccionada = formasCobro.find((f) => f.id === idFormaCobro) ?? null;
  const esFormaCobroBanco = formaCobroSeleccionada?.tipo === 'BANCO';

  async function confirmarCobro(id: number) {
    // Candado síncrono (§8): un doble toque llega antes de que React desactive el botón.
    if (cobrandoRef.current) return;
    const monto = Number(montoCobro.replace(',', '.'));
    if (!monto || monto <= 0) {
      Alert.alert('Monto inválido', 'Ingresa un monto mayor a cero.');
      return;
    }
    if (monto > saldoPendiente + 0.01) {
      Alert.alert('Monto inválido', `El monto no puede superar el saldo pendiente ($${saldoPendiente.toFixed(2)}).`);
      return;
    }
    if (!idFormaCobro) {
      Alert.alert('Falta la forma de cobro', 'Selecciona cómo se recibió el pago.');
      return;
    }
    if (!idPuntoEmisionCobro) {
      Alert.alert('Falta la serie', 'Selecciona la serie con la que se registrará el ingreso.');
      return;
    }
    if (esFormaCobroBanco && !tipoOperacionBancaria) {
      Alert.alert('Falta la operación bancaria', 'Selecciona si fue transferencia, depósito, débito o cheque.');
      return;
    }
    if (esFormaCobroBanco && tipoOperacionBancaria === 'CHEQUE' && !fechaCobroCheque) {
      Alert.alert('Falta la fecha del cheque', 'Indica la fecha en que se podrá cobrar el cheque.');
      return;
    }

    cobrandoRef.current = true;
    setCobrando(true);
    setError(null);
    try {
      const res = await registrarCobro({
        token_guardado: tokenCobro.current,
        id_factura: id,
        monto,
        id_forma_cobro: idFormaCobro,
        id_punto_emision: idPuntoEmisionCobro,
        fecha_emision: fechaLocalISO(fechaEmisionCobro),
        observaciones: observacionesCobro.trim() || undefined,
        tipo_operacion_bancaria: esFormaCobroBanco ? tipoOperacionBancaria ?? undefined : undefined,
        numero_referencia: esFormaCobroBanco ? numeroReferenciaCobro.trim() || undefined : undefined,
        fecha_cobro: esFormaCobroBanco && tipoOperacionBancaria === 'CHEQUE' && fechaCobroCheque ? fechaLocalISO(fechaCobroCheque) : undefined,
      });
      Alert.alert(
        res.ya_existia ? 'Cobro ya registrado' : 'Cobro registrado',
        res.ya_existia
          ? 'Este cobro ya se había registrado en un intento anterior; no se registró otro.'
          : `Se registró un cobro de ${monto.toFixed(2)}.`
      );
      tokenCobro.current = generarUuid();
      setMostrarFormCobro(false);
      setMontoCobro('');
      setIdFormaCobro(null);
      setObservacionesCobro('');
      setTipoOperacionBancaria(null);
      setNumeroReferenciaCobro('');
      setFechaCobroCheque(null);
      await cargarFactura();
    } catch (err) {
      setError(mensajeError(err, 'No se pudo registrar el cobro.'));
    } finally {
      cobrandoRef.current = false;
      setCobrando(false);
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
    const puedeCobrar = cabeceraLectura.estado === 'autorizado' && saldoPendiente > 0.01;
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
              {d.cantidad} x ${Number(d.precio_unitario).toFixed(2)}
              {Number(d.descuento ?? 0) > 0 ? ` - desc. $${Number(d.descuento).toFixed(2)}` : ''} = $
              {Number(d.precio_total_sin_impuesto).toFixed(2)}
            </Text>
          </View>
        ))}

        <View style={styles.totalesBox}>
          {Number(cabeceraLectura.total_descuento) > 0 ? (
            <View style={styles.totalFila}>
              <Text style={styles.totalLabel}>Descuento</Text>
              <Text style={[styles.totalValor, styles.textoDescuento]}>-${Number(cabeceraLectura.total_descuento).toFixed(2)}</Text>
            </View>
          ) : null}
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

        {cabeceraLectura.estado === 'autorizado' && saldoPendiente > 0.01 ? (
          <Text style={[styles.dato, styles.saldoPendienteTexto]}>
            Saldo pendiente: ${saldoPendiente.toFixed(2)}
          </Text>
        ) : null}
        {cabeceraLectura.estado === 'autorizado' && saldoPendiente <= 0.01 ? (
          <View style={styles.badgePagado}>
            <Text style={styles.badgePagadoTexto}>Pagado</Text>
          </View>
        ) : null}

        {puedeCobrar && !mostrarFormCobro ? (
          <TouchableOpacity style={styles.botonCobrar} onPress={abrirFormCobro} disabled={enviandoSri || descargandoPdf}>
            <Text style={styles.botonGuardarTexto}>Cobrar</Text>
          </TouchableOpacity>
        ) : null}

        {puedeCobrar && mostrarFormCobro ? (
          <View style={styles.formCobroBox}>
            <Text style={styles.tituloBloque}>Registrar cobro</Text>

            {cargandoSeriesCobro ? (
              <ActivityIndicator color="#0d6efd" style={{ marginTop: 12 }} />
            ) : (
              <View style={{ marginTop: 12 }}>
                <SelectorLista<number>
                  label="Serie"
                  value={idPuntoEmisionCobro}
                  opciones={serieCobroOpciones}
                  onChange={setIdPuntoEmisionCobro}
                />
              </View>
            )}

            <View style={{ marginTop: 12 }}>
              <SelectorFechaHora label="Fecha" mode="date" value={fechaEmisionCobro} onChange={(d) => d && setFechaEmisionCobro(d)} permiteQuitar={false} />
            </View>

            <Text style={styles.label}>Monto</Text>
            <TextInput style={styles.input} value={montoCobro} onChangeText={setMontoCobro} keyboardType="decimal-pad" />

            <View style={{ marginTop: 12 }}>
              <SelectorLista<number>
                label="Forma de cobro"
                value={idFormaCobro}
                opciones={formasCobro.map((f) => ({ id: f.id, label: f.nombre }))}
                onChange={onCambiarFormaCobro}
              />
            </View>

            {esFormaCobroBanco ? (
              <View style={styles.bancoBox}>
                <View style={{ marginTop: 0 }}>
                  <SelectorLista<TipoOperacionBancaria>
                    label="Operación bancaria"
                    value={tipoOperacionBancaria}
                    opciones={TIPOS_OPERACION_BANCARIA}
                    onChange={setTipoOperacionBancaria}
                  />
                </View>

                <Text style={styles.label}>Nº de referencia / cheque</Text>
                <TextInput
                  style={styles.input}
                  value={numeroReferenciaCobro}
                  onChangeText={setNumeroReferenciaCobro}
                  placeholder="Opcional"
                />

                {tipoOperacionBancaria === 'CHEQUE' ? (
                  <View style={{ marginTop: 12 }}>
                    <SelectorFechaHora
                      label="Fecha de cobro del cheque"
                      mode="date"
                      value={fechaCobroCheque}
                      onChange={setFechaCobroCheque}
                    />
                  </View>
                ) : null}
              </View>
            ) : null}

            <Text style={styles.label}>Observaciones</Text>
            <TextInput style={styles.input} value={observacionesCobro} onChangeText={setObservacionesCobro} placeholder="Opcional" multiline />

            <View style={styles.filaBotonesCobro}>
              <TouchableOpacity
                style={[styles.botonCancelar, { flex: 1, marginTop: 0, justifyContent: 'center' }]}
                onPress={() => setMostrarFormCobro(false)}
                disabled={cobrando}
              >
                <Text style={styles.botonCancelarTexto}>Cancelar</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.botonCobrar, { flex: 1, marginTop: 0 }]}
                onPress={() => confirmarCobro(cabeceraLectura.id)}
                disabled={cobrando}
              >
                {cobrando ? <ActivityIndicator color="#fff" /> : <Text style={styles.botonGuardarTexto}>Registrar cobro</Text>}
              </TouchableOpacity>
            </View>
          </View>
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
        lineas.map((l, i) => {
          const c = calculos[i];
          return (
            <View key={i} style={styles.lineaEditBox}>
              <View style={styles.lineaEditCabecera}>
                <Text style={[styles.lineaNombre, { flex: 1 }]}>{l.producto_nombre}</Text>
                <TouchableOpacity onPress={() => quitarLinea(i)}>
                  <Text style={styles.quitar}>Quitar</Text>
                </TouchableOpacity>
              </View>
              <View style={styles.lineaCampos}>
                <View style={styles.lineaCampo}>
                  <Text style={styles.lineaCampoLabel}>Cantidad</Text>
                  <TextInput
                    style={styles.inputLinea}
                    keyboardType="decimal-pad"
                    value={String(l.cantidad)}
                    onChangeText={(v) => actualizarLinea(i, { cantidad: aNumero(v) })}
                  />
                </View>
                <View style={styles.lineaCampo}>
                  <Text style={styles.lineaCampoLabel}>Precio (sin IVA)</Text>
                  <TextInput
                    style={[styles.inputLinea, !puedeEditarPrecio && styles.inputLineaBloqueado]}
                    keyboardType="decimal-pad"
                    value={l.precioTexto}
                    editable={puedeEditarPrecio}
                    onChangeText={(v) => actualizarLinea(i, { precioTexto: v })}
                  />
                </View>
                {puedeEditarDescuento ? (
                  <View style={styles.lineaCampo}>
                    <Text style={styles.lineaCampoLabel}>Descuento $</Text>
                    <TextInput
                      style={[styles.inputLinea, styles.inputDescuento]}
                      keyboardType="decimal-pad"
                      value={l.descuentoTexto}
                      placeholder="0.00"
                      onChangeText={(v) => actualizarLinea(i, { descuentoTexto: v })}
                    />
                  </View>
                ) : null}
              </View>
              <Text style={styles.lineaSub}>
                Subtotal ${c.subtotal.toFixed(2)}
                {c.descuento > 0 ? ` (desc. $${c.descuento.toFixed(2)})` : ''} · Total ${(c.subtotal + c.iva).toFixed(2)}
              </Text>
            </View>
          );
        })
      )}

      {lineas.length > 0 ? (
        <View style={styles.totalesBox}>
          {totalDescuento > 0 ? (
            <View style={styles.totalFila}>
              <Text style={styles.totalLabel}>Descuento</Text>
              <Text style={[styles.totalValor, styles.textoDescuento]}>-${totalDescuento.toFixed(2)}</Text>
            </View>
          ) : null}
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
  lineaEditBox: { backgroundColor: '#fff', borderRadius: 8, padding: 12, marginBottom: 8 },
  lineaEditCabecera: { flexDirection: 'row', alignItems: 'flex-start', gap: 10 },
  lineaCampos: { flexDirection: 'row', gap: 8, marginTop: 8, marginBottom: 6 },
  lineaCampo: { flex: 1 },
  lineaCampoLabel: { fontSize: 11, color: '#777', marginBottom: 2 },
  lineaNombre: { fontSize: 14, fontWeight: '600' },
  inputLinea: { borderWidth: 1, borderColor: '#ccc', borderRadius: 6, paddingHorizontal: 8, paddingVertical: 6, fontSize: 13, textAlign: 'right', backgroundColor: '#fff' },
  inputLineaBloqueado: { backgroundColor: '#f1f1f1', color: '#777' },
  inputDescuento: { color: '#dc3545' },
  textoDescuento: { color: '#dc3545' },
  botonGuardar: { backgroundColor: '#0d6efd', borderRadius: 8, paddingVertical: 14, marginTop: 20, alignItems: 'center' },
  botonEditar: { backgroundColor: '#0d6efd', borderRadius: 8, paddingVertical: 14, marginTop: 20, alignItems: 'center' },
  botonSri: { backgroundColor: '#6f42c1', borderRadius: 8, paddingVertical: 14, marginTop: 12, alignItems: 'center' },
  botonPdf: { backgroundColor: '#198754', borderRadius: 8, paddingVertical: 14, marginTop: 12, alignItems: 'center' },
  botonCancelar: { borderRadius: 8, paddingVertical: 12, marginTop: 20, alignItems: 'center', borderWidth: 1, borderColor: '#ccc' },
  botonCancelarTexto: { color: '#666', fontWeight: '600' },
  botonCobrar: { backgroundColor: '#fd7e14', borderRadius: 8, paddingVertical: 14, marginTop: 12, alignItems: 'center' },
  saldoPendienteTexto: { color: '#fd7e14', fontWeight: '700' },
  badgePagado: {
    alignSelf: 'flex-start',
    borderWidth: 1,
    borderColor: '#198754',
    backgroundColor: '#19875422',
    borderRadius: 20,
    paddingHorizontal: 12,
    paddingVertical: 4,
    marginTop: 4,
  },
  badgePagadoTexto: { color: '#198754', fontWeight: '700', fontSize: 12 },
  formCobroBox: { backgroundColor: '#fff', borderRadius: 10, padding: 16, marginTop: 12 },
  bancoBox: {
    backgroundColor: '#fff8e6',
    borderWidth: 1,
    borderColor: '#ffe69c',
    borderRadius: 8,
    padding: 12,
    marginTop: 12,
  },
  filaBotonesCobro: { flexDirection: 'row', gap: 10, marginTop: 20, alignItems: 'stretch' },
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
