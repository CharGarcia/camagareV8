-- ============================================================================
-- Habilita la extensión `unaccent` de PostgreSQL
-- ============================================================================
-- PARA QUÉ
--   Permite que los buscadores del sistema encuentren resultados sin importar
--   tildes/diéresis/eñe (buscar "compania" encuentra "COMPAÑÍA", "nono" encuentra
--   "ÑOÑO"). App\Helpers\FiltrosBusqueda::condicionTexto() —la función estándar
--   nueva para armar buscadores de texto libre— usa unaccent() automáticamente
--   SI está instalada; si no lo está, sigue funcionando igual pero sensible a
--   tildes (degradación segura, no rompe nada si este script no se ha corrido).
--
-- REQUISITOS
--   El rol de la aplicación necesita permiso para crear extensiones. En Postgres
--   administrado (DigitalOcean Managed PG, etc.) normalmente el rol admin ya lo
--   tiene para extensiones de la lista blanca (unaccent está en esa lista en la
--   mayoría de proveedores). Si este script falla por permisos, pedir al
--   proveedor/administrador de la BD que la habilite.
--
-- USO
--   Ejecutar una sola vez. No destructivo, no afecta datos existentes.
-- ============================================================================

CREATE EXTENSION IF NOT EXISTS unaccent;

-- Verificación: debe devolver 'Compania Garcia Nono' (sin tildes).
SELECT unaccent('Compañía García Ñoño') AS prueba_unaccent;
