# CaMaGaRe - Sistema MVC

PHP 8+ | Bootstrap 5+ | MySQL

## Estructura

```
sistema/
├── app/
│   ├── controllers/
│   ├── models/
│   ├── views/
│   │   ├── layouts/
│   │   ├── home/
│   │   ├── usuarios/
│   │   ├── productos/
│   │   ├── empresa/
│   │   └── cliente/
│   ├── core/
│   ├── helpers/
│   └── middleware/
├── config/
├── routes/
├── public/
├── storage/
├── legacy/          # Sistema anterior
└── .env.example
```

## URLs

- **Login:** http://localhost/sistema/
- **Aplicación:** http://localhost/sistema/public/

## Requisitos

- PHP 8.0+
- MySQL 5.7+
- Apache con mod_rewrite
