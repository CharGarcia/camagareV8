import React, { useState } from 'react';
import { Modal, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import DateTimePicker from '@react-native-community/datetimepicker';
import { Ionicons } from '@expo/vector-icons';

type Props = {
  label: string;
  value: Date | null;
  onChange: (fecha: Date | null) => void;
  mode: 'date' | 'time';
  minimumDate?: Date;
  /** false (default): permite dejarlo vacío con un link "Quitar". true: obligatorio, sin esa opción. */
  permiteQuitar?: boolean;
};

function formatear(d: Date, mode: 'date' | 'time'): string {
  if (mode === 'date') {
    return d.toLocaleDateString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }
  return d.toLocaleTimeString('es-EC', { hour: '2-digit', minute: '2-digit' });
}

// "spinner" se embebe en el layout (no abre un diálogo del sistema aparte) para
// poder controlarlo con nuestros propios botones Cancelar/Aceptar dentro de una
// sola hoja modal. OJO: el modo "calendar" de Android (CalendarView nativo) tiene
// un bug conocido donde, al combinarlo con minimumDate, el día de HOY queda
// bloqueado/no seleccionable — por eso se usa "spinner" también para fechas, no
// solo para horas.

export default function SelectorFechaHora({ label, value, onChange, mode, minimumDate, permiteQuitar = true }: Props) {
  const [abierto, setAbierto] = useState(false);
  const [borrador, setBorrador] = useState<Date>(value ?? new Date());

  function abrir() {
    setBorrador(value ?? new Date());
    setAbierto(true);
  }

  return (
    <View style={{ flex: 1 }}>
      <Text style={styles.label}>{label}</Text>
      <TouchableOpacity style={styles.input} onPress={abrir}>
        <Text style={value ? styles.valor : styles.placeholder}>
          {value ? formatear(value, mode) : 'Opcional'}
        </Text>
        <Ionicons name={mode === 'date' ? 'calendar-outline' : 'time-outline'} size={18} color="#0d6efd" />
      </TouchableOpacity>
      {value && permiteQuitar ? (
        <TouchableOpacity onPress={() => onChange(null)}>
          <Text style={styles.quitar}>Quitar</Text>
        </TouchableOpacity>
      ) : null}

      <Modal visible={abierto} animationType="slide" transparent onRequestClose={() => setAbierto(false)}>
        <View style={styles.overlay}>
          <View style={styles.hoja}>
            <Text style={styles.hojaTitulo}>{label}</Text>
            <DateTimePicker
              value={borrador}
              mode={mode}
              is24Hour
              display="spinner"
              minimumDate={minimumDate}
              onChange={(_evento, seleccionado) => {
                if (seleccionado) setBorrador(seleccionado);
              }}
            />
            <View style={styles.acciones}>
              <TouchableOpacity onPress={() => setAbierto(false)}>
                <Text style={styles.cancelar}>Cancelar</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.aceptar}
                onPress={() => {
                  onChange(borrador);
                  setAbierto(false);
                }}
              >
                <Text style={styles.aceptarTexto}>Aceptar</Text>
              </TouchableOpacity>
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
  quitar: { color: '#dc3545', fontSize: 12, marginTop: 2 },
  overlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.4)', justifyContent: 'flex-end' },
  hoja: { backgroundColor: '#fff', borderTopLeftRadius: 16, borderTopRightRadius: 16, padding: 16 },
  hojaTitulo: { fontSize: 16, fontWeight: '700', marginBottom: 8, textAlign: 'center' },
  acciones: { flexDirection: 'row', justifyContent: 'flex-end', gap: 16, marginTop: 8, alignItems: 'center' },
  cancelar: { color: '#666', fontWeight: '600', paddingVertical: 10, paddingHorizontal: 8 },
  aceptar: { backgroundColor: '#0d6efd', borderRadius: 8, paddingHorizontal: 20, paddingVertical: 10 },
  aceptarTexto: { color: '#fff', fontWeight: '700' },
});
