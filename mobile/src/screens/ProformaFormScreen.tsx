import React, { useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Alert, StyleSheet, Switch, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { KeyboardAwareScrollView } from 'react-native-keyboard-aware-scroll-view';
import * as Sharing from 'expo-sharing';
import { useNavigation, useRoute } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RouteProp } from '@react-navigation/native';
import type { RootStackParamList } from '../navigation/RootNavigator';
import {
  actualizarProforma,
  buscarClientesProforma,
  buscarProductosProforma,
  cambiarEstadoProforma,
  ClienteProforma,
  convertirProformaAFactura,
  convertirProformaAPedido,
  crearProforma,
  descargarPdfProforma,
  duplicarProforma,
  enviarCorreoProforma,
  ESTADO_PROFORMA,
  EstadoProforma,
  obtenerCatalogosProforma,
  obtenerProforma,
  obtenerSecuencialProforma,
  obtenerSeriesProforma,
  ProductoProforma,
  ProformaCabecera,
  ProformaDetalleLinea,
  SerieProforma,
} from '../api/proformas';
import { mensajeError } from '../api/client';
import SelectorFechaHora from '../components/SelectorFechaHora';
import SelectorLista from '../components/SelectorLista';
import { modoIva, r2, redondear, repartirIva } from '../utils/iva';
import { generarUuid } from '../utils/uuid';

type Modo = 'ver' | 'crear' | 'editar';

// Precio y descuento se guardan como texto mientras se editan (para poder escribir
// "1." o dejar el campo vacío). En proformas son siempre editables, igual que en la web.
type Linea = {
  idDetalle?: number;
  id_producto: number | null;
  descripcion: string;
  codigo: string;
  cantidadTexto: string;
  precioTexto: string;
  descuentoTexto: string;
  ivaPct: number;
};

function aNumero(texto: string): number {
  const n = Number(String(texto).replace(',', '.'));
  return Number.isFinite(n) ? n : 0;
}

type Decimales = { precio: number; cantidad: number };

/**
 * Subtotal (sin IVA, ya con descuento) de una línea, con los decimales de precio y cantidad
 * de la configuración de facturación, igual que ProformaService::normalizarImportes().
 */
function calcularLinea(l: Linea, dec: Decimales) {
  const bruto = r2(redondear(aNumero(l.cantidadTexto), dec.cantidad) * redondear(aNumero(l.precioTexto), dec.precio));
  const descuento = Math.min(Math.max(r2(aNumero(l.descuentoTexto)), 0), bruto);
  return { bruto, descuento, subtotal: r2(bruto - descuento) };
}

/** Líneas con su IVA según el modo de la serie (§9: al subtotal o línea por línea). El servidor recalcula al guardar. */
function calcularTotales(lineas: Linea[], dec: Decimales, modo: string | undefined) {
  const bases = lineas.map((l) => calcularLinea(l, dec));
  const ivas = repartirIva(
    bases.map((b, i) => ({ base: b.subtotal, pct: lineas[i].ivaPct })),
    modoIva(modo)
  );
  return bases.map((b, i) => ({ ...b, iva: ivas[i] }));
}

function fechaISO(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function fechaDesdeISO(iso: string): Date {
  const [y, m, d] = String(iso).slice(0, 10).split('-').map(Number);
  return new Date(y, (m || 1) - 1, d || 1);
}

/** d-m-Y, el formato de fechas del sistema. */
function fechaVista(d: Date): string {
  return `${String(d.getDate()).padStart(2, '0')}-${String(d.getMonth() + 1).padStart(2, '0')}-${d.getFullYear()}`;
}

function ivaDeLinea(d: ProformaDetalleLinea): number {
  const iva = d.impuestos.find((i) => i.codigo_impuesto === '2');
  return Number(iva?.tarifa ?? 0);
}

export default function ProformaFormScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const route = useRoute<RouteProp<RootStackParamList, 'ProformaForm'>>();
  const idProforma = route.params?.id;

  const [modo, setModo] = useState<Modo>(idProforma ? 'ver' : 'crear');
  const [cargando, setCargando] = useState(!!idProforma);
  const [guardando, setGuardando] = useState(false);
  const guardandoRef = useRef(false);
  // Guardado único (§8): una clave por proforma nueva, la misma en todos sus intentos.
  const tokenGuardado = useRef(generarUuid());
  const [accion, setAccion] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  // Modo ver
  const [cabecera, setCabecera] = useState<ProformaCabecera | null>(null);
  const [detallesLectura, setDetallesLectura] = useState<ProformaDetalleLinea[]>([]);
  const [mostrarCorreo, setMostrarCorreo] = useState(false);
  const [correos, setCorreos] = useState('');
  const [adjuntarFicha, setAdjuntarFicha] = useState(false);

  // Serie (solo al crear)
  const [series, setSeries] = useState<SerieProforma[]>([]);
  const [idPuntoEmision, setIdPuntoEmision] = useState<number | null>(null);
  const [numeroPrevio, setNumeroPrevio] = useState<string | null>(null);

  // Formulario
  const [vendedores, setVendedores] = useState<{ id: number; nombre: string }[]>([]);
  const [cliente, setCliente] = useState<ClienteProforma | null>(null);
  const [clienteTexto, setClienteTexto] = useState('');
  const [clienteResultados, setClienteResultados] = useState<ClienteProforma[]>([]);
  const [fechaEmision, setFechaEmision] = useState<Date>(() => new Date());
  const [idVendedor, setIdVendedor] = useState<number | null>(null);
  const [diasVigencia, setDiasVigencia] = useState('15');
  const [observaciones, setObservaciones] = useState('');
  const [productoTexto, setProductoTexto] = useState('');
  const [productoResultados, setProductoResultados] = useState<ProductoProforma[]>([]);
  const [lineas, setLineas] = useState<Linea[]>([]);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    const titulos: Record<Modo, string> = { ver: 'Proforma', crear: 'Nueva proforma', editar: 'Editar proforma' };
    navigation.setOptions({ title: titulos[modo] });
  }, [navigation, modo]);

  const cargarProforma = useCallback(async () => {
    if (!idProforma) return;
    try {
      const data = await obtenerProforma(idProforma);
      setCabecera(data.cabecera);
      setDetallesLectura(data.detalles);
      setCorreos(data.cabecera.cliente_email ?? '');
    } catch (err) {
      setError(mensajeError(err, 'No se pudo cargar la proforma.'));
    } finally {
      setCargando(false);
    }
  }, [idProforma]);

  useEffect(() => {
    cargarProforma();
  }, [cargarProforma]);

  useEffect(() => {
    obtenerCatalogosProforma()
      .then((c) => {
        setVendedores(c.vendedores);
        if (!idProforma) setDiasVigencia(String(c.dias_vigencia));
      })
      .catch(() => setVendedores([]));
    // Las series se cargan también al editar: traen el modo de IVA y los decimales de la
    // proforma; solo al crear se elige una.
    obtenerSeriesProforma()
      .then(({ establecimientos }) => {
        setSeries(establecimientos);
        if (idProforma) return;
        const primero = establecimientos[0]?.puntos_emision[0]?.id_punto_emision ?? null;
        if (primero) onSerieChange(primero);
      })
      .catch((err) => setError(mensajeError(err, 'No se pudieron cargar las series de proformas.')));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [idProforma]);

  const serieOpciones = series.flatMap((e) =>
    e.puntos_emision.map((p) => ({
      id: p.id_punto_emision,
      label: `${e.establecimiento}-${p.punto_emision}`,
      idEstablecimiento: e.id_establecimiento,
    }))
  );

  async function onSerieChange(idPunto: number | null) {
    setIdPuntoEmision(idPunto);
    setNumeroPrevio(null);
    if (!idPunto) return;
    try {
      const res = await obtenerSecuencialProforma(idPunto, fechaISO(fechaEmision));
      setNumeroPrevio(res.formateado);
    } catch {
      setNumeroPrevio(null);
    }
  }

  function iniciarEdicion() {
    if (!cabecera) return;
    setCliente({
      id: cabecera.id_cliente,
      nombre: cabecera.cliente_nombre ?? '',
      identificacion: cabecera.cliente_ruc ?? '',
      email: cabecera.cliente_email,
    });
    setFechaEmision(fechaDesdeISO(cabecera.fecha_emision));
    setIdVendedor(cabecera.id_vendedor ?? null);
    setDiasVigencia(String(cabecera.dias_vigencia ?? 15));
    setObservaciones(cabecera.observaciones ?? '');
    setLineas(
      detallesLectura.map((d) => {
        const desc = Number(d.descuento ?? 0);
        return {
          idDetalle: d.id,
          id_producto: d.id_producto,
          descripcion: d.descripcion,
          codigo: d.codigo_principal ?? d.producto_codigo ?? '',
          cantidadTexto: String(Number(d.cantidad)),
          precioTexto: String(Number(d.precio_unitario)),
          descuentoTexto: desc > 0 ? desc.toFixed(2) : '',
          ivaPct: ivaDeLinea(d),
        };
      })
    );
    setError(null);
    setModo('editar');
  }

  function buscarClientes(texto: string) {
    setClienteTexto(texto);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    if (texto.trim().length < 2) {
      setClienteResultados([]);
      return;
    }
    debounceRef.current = setTimeout(async () => {
      try {
        setClienteResultados(await buscarClientesProforma(texto.trim()));
      } catch {
        setClienteResultados([]);
      }
    }, 350);
  }

  function buscarProductos(texto: string) {
    setProductoTexto(texto);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    if (texto.trim().length < 2) {
      setProductoResultados([]);
      return;
    }
    debounceRef.current = setTimeout(async () => {
      try {
        setProductoResultados(await buscarProductosProforma(texto.trim()));
      } catch {
        setProductoResultados([]);
      }
    }, 350);
  }

  function agregarProducto(p: ProductoProforma) {
    const base = Number(p.precio_base);
    const ivaPct =
      p.porcentaje_iva_final != null ? Number(p.porcentaje_iva_final) : base > 0 ? r2((Number(p.pvp ?? base) / base - 1) * 100) : 0;
    setLineas((prev) => [
      ...prev,
      {
        id_producto: p.id,
        descripcion: p.nombre,
        codigo: p.codigo,
        cantidadTexto: '1',
        precioTexto: String(base),
        descuentoTexto: '',
        ivaPct,
      },
    ]);
    setProductoTexto('');
    setProductoResultados([]);
  }

  function actualizarLinea(index: number, cambios: Partial<Linea>) {
    setLineas((prev) => prev.map((l, i) => (i === index ? { ...l, ...cambios } : l)));
  }

  // Configuración de facturación de la serie (la elegida al crear, la de la proforma al editar).
  const idPuntoSerie = modo === 'crear' ? idPuntoEmision : cabecera?.id_punto_emision ?? null;
  const serieActual = series.find((e) => e.puntos_emision.some((p) => p.id_punto_emision === idPuntoSerie));
  const decimales: Decimales = { precio: serieActual?.decimales_precio ?? 2, cantidad: serieActual?.decimales_cantidad ?? 2 };
  const modoIvaSerie = serieActual?.puntos_emision.find((p) => p.id_punto_emision === idPuntoSerie)?.calculo_iva;
  const calculos = calcularTotales(lineas, decimales, modoIvaSerie);
  const totalDescuento = r2(calculos.reduce((a, c) => a + c.descuento, 0));
  const subtotal = r2(calculos.reduce((a, c) => a + c.subtotal, 0));
  const iva = r2(calculos.reduce((a, c) => a + c.iva, 0));

  async function guardar() {
    // Candado síncrono (§8): un doble toque llega antes de que React desactive el botón.
    if (guardandoRef.current) return;
    if (!cliente) {
      Alert.alert('Falta el cliente', 'Selecciona un cliente de la lista.');
      return;
    }
    if (lineas.length === 0) {
      Alert.alert('Sin productos', 'Agrega al menos un producto.');
      return;
    }
    const dias = Math.trunc(aNumero(diasVigencia));
    if (dias < 1 || dias > 3650) {
      Alert.alert('Vigencia inválida', 'Los días de vigencia deben estar entre 1 y 3650.');
      return;
    }
    for (const [i, l] of lineas.entries()) {
      const c = calculos[i];
      if (aNumero(l.cantidadTexto) <= 0) {
        Alert.alert('Cantidad inválida', `La cantidad de ${l.descripcion} debe ser mayor a cero.`);
        return;
      }
      if (aNumero(l.precioTexto) < 0) {
        Alert.alert('Precio inválido', `El precio de ${l.descripcion} no puede ser negativo.`);
        return;
      }
      if (aNumero(l.descuentoTexto) < 0 || aNumero(l.descuentoTexto) > c.bruto) {
        Alert.alert('Descuento inválido', `El descuento de ${l.descripcion} debe estar entre $0.00 y $${c.bruto.toFixed(2)}.`);
        return;
      }
    }

    const input = {
      fecha_emision: fechaISO(fechaEmision),
      id_cliente: cliente.id,
      id_vendedor: idVendedor ?? undefined,
      dias_vigencia: dias,
      observaciones: observaciones.trim() || undefined,
      detalles: lineas.map((l) => ({
        id_detalle: l.idDetalle,
        id_producto: l.id_producto,
        cantidad: aNumero(l.cantidadTexto),
        precio_unitario: aNumero(l.precioTexto),
        descuento: r2(aNumero(l.descuentoTexto)),
      })),
    };

    guardandoRef.current = true;
    let creada = false;
    setGuardando(true);
    setError(null);
    try {
      if (modo === 'editar' && idProforma) {
        await actualizarProforma(idProforma, input);
        setModo('ver');
        setCargando(true);
        await cargarProforma();
        Alert.alert('Proforma actualizada', 'Los cambios se guardaron correctamente.');
      } else {
        const serie = serieOpciones.find((s) => s.id === idPuntoEmision);
        if (!serie) {
          Alert.alert('Falta la serie', 'Selecciona la serie de la proforma.');
          return;
        }
        const res = await crearProforma({
          ...input,
          id_establecimiento: serie.idEstablecimiento,
          id_punto_emision: serie.id,
          token_guardado: tokenGuardado.current,
        });
        // Creada: el botón queda desactivado hasta abrir la proforma (otro toque crearía otra).
        creada = true;
        Alert.alert(
          res.ya_existia ? 'Proforma ya registrada' : 'Proforma guardada',
          res.ya_existia
            ? `La proforma ${res.numero} ya se había guardado en un intento anterior; no se creó otra.`
            : `Se creó la proforma ${res.numero} como borrador.`,
          [{ text: 'OK', onPress: () => navigation.replace('ProformaForm', { id: res.id }) }],
          { cancelable: false }
        );
      }
    } catch (err) {
      setError(mensajeError(err, 'No se pudo guardar la proforma.'));
    } finally {
      if (!creada) {
        guardandoRef.current = false;
        setGuardando(false);
      }
    }
  }

  // ── Acciones sobre una proforma guardada ─────────────────────────────

  // Candado síncrono (§8): duplicar/convertir/enviar crean o envían documentos; un doble
  // toque no debe ejecutarlos dos veces.
  const accionRef = useRef(false);

  async function ejecutar(nombre: string, fn: () => Promise<void>) {
    if (accionRef.current) return;
    accionRef.current = true;
    setAccion(nombre);
    setError(null);
    try {
      await fn();
    } catch (err) {
      setError(mensajeError(err, 'No se pudo completar la acción.'));
    } finally {
      accionRef.current = false;
      setAccion(null);
    }
  }

  function confirmar(titulo: string, mensaje: string, textoBoton: string, fn: () => void, destructivo = false) {
    Alert.alert(titulo, mensaje, [
      { text: 'Cancelar', style: 'cancel' },
      { text: textoBoton, style: destructivo ? 'destructive' : 'default', onPress: fn },
    ]);
  }

  function cambiarEstado(id: number, estado: EstadoProforma) {
    const textos: Partial<Record<EstadoProforma, [string, string, boolean]>> = {
      aprobada: ['Aprobar proforma', '¿Marcar la proforma como aprobada?', false],
      anulada: ['Anular proforma', 'La proforma quedará anulada. ¿Continuar?', true],
      rechazada: ['Rechazar proforma', '¿Marcar la proforma como rechazada por el cliente?', true],
      borrador: ['Reabrir proforma', 'Vuelve a borrador para poder editarla (solo administradores). ¿Continuar?', false],
    };
    const [titulo, mensaje, destructivo] = textos[estado] ?? ['Cambiar estado', '¿Continuar?', false];
    confirmar(titulo, mensaje, 'Sí, continuar', () =>
      ejecutar('estado', async () => {
        await cambiarEstadoProforma(id, estado);
        await cargarProforma();
      }),
      destructivo
    );
  }

  async function compartirPdf(id: number) {
    await ejecutar('pdf', async () => {
      const uri = await descargarPdfProforma(id);
      if (await Sharing.isAvailableAsync()) {
        await Sharing.shareAsync(uri, { mimeType: 'application/pdf', dialogTitle: 'Proforma' });
      } else {
        Alert.alert('PDF descargado', 'El PDF se guardó en el dispositivo, pero no se pudo abrir el diálogo para compartirlo.');
      }
    });
  }

  async function enviarCorreo(id: number) {
    if (!correos.trim()) {
      Alert.alert('Falta el correo', 'Escribe al menos un correo (puedes separar varios con coma).');
      return;
    }
    await ejecutar('correo', async () => {
      const res = await enviarCorreoProforma(id, correos.trim(), adjuntarFicha);
      setMostrarCorreo(false);
      await cargarProforma();
      Alert.alert('Correo enviado', res.mensaje);
    });
  }

  function duplicar(id: number) {
    confirmar('Duplicar proforma', 'Se creará una proforma nueva en borrador con los mismos datos, número nuevo y fecha de hoy.', 'Duplicar', () =>
      ejecutar('duplicar', async () => {
        const res = await duplicarProforma(id);
        Alert.alert('Proforma duplicada', `Se creó la proforma ${res.numero}.`, [
          { text: 'Abrir', onPress: () => navigation.replace('ProformaForm', { id: res.id }) },
          { text: 'Quedarme aquí', style: 'cancel' },
        ]);
      })
    );
  }

  async function aFactura(id: number, forzar = false) {
    await ejecutar('factura', async () => {
      const res = await convertirProformaAFactura(id, forzar);
      if (res.requiere_confirmacion) {
        confirmar('Ya tiene factura', res.mensaje || 'Esta proforma ya tiene una factura asociada. ¿Crear otra?', 'Crear otra', () => aFactura(id, true));
        return;
      }
      if (res.stock_insuficiente) {
        const lista = res.faltantes.map((f) => `• ${f.producto}: hay ${f.disponible}, se necesitan ${f.requerido}`).join('\n');
        Alert.alert('Stock insuficiente', `No hay saldo suficiente para facturar:\n\n${lista}`);
        return;
      }
      await cargarProforma();
      Alert.alert('Factura creada', 'Se creó la factura en borrador a partir de la proforma.', [
        { text: 'Ver factura', onPress: () => navigation.navigate('FacturaVentaForm', { id: res.id_factura }) },
        { text: 'Cerrar', style: 'cancel' },
      ]);
    });
  }

  async function aPedido(id: number, forzar = false) {
    await ejecutar('pedido', async () => {
      const res = await convertirProformaAPedido(id, forzar);
      if (res.items_sin_producto.length > 0) {
        Alert.alert(
          'No se puede enviar a pedidos',
          `El pedido solo admite productos del catálogo. Estas líneas son texto libre:\n\n${res.items_sin_producto.map((t) => `• ${t}`).join('\n')}`
        );
        return;
      }
      if (res.requiere_confirmacion) {
        confirmar('Ya tiene pedido', res.mensaje || 'Esta proforma ya tiene un pedido asociado. ¿Crear otro?', 'Crear otro', () => aPedido(id, true));
        return;
      }
      Alert.alert('Pedido creado', `Se creó el pedido ${res.numero}.`, [
        { text: 'Ver pedido', onPress: () => navigation.navigate('PedidoForm', { id: res.id_pedido }) },
        { text: 'Cerrar', style: 'cancel' },
      ]);
    });
  }

  // ── Render ───────────────────────────────────────────────────────────

  if (cargando) {
    return (
      <View style={styles.centrado}>
        <ActivityIndicator size="large" color="#0d6efd" />
      </View>
    );
  }

  if (modo === 'ver') {
    if (!cabecera) {
      return (
        <View style={styles.centrado}>
          <Text style={styles.error}>{error ?? 'No se pudo cargar la proforma.'}</Text>
        </View>
      );
    }
    const id = cabecera.id;
    const estado = ESTADO_PROFORMA[cabecera.estado] ?? { label: cabecera.estado, color: '#6c757d' };
    const emision = fechaDesdeISO(cabecera.fecha_emision);
    const validaHasta = new Date(emision);
    validaHasta.setDate(validaHasta.getDate() + Number(cabecera.dias_vigencia || 0));
    const ivaTotal = detallesLectura.reduce((a, d) => a + d.impuestos.reduce((s, i) => s + Number(i.valor), 0), 0);
    const ocupado = accion !== null;

    const boton = (texto: string, onPress: () => void, color: string, nombre: string) => (
      <TouchableOpacity key={texto} style={[styles.botonAccion, { backgroundColor: color }]} onPress={onPress} disabled={ocupado}>
        {accion === nombre ? <ActivityIndicator color="#fff" /> : <Text style={styles.botonTexto}>{texto}</Text>}
      </TouchableOpacity>
    );

    return (
      <KeyboardAwareScrollView style={styles.container} contentContainerStyle={{ padding: 16 }} keyboardShouldPersistTaps="handled">
        {error ? <Text style={styles.error}>{error}</Text> : null}

        <View style={styles.bloque}>
          <View style={styles.filaEntre}>
            <Text style={styles.numero}>
              {cabecera.establecimiento}-{cabecera.punto_emision}-{cabecera.secuencial}
            </Text>
            <View style={[styles.badge, { backgroundColor: estado.color + '22', borderColor: estado.color }]}>
              <Text style={[styles.badgeTexto, { color: estado.color }]}>{estado.label}</Text>
            </View>
          </View>
          <Text style={styles.clienteNombre}>{cabecera.cliente_nombre}</Text>
          <Text style={styles.datoSub}>{cabecera.cliente_ruc}</Text>
          <Text style={styles.dato}>Fecha: {fechaVista(emision)}</Text>
          <Text style={styles.dato}>
            Válida hasta: {fechaVista(validaHasta)} ({cabecera.dias_vigencia} días)
          </Text>
          {cabecera.vendedor_nombre ? <Text style={styles.dato}>Vendedor: {cabecera.vendedor_nombre}</Text> : null}
          {cabecera.observaciones ? <Text style={styles.dato}>Obs.: {cabecera.observaciones}</Text> : null}
          {cabecera.estado_correo === 'enviado' ? <Text style={styles.datoOk}>Correo enviado al cliente</Text> : null}
          {cabecera.aprobacion_cliente_fecha ? (
            <Text style={styles.datoOk}>
              Aprobada por el cliente desde el correo
              {cabecera.aprobacion_cliente_comentario ? `: "${cabecera.aprobacion_cliente_comentario}"` : ''}
            </Text>
          ) : null}
        </View>

        <Text style={styles.tituloSeccion}>Productos</Text>
        {detallesLectura.map((d) => (
          <View key={d.id} style={styles.linea}>
            <Text style={styles.lineaNombre}>{d.descripcion}</Text>
            <Text style={styles.lineaSub}>
              {Number(d.cantidad)} x ${Number(d.precio_unitario).toFixed(2)}
              {Number(d.descuento ?? 0) > 0 ? ` - desc. $${Number(d.descuento).toFixed(2)}` : ''} = $
              {Number(d.precio_total_sin_impuesto).toFixed(2)}
            </Text>
          </View>
        ))}

        <View style={styles.totalesBox}>
          {Number(cabecera.total_descuento) > 0 ? (
            <View style={styles.totalFila}>
              <Text style={styles.totalLabel}>Descuento</Text>
              <Text style={[styles.totalValor, styles.textoDescuento]}>-${Number(cabecera.total_descuento).toFixed(2)}</Text>
            </View>
          ) : null}
          <View style={styles.totalFila}>
            <Text style={styles.totalLabel}>Subtotal</Text>
            <Text style={styles.totalValor}>${Number(cabecera.total_sin_impuestos).toFixed(2)}</Text>
          </View>
          <View style={styles.totalFila}>
            <Text style={styles.totalLabel}>IVA</Text>
            <Text style={styles.totalValor}>${ivaTotal.toFixed(2)}</Text>
          </View>
          <View style={styles.totalFila}>
            <Text style={styles.totalLabel}>Total</Text>
            <Text style={[styles.totalValor, styles.totalFinal]}>${Number(cabecera.importe_total).toFixed(2)}</Text>
          </View>
        </View>

        <View style={styles.acciones}>
          {cabecera.estado === 'borrador' ? boton('Editar', iniciarEdicion, '#0d6efd', 'editar') : null}
          {cabecera.estado === 'borrador' ? boton('Aprobar', () => cambiarEstado(id, 'aprobada'), '#198754', 'estado') : null}
          {['aprobada', 'convertida'].includes(cabecera.estado) ? boton('Convertir a factura', () => aFactura(id), '#198754', 'factura') : null}
          {['borrador', 'aprobada', 'convertida'].includes(cabecera.estado) ? boton('Enviar a pedidos', () => aPedido(id), '#6f42c1', 'pedido') : null}
          {boton('Compartir PDF', () => compartirPdf(id), '#20c997', 'pdf')}
          {boton(mostrarCorreo ? 'Ocultar correo' : 'Enviar por correo', () => setMostrarCorreo((v) => !v), '#0dcaf0', 'correo-toggle')}
          {boton('Duplicar', () => duplicar(id), '#6c757d', 'duplicar')}
          {cabecera.estado === 'aprobada' ? boton('Reabrir (borrador)', () => cambiarEstado(id, 'borrador'), '#fd7e14', 'estado') : null}
          {cabecera.estado === 'aprobada' ? boton('Rechazada por el cliente', () => cambiarEstado(id, 'rechazada'), '#fd7e14', 'estado') : null}
          {['borrador', 'aprobada'].includes(cabecera.estado) ? boton('Anular', () => cambiarEstado(id, 'anulada'), '#dc3545', 'estado') : null}
        </View>

        {mostrarCorreo ? (
          <View style={styles.bloque}>
            <Text style={styles.label}>Correos del destinatario</Text>
            <TextInput
              style={styles.input}
              value={correos}
              onChangeText={setCorreos}
              placeholder="cliente@correo.com, otro@correo.com"
              autoCapitalize="none"
              keyboardType="email-address"
            />
            <View style={[styles.filaEntre, { marginTop: 12 }]}>
              <Text style={styles.dato}>Adjuntar ficha de productos</Text>
              <Switch value={adjuntarFicha} onValueChange={setAdjuntarFicha} />
            </View>
            {cabecera.estado === 'borrador' ? (
              <Text style={styles.ayuda}>El correo incluye un botón para que el cliente apruebe la proforma.</Text>
            ) : null}
            <TouchableOpacity style={[styles.botonAccion, { backgroundColor: '#0d6efd', marginTop: 12 }]} onPress={() => enviarCorreo(id)} disabled={ocupado}>
              {accion === 'correo' ? <ActivityIndicator color="#fff" /> : <Text style={styles.botonTexto}>Enviar</Text>}
            </TouchableOpacity>
          </View>
        ) : null}
      </KeyboardAwareScrollView>
    );
  }

  // ── Crear / editar ───────────────────────────────────────────────────

  return (
    <KeyboardAwareScrollView style={styles.container} contentContainerStyle={{ padding: 16 }} keyboardShouldPersistTaps="handled">
      {error ? <Text style={styles.error}>{error}</Text> : null}

      {modo === 'crear' ? (
        <>
          {serieOpciones.length === 0 ? (
            <Text style={styles.error}>No hay series con secuencial de Proformas configurado.</Text>
          ) : (
            <SelectorLista<number> label="Serie" value={idPuntoEmision} opciones={serieOpciones} onChange={onSerieChange} />
          )}
          {numeroPrevio ? <Text style={styles.ayuda}>Número estimado: {numeroPrevio} (se confirma al guardar)</Text> : null}
        </>
      ) : cabecera ? (
        <Text style={styles.numero}>
          {cabecera.establecimiento}-{cabecera.punto_emision}-{cabecera.secuencial}
        </Text>
      ) : null}

      <View style={{ marginTop: 12 }}>
        <SelectorFechaHora label="Fecha de emisión" mode="date" value={fechaEmision} onChange={(d) => d && setFechaEmision(d)} permiteQuitar={false} />
      </View>

      <Text style={styles.label}>Cliente</Text>
      {cliente ? (
        <View style={styles.seleccionado}>
          <View style={{ flex: 1 }}>
            <Text style={styles.lineaNombre}>{cliente.nombre}</Text>
            <Text style={styles.datoSub}>{cliente.identificacion}</Text>
          </View>
          <TouchableOpacity onPress={() => setCliente(null)}>
            <Text style={styles.quitar}>Cambiar</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <>
          <TextInput style={styles.input} value={clienteTexto} onChangeText={buscarClientes} placeholder="Buscar por nombre o identificación..." />
          {clienteResultados.map((c) => (
            <TouchableOpacity
              key={c.id}
              style={styles.resultado}
              onPress={() => {
                setCliente(c);
                setClienteTexto('');
                setClienteResultados([]);
              }}
            >
              <Text style={styles.lineaNombre}>{c.nombre}</Text>
              <Text style={styles.datoSub}>{c.identificacion}</Text>
            </TouchableOpacity>
          ))}
        </>
      )}

      <View style={{ marginTop: 12 }}>
        <SelectorLista<number>
          label="Vendedor"
          value={idVendedor}
          opciones={vendedores.map((v) => ({ id: v.id, label: v.nombre }))}
          onChange={setIdVendedor}
          placeholder="Opcional"
        />
      </View>

      <Text style={styles.label}>Días de vigencia</Text>
      <TextInput style={styles.input} value={diasVigencia} onChangeText={setDiasVigencia} keyboardType="number-pad" />

      <Text style={styles.label}>Observaciones</Text>
      <TextInput style={styles.input} value={observaciones} onChangeText={setObservaciones} placeholder="Opcional" multiline />

      <Text style={[styles.label, { marginTop: 20 }]}>Agregar producto</Text>
      <TextInput style={styles.input} value={productoTexto} onChangeText={buscarProductos} placeholder="Buscar por código o nombre..." />
      {productoResultados.map((p) => (
        <TouchableOpacity key={p.id} style={styles.resultado} onPress={() => agregarProducto(p)}>
          <Text style={styles.lineaNombre}>{p.nombre}</Text>
          <Text style={styles.datoSub}>
            {p.codigo} · ${Number(p.precio_base).toFixed(2)}
          </Text>
        </TouchableOpacity>
      ))}

      <Text style={[styles.tituloSeccion, { marginTop: 16 }]}>Detalle de la proforma</Text>
      {lineas.length === 0 ? (
        <Text style={styles.vacio}>Aún no agregas productos.</Text>
      ) : (
        lineas.map((l, i) => {
          const c = calculos[i];
          return (
            <View key={i} style={styles.linea}>
              <View style={styles.filaEntre}>
                <Text style={[styles.lineaNombre, { flex: 1 }]}>{l.descripcion}</Text>
                <TouchableOpacity onPress={() => setLineas((prev) => prev.filter((_, j) => j !== i))}>
                  <Text style={styles.quitar}>Quitar</Text>
                </TouchableOpacity>
              </View>
              <View style={styles.campos}>
                <View style={styles.campo}>
                  <Text style={styles.campoLabel}>Cantidad</Text>
                  <TextInput style={styles.inputLinea} keyboardType="decimal-pad" value={l.cantidadTexto} onChangeText={(v) => actualizarLinea(i, { cantidadTexto: v })} />
                </View>
                <View style={styles.campo}>
                  <Text style={styles.campoLabel}>Precio (sin IVA)</Text>
                  <TextInput style={styles.inputLinea} keyboardType="decimal-pad" value={l.precioTexto} onChangeText={(v) => actualizarLinea(i, { precioTexto: v })} />
                </View>
                <View style={styles.campo}>
                  <Text style={styles.campoLabel}>Descuento $</Text>
                  <TextInput
                    style={[styles.inputLinea, styles.textoDescuento]}
                    keyboardType="decimal-pad"
                    value={l.descuentoTexto}
                    placeholder="0.00"
                    onChangeText={(v) => actualizarLinea(i, { descuentoTexto: v })}
                  />
                </View>
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
            <Text style={[styles.totalValor, styles.totalFinal]}>${(subtotal + iva).toFixed(2)}</Text>
          </View>
        </View>
      ) : null}

      {modo === 'editar' ? (
        <TouchableOpacity style={styles.botonCancelar} onPress={() => setModo('ver')} disabled={guardando}>
          <Text style={styles.botonCancelarTexto}>Cancelar edición</Text>
        </TouchableOpacity>
      ) : null}

      <TouchableOpacity style={[styles.botonAccion, { backgroundColor: '#0d6efd', marginTop: 16 }]} onPress={guardar} disabled={guardando}>
        {guardando ? <ActivityIndicator color="#fff" /> : <Text style={styles.botonTexto}>{modo === 'editar' ? 'Guardar cambios' : 'Guardar proforma'}</Text>}
      </TouchableOpacity>
    </KeyboardAwareScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f6f8' },
  centrado: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 24 },
  error: { color: '#dc3545', marginBottom: 12, textAlign: 'center' },
  bloque: { backgroundColor: '#fff', borderRadius: 10, padding: 16, marginBottom: 12 },
  filaEntre: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8 },
  numero: { fontSize: 13, color: '#888', fontWeight: '600' },
  clienteNombre: { fontSize: 17, fontWeight: '700', marginTop: 6 },
  dato: { fontSize: 14, color: '#444', marginTop: 2 },
  datoSub: { fontSize: 12, color: '#777' },
  datoOk: { fontSize: 13, color: '#198754', marginTop: 4 },
  ayuda: { fontSize: 12, color: '#777', marginTop: 6 },
  badge: { borderWidth: 1, borderRadius: 20, paddingHorizontal: 10, paddingVertical: 4 },
  badgeTexto: { fontSize: 11, fontWeight: '700' },
  tituloSeccion: { fontSize: 15, fontWeight: '700', marginTop: 8, marginBottom: 8 },
  linea: { backgroundColor: '#fff', borderRadius: 8, padding: 12, marginBottom: 8 },
  lineaNombre: { fontSize: 14, fontWeight: '600' },
  lineaSub: { fontSize: 13, color: '#666', marginTop: 2 },
  campos: { flexDirection: 'row', gap: 8, marginTop: 8, marginBottom: 6 },
  campo: { flex: 1 },
  campoLabel: { fontSize: 11, color: '#777', marginBottom: 2 },
  inputLinea: { borderWidth: 1, borderColor: '#ccc', borderRadius: 6, paddingHorizontal: 8, paddingVertical: 6, fontSize: 13, textAlign: 'right', backgroundColor: '#fff' },
  textoDescuento: { color: '#dc3545' },
  label: { fontSize: 13, color: '#333', marginTop: 12, marginBottom: 4, fontWeight: '600' },
  input: { borderWidth: 1, borderColor: '#ccc', borderRadius: 8, paddingHorizontal: 12, paddingVertical: 10, fontSize: 15, backgroundColor: '#fff' },
  seleccionado: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#e7f1ff', borderRadius: 8, padding: 12 },
  resultado: { backgroundColor: '#fff', padding: 10, borderBottomWidth: 1, borderBottomColor: '#eee' },
  quitar: { color: '#dc3545', fontWeight: '600' },
  vacio: { color: '#888' },
  totalesBox: { backgroundColor: '#fff', borderRadius: 8, padding: 12, marginTop: 8 },
  totalFila: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 3 },
  totalLabel: { fontSize: 13, color: '#666' },
  totalValor: { fontSize: 13, fontWeight: '600' },
  totalFinal: { fontSize: 16, color: '#0d6efd', fontWeight: '700' },
  acciones: { marginTop: 16, gap: 10 },
  botonAccion: { borderRadius: 8, paddingVertical: 14, alignItems: 'center' },
  botonTexto: { color: '#fff', fontSize: 15, fontWeight: '600' },
  botonCancelar: { borderRadius: 8, paddingVertical: 12, marginTop: 20, alignItems: 'center', borderWidth: 1, borderColor: '#ccc' },
  botonCancelarTexto: { color: '#666', fontWeight: '600' },
});
