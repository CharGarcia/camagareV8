import React from 'react';
import { FontAwesome5 } from '@expo/vector-icons';

/**
 * La BD guarda clases tipo "fa-cart", "fas fa-tachometer-alt", "bi bi-house" (mismo
 * valor que usa el navbar web vía el helper iconoClase()). @expo/vector-icons no trae
 * Bootstrap Icons, así que solo se mapea razonablemente el set FontAwesome; si el
 * nombre no existe en la librería, el ícono simplemente sale en blanco (no rompe nada).
 */
function extraerNombreIcono(claseCss: string): string {
  const partes = claseCss.trim().split(/\s+/);
  const ultima = partes[partes.length - 1] || 'folder';
  return ultima.replace(/^(fa|bi)-/, '') || 'folder';
}

export default function ModuloIcono({
  clase,
  size = 20,
  color = '#0d6efd',
}: {
  clase: string;
  size?: number;
  color?: string;
}) {
  const nombre = extraerNombreIcono(clase || 'fa-folder');
  return <FontAwesome5 name={nombre as React.ComponentProps<typeof FontAwesome5>['name']} size={size} color={color} />;
}
