import React, { useState } from 'react';
import { Alert, Modal, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import DateTimePicker from '@react-native-community/datetimepicker';
import { Ionicons } from '@expo/vector-icons';

/** Compara solo horas:minutos (mismo día base en los "borradores" del spinner). */
function horaEnMinutos(d: Date): number {
  return d.getHours() * 60 + d.getMinutes();
}

type Props = {
  label: string;
  horaInicial: Date | null;
  horaMaxima: Date | null;
  onChange: (horaInicial: Date | null, horaMaxima: Date | null) => void;
  /** false (default): permite dejarlo vacío con "Quitar horario". true: obligatorio, sin esa opción. */
  permiteQuitar?: boolean;
};

function formatearHora(d: Date): string {
  return d.toLocaleTimeString('es-EC', { hour: '2-digit', minute: '2-digit' });
}

/** Un solo campo "Horario de entrega" que abre un modal con los dos selectores
 * (Desde/Hasta) embebidos y visibles a la vez, confirmados con un botón Aceptar. */
export default function SelectorRangoHoras({ label, horaInicial, horaMaxima, onChange, permiteQuitar = true }: Props) {
  const [abierto, setAbierto] = useState(false);
  const [borradorInicial, setBorradorInicial] = useState<Date>(horaInicial ?? new Date());
  const [borradorMaxima, setBorradorMaxima] = useState<Date>(horaMaxima ?? new Date());

  const hayValor = !!horaInicial || !!horaMaxima;
  const texto = hayValor
    ? `${horaInicial ? formatearHora(horaInicial) : '--:--'} - ${horaMaxima ? formatearHora(horaMaxima) : '--:--'}`
    : 'Opcional';

  function abrir() {
    setBorradorInicial(horaInicial ?? new Date());
    setBorradorMaxima(horaMaxima ?? new Date());
    setAbierto(true);
  }

  return (
    <View>
      <Text style={styles.label}>{label}</Text>
      <TouchableOpacity style={styles.input} onPress={abrir}>
        <Text style={hayValor ? styles.valor : styles.placeholder}>{texto}</Text>
        <Ionicons name="time-outline" size={18} color="#0d6efd" />
      </TouchableOpacity>

      <Modal visible={abierto} animationType="slide" transparent onRequestClose={() => setAbierto(false)}>
        <View style={styles.overlay}>
          <View style={styles.hoja}>
            <Text style={styles.hojaTitulo}>{label}</Text>

            <Text style={styles.subLabel}>Desde</Text>
            <DateTimePicker
              value={borradorInicial}
              mode="time"
              is24Hour
              display="spinner"
              onChange={(_e, seleccionado) => seleccionado && setBorradorInicial(seleccionado)}
            />

            <Text style={styles.subLabel}>Hasta</Text>
            <DateTimePicker
              value={borradorMaxima}
              mode="time"
              is24Hour
              display="spinner"
              onChange={(_e, seleccionado) => seleccionado && setBorradorMaxima(seleccionado)}
            />

            <View style={styles.acciones}>
              {hayValor && permiteQuitar ? (
                <TouchableOpacity
                  onPress={() => {
                    onChange(null, null);
                    setAbierto(false);
                  }}
                >
                  <Text style={styles.quitar}>Quitar horario</Text>
                </TouchableOpacity>
              ) : (
                <View />
              )}
              <View style={{ flexDirection: 'row', gap: 16, alignItems: 'center' }}>
                <TouchableOpacity onPress={() => setAbierto(false)}>
                  <Text style={styles.cancelar}>Cancelar</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={styles.aceptar}
                  onPress={() => {
                    if (horaEnMinutos(borradorInicial) >= horaEnMinutos(borradorMaxima)) {
                      Alert.alert(
                        'Horario inválido',
                        'La hora "Desde" no puede ser igual o mayor a la hora "Hasta" (y "Hasta" no puede ser igual o menor a "Desde").'
                      );
                      return;
                    }
                    onChange(borradorInicial, borradorMaxima);
                    setAbierto(false);
                  }}
                >
                  <Text style={styles.aceptarTexto}>Aceptar</Text>
                </TouchableOpacity>
              </View>
            </View>
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
  overlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.4)', justifyContent: 'flex-end' },
  hoja: { backgroundColor: '#fff', borderTopLeftRadius: 16, borderTopRightRadius: 16, padding: 16 },
  hojaTitulo: { fontSize: 16, fontWeight: '700', marginBottom: 4, textAlign: 'center' },
  subLabel: { fontSize: 13, color: '#666', fontWeight: '600', marginTop: 8 },
  acciones: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 12 },
  quitar: { color: '#dc3545', fontSize: 13, fontWeight: '600' },
  cancelar: { color: '#666', fontWeight: '600' },
  aceptar: { backgroundColor: '#0d6efd', borderRadius: 8, paddingHorizontal: 20, paddingVertical: 10 },
  aceptarTexto: { color: '#fff', fontWeight: '700' },
});
