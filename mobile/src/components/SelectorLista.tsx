import React, { useState } from 'react';
import { FlatList, Modal, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

export type OpcionSelector = { id: number; label: string; sublabel?: string };

type Props = {
  label: string;
  value: number | null;
  opciones: OpcionSelector[];
  onChange: (id: number | null) => void;
  placeholder?: string;
};

/** Selector de una lista de opciones vía modal (a diferencia de un <select> web, en
 * RN no hay un control nativo equivalente para listas cortas de datos del servidor). */
export default function SelectorLista({ label, value, opciones, onChange, placeholder = 'Seleccionar...' }: Props) {
  const [abierto, setAbierto] = useState(false);
  const seleccionado = opciones.find((o) => o.id === value) ?? null;

  return (
    <View>
      <Text style={styles.label}>{label}</Text>
      <TouchableOpacity style={styles.input} onPress={() => setAbierto(true)}>
        <Text style={seleccionado ? styles.valor : styles.placeholder}>
          {seleccionado ? seleccionado.label : placeholder}
        </Text>
        <Ionicons name="chevron-down" size={18} color="#0d6efd" />
      </TouchableOpacity>
      {seleccionado ? (
        <TouchableOpacity onPress={() => onChange(null)}>
          <Text style={styles.quitar}>Quitar</Text>
        </TouchableOpacity>
      ) : null}

      <Modal visible={abierto} animationType="slide" transparent onRequestClose={() => setAbierto(false)}>
        <View style={styles.overlay}>
          <View style={styles.hoja}>
            <Text style={styles.hojaTitulo}>{label}</Text>
            <FlatList
              data={opciones}
              keyExtractor={(item) => String(item.id)}
              ListEmptyComponent={<Text style={styles.vacio}>No hay opciones disponibles.</Text>}
              renderItem={({ item }) => (
                <TouchableOpacity
                  style={styles.opcion}
                  onPress={() => {
                    onChange(item.id);
                    setAbierto(false);
                  }}
                >
                  <Text style={styles.opcionTexto}>{item.label}</Text>
                  {item.sublabel ? <Text style={styles.opcionSub}>{item.sublabel}</Text> : null}
                </TouchableOpacity>
              )}
            />
            <TouchableOpacity style={styles.cerrar} onPress={() => setAbierto(false)}>
              <Text style={styles.cerrarTexto}>Cancelar</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  label: { fontSize: 13, color: '#333', marginBottom: 4, fontWeight: '600' },
  input: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    backgroundColor: '#fff',
  },
  valor: { fontSize: 15, color: '#000' },
  placeholder: { fontSize: 15, color: '#999' },
  quitar: { color: '#dc3545', fontSize: 12, marginTop: 2 },
  overlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.4)', justifyContent: 'flex-end' },
  hoja: { backgroundColor: '#fff', borderTopLeftRadius: 16, borderTopRightRadius: 16, maxHeight: '70%', padding: 16 },
  hojaTitulo: { fontSize: 16, fontWeight: '700', marginBottom: 8 },
  vacio: { color: '#888', textAlign: 'center', paddingVertical: 20 },
  opcion: { paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: '#eee' },
  opcionTexto: { fontSize: 15, fontWeight: '600' },
  opcionSub: { fontSize: 12, color: '#777', marginTop: 2 },
  cerrar: { alignItems: 'center', paddingVertical: 14, marginTop: 4 },
  cerrarTexto: { color: '#666', fontWeight: '600' },
});
