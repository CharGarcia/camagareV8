import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { obtenerSeries, Establecimiento } from '../api/pedidos';
import { clearSerie, getSerieSeleccionada, setSerieSeleccionada, SerieGuardada } from '../auth/tokenStore';
import { useAuth } from '../auth/AuthContext';

type SerieState = {
  cargando: boolean;
  serie: SerieGuardada | null;
  requiereSeleccion: boolean;
  establecimientos: Establecimiento[];
  cargarEstablecimientos: () => Promise<void>;
  seleccionarSerie: (est: Establecimiento, punto: Establecimiento['puntos_emision'][number]) => Promise<void>;
  cambiarSerie: () => void;
};

const SerieContext = createContext<SerieState | undefined>(undefined);

export function SerieProvider({ children }: { children: React.ReactNode }) {
  const { idEmpresa } = useAuth();
  const [cargando, setCargando] = useState(true);
  const [serie, setSerie] = useState<SerieGuardada | null>(null);
  const [establecimientos, setEstablecimientos] = useState<Establecimiento[]>([]);

  // Al cambiar de empresa activa, la serie guardada de otra empresa ya no aplica.
  useEffect(() => {
    (async () => {
      if (!idEmpresa) {
        setSerie(null);
        setCargando(false);
        return;
      }
      setCargando(true);
      const guardada = await getSerieSeleccionada(idEmpresa);
      setSerie(guardada);
      setCargando(false);
    })();
  }, [idEmpresa]);

  const cargarEstablecimientos = useCallback(async () => {
    setEstablecimientos(await obtenerSeries());
  }, []);

  const seleccionarSerie = useCallback(
    async (est: Establecimiento, punto: Establecimiento['puntos_emision'][number]) => {
      if (!idEmpresa) return;
      const nueva: SerieGuardada = {
        idEmpresa,
        id_establecimiento: est.id_establecimiento,
        establecimiento: est.establecimiento,
        id_punto_emision: punto.id_punto_emision,
        punto_emision: punto.punto_emision,
      };
      await setSerieSeleccionada(nueva);
      setSerie(nueva);
    },
    [idEmpresa]
  );

  const cambiarSerie = useCallback(() => {
    clearSerie();
    setSerie(null);
  }, []);

  const value = useMemo<SerieState>(
    () => ({
      cargando,
      serie,
      requiereSeleccion: !cargando && serie === null,
      establecimientos,
      cargarEstablecimientos,
      seleccionarSerie,
      cambiarSerie,
    }),
    [cargando, serie, establecimientos, cargarEstablecimientos, seleccionarSerie, cambiarSerie]
  );

  return <SerieContext.Provider value={value}>{children}</SerieContext.Provider>;
}

export function useSerie(): SerieState {
  const ctx = useContext(SerieContext);
  if (!ctx) throw new Error('useSerie debe usarse dentro de <SerieProvider>');
  return ctx;
}
