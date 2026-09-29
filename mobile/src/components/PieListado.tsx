import React from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

/**
 * Pie de los listados paginados (useListadoPaginado): muestra el indicador mientras
 * llega la siguiente página y, si no, cuántas filas se ven de cuántas hay.
 */
export default function PieListado({ cargandoMas, mostrados, total }: { cargandoMas: boolean; mostrados: number; total: number }) {
  if (cargandoMas) {
    return <ActivityIndicator color="#0d6efd" style={styles.cargando} />;
  }
  if (mostrados === 0) return null;
  return (
    <View style={styles.pie}>
      <Text style={styles.texto}>
        {mostrados < total ? `Mostrando ${mostrados} de ${total} · desliza para ver más` : `${total} en total`}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  cargando: { marginVertical: 16 },
  pie: { paddingVertical: 12, alignItems: 'center' },
  texto: { fontSize: 12, color: '#888' },
});
