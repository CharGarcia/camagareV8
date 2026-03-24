<?php
/**
 * Crea un usuario inicial por CLI.
 * - Nivel 3 (SuperAdmin): solo usuario; puede entrar sin empresa y crear empresas en la app.
 * - Nivel 1–2: además asigna empresa existente o crea una demo (el login exige empresa).
 *
 * Uso (desde la raíz del proyecto):
 *   php scripts/crear_usuario_inicial.php --cedula=1234567890 --nombre="Administrador" --password="TuClaveSegura" --mail=admin@ejemplo.com
 *
 * Opcional:
 *   --nivel=3        (1=Usuario, 2=Admin, 3=SuperAdmin; por defecto 3)
 *   --con-empresa-demo   Si nivel<3 o si quieres forzar empresa demo también para nivel 3
 *   --ruc=0999999999001  RUC para empresa demo (13 dígitos)
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo se ejecuta por línea de comandos (CLI).\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/bootstrap.php';

use App\models\Empresa;
use App\models\EmpresaAsignada;
use App\models\Usuario;

$longopts = ['cedula:', 'nombre:', 'password:', 'mail:', 'nivel::', 'ruc::', 'con-empresa-demo'];
$opts = getopt('', $longopts);

$cedula = trim((string) ($opts['cedula'] ?? ''));
$nombre = trim((string) ($opts['nombre'] ?? ''));
$password = (string) ($opts['password'] ?? '');
$mail = trim((string) ($opts['mail'] ?? ''));
$nivel = (int) ($opts['nivel'] ?? 3);
$rucDemo = preg_replace('/\D/', '', (string) ($opts['ruc'] ?? '0999999999001'));
$conEmpresaDemo = array_key_exists('con-empresa-demo', $opts);

if ($cedula === '' || $nombre === '') {
    fwrite(STDERR, "Obligatorio: --cedula y --nombre\n");
    fwrite(STDERR, "Ejemplo: php scripts/crear_usuario_inicial.php --cedula=1234567890 --nombre=Admin --password=Clave123 --mail=admin@local.test\n");
    exit(1);
}

if (strlen($password) < 4) {
    fwrite(STDERR, "La contraseña debe tener al menos 4 caracteres (--password=...).\n");
    exit(1);
}

$nivel = max(1, min(3, $nivel));

$usuarioModel = new Usuario();
if ($usuarioModel->existePorCedula($cedula)) {
    fwrite(STDERR, "Ya existe un usuario con la cédula indicada.\n");
    exit(1);
}

try {
    $idUsuario = $usuarioModel->crear([
        'nombre' => $nombre,
        'cedula' => $cedula,
        'password' => $password,
        'nivel' => $nivel,
        'mail' => $mail,
    ]);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Error al crear usuario: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Usuario creado: id={$idUsuario}, nivel={$nivel}, cédula={$cedula}\n";

if ($nivel === 3 && !$conEmpresaDemo) {
    echo "SuperAdmin: puede iniciar sesión sin empresa; cree la primera en Configuración → Empresas del sistema.\n";
    exit(0);
}

$empresaModel = new Empresa();
$rows = $empresaModel->query("SELECT id FROM empresas WHERE estado = '1' ORDER BY id ASC LIMIT 1");
$idEmpresa = (int) ($rows[0]['id'] ?? 0);

if ($idEmpresa <= 0) {
    try {
        $idEmpresa = $empresaModel->crear([
            'nombre' => 'Empresa inicial (configurar)',
            'nombre_comercial' => 'Mi empresa',
            'ruc' => $rucDemo,
            'direccion' => '',
            'telefono' => '',
            'mail' => $mail !== '' ? $mail : 'contacto@local.test',
            'id_usuario' => (string) $idUsuario,
            'estado' => '1',
        ]);
        echo "Empresa demo creada: id={$idEmpresa}, RUC={$rucDemo}\n";
    } catch (\Throwable $e) {
        fwrite(STDERR, 'Usuario creado pero falló crear empresa: ' . $e->getMessage() . "\n");
        fwrite(STDERR, "Use --con-empresa-demo con otro --ruc= o cree la empresa desde la aplicación (si es SuperAdmin).\n");
        exit(1);
    }
} else {
    echo "Usando empresa existente: id={$idEmpresa}\n";
}

$ea = new EmpresaAsignada();
if ($ea->asignar($idEmpresa, $idUsuario, $idUsuario)) {
    echo "Asignación empresa_asignada: usuario {$idUsuario} ↔ empresa {$idEmpresa}\n";
} else {
    fwrite(STDERR, "No se pudo asignar la empresa (¿ya estaba asignada?). Comprueba en empresa_asignada.\n");
    exit(1);
}

echo "Listo. Puedes iniciar sesión con la cédula y la contraseña indicadas.\n";
exit(0);
