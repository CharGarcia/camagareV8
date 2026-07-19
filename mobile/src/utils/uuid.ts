/** Id único generado en el celular, no criptográfico — solo para idempotencia del
 * backend (UNIQUE por uuid_cliente), no para seguridad. */
export function generarUuid(): string {
  return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
}
