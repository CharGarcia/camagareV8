import React, { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Alert, Image, StyleSheet, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { KeyboardAwareScrollView } from 'react-native-keyboard-aware-scroll-view';
import * as ImagePicker from 'expo-image-picker';
import { useNavigation, useRoute } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RouteProp } from '@react-navigation/native';
import type { RootStackParamList } from '../navigation/RootNavigator';
import {
  actualizarProducto,
  Categoria,
  crearProducto,
  Marca,
  obtenerCatalogosProductos,
  obtenerProducto,
  obtenerSiguienteCodigo,
  subirImagenProducto,
  TarifaIva,
  UnidadMedida,
  urlImagenProducto,
} from '../api/productos';
import { mensajeError } from '../api/client';
import SelectorLista from '../components/SelectorLista';

const TIPOS_PRODUCCION: { id: '01' | '02'; label: string }[] = [
  { id: '01', label: 'Bien' },
  { id: '02', label: 'Servicio' },
];

export default function ProductoFormScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const route = useRoute<RouteProp<RootStackParamList, 'ProductoForm'>>();
  const idProducto = route.params?.id;
  const editando = !!idProducto;

  const [cargando, setCargando] = useState(editando);
  const [guardando, setGuardando] = useState(false);
  const [subiendoImagen, setSubiendoImagen] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [categorias, setCategorias] = useState<Categoria[]>([]);
  const [marcas, setMarcas] = useState<Marca[]>([]);
  const [unidades, setUnidades] = useState<UnidadMedida[]>([]);
  const [tarifasIva, setTarifasIva] = useState<TarifaIva[]>([]);

  const [codigo, setCodigo] = useState('');
  const codigoTocado = useRef(false);
  const [codigoAuxiliar, setCodigoAuxiliar] = useState('');
  const [nombre, setNombre] = useState('');
  const [tipoProduccion, setTipoProduccion] = useState<'01' | '02'>('01');
  const [tarifaIva, setTarifaIva] = useState<number | null>(null);
  const [precioBase, setPrecioBase] = useState('');
  const [pvp, setPvp] = useState('');
  // true mientras el usuario escribe en el campo PVP: evita que el efecto que
  // recalcula PVP desde la base le pise lo que está tecleando (el PVP manda en
  // ese momento; la base es la que se recalcula, igual que en la web).
  const editandoPvp = useRef(false);
  const [idCategoria, setIdCategoria] = useState<number | null>(null);
  const [idMarca, setIdMarca] = useState<number | null>(null);
  const [idMedida, setIdMedida] = useState<number | null>(null);
  const [imagenPath, setImagenPath] = useState<string | null>(null);
  const [stockActual, setStockActual] = useState<number | null>(null);

  useEffect(() => {
    navigation.setOptions({ title: editando ? 'Editar producto' : 'Nuevo producto' });
  }, [navigation, editando]);

  useEffect(() => {
    obtenerCatalogosProductos()
      .then((data) => {
        setCategorias(data.categorias);
        setMarcas(data.marcas);
        setUnidades(data.unidades);
        setTarifasIva(data.tarifas_iva);
        // Solo al crear: preseleccionar "Unidad" (la misma que ProductoService
        // asignaría por defecto si no se elige nada). Al editar, la unidad ya
        // guardada del producto manda.
        if (!editando && data.medida_default) {
          setIdMedida(data.medida_default.id_medida);
        }
      })
      .catch(() => {
        setCategorias([]);
        setMarcas([]);
        setUnidades([]);
        setTarifasIva([]);
      });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    (async () => {
      if (editando && idProducto) {
        try {
          const p = await obtenerProducto(idProducto);
          setCodigo(p.codigo);
          codigoTocado.current = true;
          setCodigoAuxiliar(p.codigo_auxiliar ?? '');
          setNombre(p.nombre);
          setTipoProduccion(p.tipo_produccion === '02' ? '02' : '01');
          setTarifaIva(p.tarifa_iva);
          setPrecioBase(String(Number(p.precio_base)));
          setIdCategoria(p.id_categoria);
          setIdMarca(p.id_marca);
          setIdMedida(p.id_medida);
          setImagenPath(p.imagen);
          setStockActual(p.stock_actual_general);
        } catch (err) {
          setError(mensajeError(err, 'No se pudo cargar el producto.'));
        } finally {
          setCargando(false);
        }
      } else {
        try {
          const res = await obtenerSiguienteCodigo('01');
          setCodigo(res.codigo);
        } catch {
          // Si falla, el usuario puede escribir el código manualmente.
        }
      }
    })();
  }, [editando, idProducto]);

  // Al crear (no al editar): cambiar bien/servicio recalcula el correlativo sugerido
  // (prefijo P o S), salvo que el usuario ya haya escrito un código a mano.
  async function onTipoProduccionChange(nuevoTipo: '01' | '02') {
    setTipoProduccion(nuevoTipo);
    if (editando || codigoTocado.current) return;
    try {
      const res = await obtenerSiguienteCodigo(nuevoTipo);
      setCodigo(res.codigo);
    } catch {
      // Se deja el código actual si falla.
    }
  }

  function pctDeTarifa(id: number | null): number {
    return tarifasIva.find((t) => t.id === id)?.porcentaje ?? 0;
  }

  // Precio base → PVP en vivo (mismo cálculo que la web: PVP = base * (1 + %IVA/100),
  // sin ICE porque el formulario móvil no lo maneja).
  useEffect(() => {
    if (editandoPvp.current) return;
    const base = parseFloat(precioBase.replace(',', '.')) || 0;
    const pct = pctDeTarifa(tarifaIva);
    setPvp((base * (1 + pct / 100)).toFixed(2));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [precioBase, tarifaIva, tarifasIva]);

  function onChangePrecioBase(texto: string) {
    editandoPvp.current = false;
    setPrecioBase(texto);
  }

  // PVP → precio base en vivo (inverso): base = PVP / (1 + %IVA/100).
  function onChangePvp(texto: string) {
    editandoPvp.current = true;
    setPvp(texto);
    const pvpNum = parseFloat(texto.replace(',', '.')) || 0;
    const factor = 1 + pctDeTarifa(tarifaIva) / 100;
    setPrecioBase((factor > 0 ? pvpNum / factor : 0).toFixed(4));
  }

  async function elegirImagen(desdeCamera: boolean) {
    const permiso = desdeCamera
      ? await ImagePicker.requestCameraPermissionsAsync()
      : await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (permiso.status !== 'granted') {
      Alert.alert('Permiso necesario', desdeCamera ? 'Necesitamos acceso a la cámara.' : 'Necesitamos acceso a tus fotos.');
      return;
    }

    const opciones: ImagePicker.ImagePickerOptions = {
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      base64: true,
      quality: 0.5,
      allowsEditing: true,
      aspect: [1, 1],
    };
    const resultado = desdeCamera ? await ImagePicker.launchCameraAsync(opciones) : await ImagePicker.launchImageLibraryAsync(opciones);
    if (resultado.canceled || !resultado.assets?.[0]?.base64) return;

    const asset = resultado.assets[0];
    const mime = asset.mimeType ?? 'image/jpeg';
    setSubiendoImagen(true);
    setError(null);
    try {
      const { path } = await subirImagenProducto(`data:${mime};base64,${asset.base64}`);
      setImagenPath(path);
    } catch (err) {
      setError(mensajeError(err, 'No se pudo subir la imagen.'));
    } finally {
      setSubiendoImagen(false);
    }
  }

  function confirmarOrigenImagen() {
    Alert.alert('Foto del producto', undefined, [
      { text: 'Tomar foto', onPress: () => elegirImagen(true) },
      { text: 'Elegir de la galería', onPress: () => elegirImagen(false) },
      { text: 'Cancelar', style: 'cancel' },
    ]);
  }

  async function guardar() {
    if (nombre.trim() === '') {
      Alert.alert('Datos incompletos', 'El nombre es obligatorio.');
      return;
    }
    const precio = precioBase.trim() === '' ? 0 : Number(precioBase.replace(',', '.'));
    if (Number.isNaN(precio) || precio < 0) {
      Alert.alert('Precio inválido', 'El precio base debe ser un número mayor o igual a cero.');
      return;
    }

    setGuardando(true);
    setError(null);
    const input = {
      codigo: codigo.trim() || undefined,
      codigo_auxiliar: codigoAuxiliar.trim() || undefined,
      nombre: nombre.trim(),
      tipo_produccion: tipoProduccion,
      tarifa_iva: tarifaIva ?? undefined,
      precio_base: precio,
      id_categoria: idCategoria ?? undefined,
      id_marca: idMarca ?? undefined,
      id_medida: idMedida ?? undefined,
      imagen: imagenPath ?? undefined,
    };
    try {
      if (editando && idProducto) {
        await actualizarProducto(idProducto, input);
        Alert.alert('Producto actualizado', 'Los cambios se guardaron correctamente.', [
          { text: 'OK', onPress: () => navigation.goBack() },
        ]);
      } else {
        await crearProducto(input);
        Alert.alert('Producto creado', 'El producto se registró correctamente.', [
          { text: 'OK', onPress: () => navigation.goBack() },
        ]);
      }
    } catch (err) {
      setError(mensajeError(err, 'No se pudo guardar el producto.'));
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

  const urlImg = urlImagenProducto(imagenPath);

  return (
    <KeyboardAwareScrollView
      style={styles.container}
      contentContainerStyle={{ padding: 16, paddingBottom: 40 }}
      enableOnAndroid
      extraScrollHeight={20}
    >
      {error ? <Text style={styles.error}>{error}</Text> : null}

      <TouchableOpacity style={styles.fotoBox} onPress={confirmarOrigenImagen} disabled={subiendoImagen}>
        {subiendoImagen ? (
          <ActivityIndicator color="#0d6efd" />
        ) : urlImg ? (
          <Image source={{ uri: urlImg }} style={styles.foto} />
        ) : (
          <Text style={styles.fotoTexto}>Toca para agregar foto</Text>
        )}
      </TouchableOpacity>

      {editando && tipoProduccion === '01' && stockActual !== null ? (
        <View style={styles.stockBox}>
          <Text style={styles.stockTexto}>Stock actual: {stockActual}</Text>
        </View>
      ) : null}

      <SelectorLista<'01' | '02'>
        label="Tipo"
        value={tipoProduccion}
        opciones={TIPOS_PRODUCCION}
        onChange={(id) => onTipoProduccionChange(id ?? '01')}
      />

      <View style={styles.campo}>
        <Text style={styles.label}>Código</Text>
        <TextInput
          style={styles.input}
          value={codigo}
          onChangeText={(t) => {
            codigoTocado.current = true;
            setCodigo(t);
          }}
          placeholder="Se autogenera si lo dejas vacío"
        />
      </View>

      <View style={styles.campo}>
        <Text style={styles.label}>Código auxiliar</Text>
        <TextInput style={styles.input} value={codigoAuxiliar} onChangeText={setCodigoAuxiliar} placeholder="Opcional" />
      </View>

      <View style={styles.campo}>
        <Text style={styles.label}>Nombre</Text>
        <TextInput style={styles.input} value={nombre} onChangeText={setNombre} placeholder="Nombre del producto" />
      </View>

      <View style={{ marginTop: 12 }}>
        <SelectorLista<number>
          label="Tarifa IVA"
          value={tarifaIva}
          opciones={tarifasIva.map((t) => ({ id: t.id, label: `${t.nombre} (${t.porcentaje}%)` }))}
          onChange={(id) => {
            editandoPvp.current = false;
            setTarifaIva(id);
          }}
          placeholder="Tarifa por defecto del sistema"
        />
      </View>

      <View style={styles.filaPrecios}>
        <View style={[styles.campo, { flex: 1 }]}>
          <Text style={styles.label}>Precio base</Text>
          <TextInput style={styles.input} value={precioBase} onChangeText={onChangePrecioBase} keyboardType="decimal-pad" placeholder="0.00" />
        </View>
        <View style={[styles.campo, { flex: 1 }]}>
          <Text style={styles.label}>Precio con IVA</Text>
          <TextInput style={styles.input} value={pvp} onChangeText={onChangePvp} keyboardType="decimal-pad" placeholder="0.00" />
        </View>
      </View>

      <View style={{ marginTop: 12 }}>
        <SelectorLista<number>
          label="Categoría"
          value={idCategoria}
          opciones={categorias.map((c) => ({ id: c.id, label: c.nombre }))}
          onChange={setIdCategoria}
          placeholder="Opcional"
        />
      </View>

      <View style={{ marginTop: 12 }}>
        <SelectorLista<number>
          label="Marca"
          value={idMarca}
          opciones={marcas.map((m) => ({ id: m.id, label: m.nombre }))}
          onChange={setIdMarca}
          placeholder="Opcional"
        />
      </View>

      <View style={{ marginTop: 12 }}>
        <SelectorLista<number>
          label="Unidad de medida"
          value={idMedida}
          opciones={unidades.map((u) => ({ id: u.id, label: u.nombre, sublabel: u.abreviatura }))}
          onChange={setIdMedida}
          placeholder="Se asigna una por defecto si no eliges"
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
  fotoBox: {
    width: 120,
    height: 120,
    borderRadius: 12,
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#ccc',
    alignSelf: 'center',
    justifyContent: 'center',
    alignItems: 'center',
    overflow: 'hidden',
  },
  foto: { width: '100%', height: '100%' },
  fotoTexto: { fontSize: 12, color: '#888', textAlign: 'center', paddingHorizontal: 8 },
  stockBox: { alignItems: 'center', marginTop: 10 },
  stockTexto: { fontSize: 13, color: '#555', fontWeight: '600' },
  campo: { marginTop: 16 },
  filaPrecios: { flexDirection: 'row', gap: 12 },
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
  botonGuardar: { backgroundColor: '#0d6efd', borderRadius: 8, paddingVertical: 14, marginTop: 24, alignItems: 'center' },
  botonGuardarTexto: { color: '#fff', fontSize: 16, fontWeight: '600' },
});
