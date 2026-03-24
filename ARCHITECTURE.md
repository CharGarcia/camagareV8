# Arquitectura del sistema

## Estructura principal

```
sistema/
├── app/
│   ├── controllers/     # HomeController, EmpresaController, ClienteController, etc.
│   ├── models/          # BaseModel, Empresa, Usuario, Producto
│   ├── views/
│   │   ├── layouts/     # header.php, footer.php, main.php, guest.php
│   │   ├── home/
│   │   ├── usuarios/
│   │   ├── productos/
│   │   ├── empresa/
│   │   └── cliente/
│   ├── core/            # Controller, Model, Router, Database, Application
│   ├── helpers/         # helpers.php, funciones.php
│   └── middleware/      # AuthMiddleware
├── config/              # app.php, database.php, parametros.xml
├── routes/              # web.php
├── public/              # index.php, css, js, img
├── storage/             # logs, uploads
└── legacy/              # Sistema anterior (ex-archivos, includes, etc.)
```

## Flujo

1. Login en `/sistema/` (usa legacy/includes)
2. Redirección a `/sistema/public/`
3. Front controller en public/index.php
4. Router → Controller → View
