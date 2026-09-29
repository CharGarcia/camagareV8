import { useCallback, useRef, useState } from 'react';
import { mensajeError } from '../api/client';

export type PaginaListado<T> = {
  data: T[];
  meta: { total: number; total_pages: number; page: number };
};

/**
 * Listado con carga por páginas ("scroll infinito"): la primera página llega con
 * cargar() y las siguientes se piden solas con cargarMas() cuando el usuario llega
 * al final de la lista (FlatList onEndReached). Antes cada pantalla pedía solo la
 * página 1 y el usuario veía únicamente las 20 filas más recientes.
 *
 * Cada búsqueda/refresco invalida las respuestas en vuelo de la anterior (idPeticion),
 * para que una página vieja no se mezcle con los resultados de la búsqueda nueva.
 */
export function useListadoPaginado<T>(
  pedirPagina: (page: number) => Promise<PaginaListado<T>>,
  mensajeErrorDefecto: string
) {
  const [items, setItems] = useState<T[]>([]);
  const [total, setTotal] = useState(0);
  const [cargando, setCargando] = useState(true);
  const [refrescando, setRefrescando] = useState(false);
  const [cargandoMas, setCargandoMas] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const paginaRef = useRef(1);
  const totalPaginasRef = useRef(1);
  const idPeticion = useRef(0);
  const cargandoMasRef = useRef(false);
  const pedirRef = useRef(pedirPagina);
  pedirRef.current = pedirPagina;

  const cargar = useCallback(
    async (esRefresh = false) => {
      const id = ++idPeticion.current;
      cargandoMasRef.current = false;
      setCargandoMas(false);
      esRefresh ? setRefrescando(true) : setCargando(true);
      setError(null);
      try {
        const resp = await pedirRef.current(1);
        if (id !== idPeticion.current) return;
        setItems(resp.data);
        setTotal(resp.meta?.total ?? resp.data.length);
        paginaRef.current = 1;
        totalPaginasRef.current = resp.meta?.total_pages ?? 1;
      } catch (err) {
        if (id !== idPeticion.current) return;
        setError(mensajeError(err, mensajeErrorDefecto));
      } finally {
        if (id === idPeticion.current) {
          esRefresh ? setRefrescando(false) : setCargando(false);
        }
      }
    },
    [mensajeErrorDefecto]
  );

  const cargarMas = useCallback(async () => {
    if (cargandoMasRef.current || paginaRef.current >= totalPaginasRef.current) return;
    const id = idPeticion.current;
    const siguiente = paginaRef.current + 1;
    cargandoMasRef.current = true;
    setCargandoMas(true);
    try {
      const resp = await pedirRef.current(siguiente);
      if (id !== idPeticion.current) return;
      setItems((prev) => [...prev, ...resp.data]);
      setTotal(resp.meta?.total ?? total);
      paginaRef.current = siguiente;
      totalPaginasRef.current = resp.meta?.total_pages ?? totalPaginasRef.current;
    } catch (err) {
      if (id !== idPeticion.current) return;
      setError(mensajeError(err, mensajeErrorDefecto));
    } finally {
      if (id === idPeticion.current) {
        cargandoMasRef.current = false;
        setCargandoMas(false);
      }
    }
  }, [mensajeErrorDefecto, total]);

  return { items, setItems, total, cargando, refrescando, cargandoMas, error, cargar, cargarMas };
}
