/**
 * Cálculo de la vista previa de totales en la app, igual que el servidor (CLAUDE.md §9).
 *
 * - r2(): redondeo a 2 decimales "medio hacia arriba" sin el error de coma flotante de
 *   Math.round(v * 100) / 100 (con 98,10 × 15% daba 14,71 en vez de 14,72). Mismo criterio
 *   que CMG_r2() de la web: se corrige primero con toPrecision(12).
 * - repartirIva(): réplica de App\Helpers\IvaSubtotal::repartir(). En modo 'subtotal' el IVA
 *   de cada tarifa es round(Σ bases × %) una sola vez y sus centavos se reparten entre las
 *   líneas; en 'linea_linea' cada línea redondea su propio IVA.
 *
 * El servidor siempre recalcula al guardar: esto solo hace que lo que el usuario ve antes
 * de guardar coincida con lo que se guarda.
 */

export type ModoIva = 'subtotal' | 'linea_linea';

export function redondear(n: number, decimales: number): number {
  if (!Number.isFinite(n)) return 0;
  const f = Math.pow(10, decimales);
  const v = Number((n * f).toPrecision(12));
  return Math.sign(v) * Math.round(Math.abs(v)) / f;
}

export function r2(n: number): number {
  return redondear(n, 2);
}

export function modoIva(valor: string | null | undefined): ModoIva {
  return valor === 'subtotal' ? 'subtotal' : 'linea_linea';
}

/** IVA de cada línea (mismo orden que `lineas`). `grupo` agrupa la tarifa (p. ej. el %). */
export function repartirIva(lineas: { base: number; pct: number; grupo?: string }[], modo: ModoIva): number[] {
  const iva = lineas.map((l) => r2((l.base * l.pct) / 100));
  if (modo !== 'subtotal') return iva;

  const grupos = new Map<string, { pct: number; base: number; idx: number[] }>();
  lineas.forEach((l, i) => {
    if (l.pct <= 0) return;
    const clave = l.grupo ?? String(l.pct);
    const g = grupos.get(clave) ?? { pct: l.pct, base: 0, idx: [] };
    g.base += l.base;
    g.idx.push(i);
    grupos.set(clave, g);
  });

  for (const g of grupos.values()) {
    const objetivo = r2((r2(g.base) * g.pct) / 100);
    const suma = r2(g.idx.reduce((a, i) => a + iva[i], 0));
    let centavos = Math.round((objetivo - suma) * 100);
    if (centavos === 0) continue;

    // Faltan centavos → subir primero las que más perdieron al redondear; sobran → bajar
    // primero las que más ganaron (igual que el servidor).
    const residuo = (i: number) => (lineas[i].base * g.pct) / 100 - iva[i];
    const orden = [...g.idx].sort((a, b) => (centavos > 0 ? residuo(b) - residuo(a) : residuo(a) - residuo(b)));
    const paso = centavos > 0 ? 0.01 : -0.01;
    for (let k = 0; centavos !== 0; k = (k + 1) % orden.length) {
      iva[orden[k]] = r2(iva[orden[k]] + paso);
      centavos += centavos > 0 ? -1 : 1;
    }
  }
  return iva;
}
