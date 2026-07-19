// Cambia esto a false para volver a apuntar al backend local (útil mientras seguimos
// construyendo módulos nuevos). true = producción real (cuidado: los datos que crees
// quedan guardados de verdad, no hay limpieza automática como en el entorno local).
const USAR_PRODUCCION = false;

// URL base de la API. El celular es OTRO dispositivo en la red: no sirve "localhost".
// Windows: ipconfig -> "Dirección IPv4" de tu adaptador WiFi/Ethernet. El celular debe
// estar en la MISMA red WiFi que esta PC para la variante local.
const LOCAL = 'http://192.168.100.228/sistema/public/api/v1';
const PRODUCCION = 'https://erp.camagare.com.ec/api/v1';

export const API_BASE_URL = USAR_PRODUCCION ? PRODUCCION : LOCAL;

// Base para archivos servidos como estáticos (imágenes subidas, etc.), que NO viven
// bajo /api/v1 sino directo en /public. Se deriva quitando el sufijo de la API.
export const ASSET_BASE_URL = API_BASE_URL.replace(/\/api\/v1\/?$/, '');

