# BiblioDigital — Fita6 Admin (PHP + MySQL)

Gestión de biblioteca: catálogo público, préstamos/reservas, activación por email y panel admin (alta/edición/stock/estados/borrado soft-hard con auditoría).

## Stack

- PHP 8 + PDO MySQL (probado en XAMPP), sin dependencias salvo PHPMailer incluido en `includes/PHPMailer`
- MySQL/MariaDB, HTML/CSS vanilla (`css/`)
- Email: SMTP configurable + fallback a `logs/emails.log` (funciona sin SMTP)

## Estructura

```
BiblioDigital/
├── index.php             # Portada = redirige a pages/login.php
├── pages/
│   ├── login.php / register.php / activate.php   # Auth real
│   ├── dashboard.php / catalog.php / profile.php # Zona usuario
│   ├── login.html / dashboard.html / ...         # Maquetas estáticas Fita 3
│   └── logout.php
├── admin/
│   ├── index.php         # Catálogo admin
│   ├── alta_llibre.php / editar_llibre.php / stock_llibre.php
│   ├── estat_llibre.php / eliminar_llibre.php / header_admin.php
│   └── auth_admin.php
├── config/
│   ├── env.php           # Carga .env
│   ├── database.php      # PDO (DB_* desde .env)
│   ├── mail.php          # SMTP (MAIL_* desde .env)
│   ├── init_db.php       # Instalación limpia (crea todo)
│   ├── migrate_activation.php / migrate_f06.php  # Solo BDs antiguas
├── includes/
│   ├── auth.php / header.php / mail_helper.php / PHPMailer/
├── css/ / design_assets/ / img/ (.gitkeep)
└── taules (1).sql        # Esquema base (sin migraciones)
```

## Puesta en marcha (5 min)

1. Requisitos: PHP 8 + MySQL (XAMPP sirve), Apache.
2. Copiar la carpeta a `htdocs/BiblioDigital` (o tu `APP_URL`).
3. Crear BD vacía `biblioteca_digital` (utf8mb4).
4. Copiar config:
```bash
cp .env.example .env
# Edita .env: DB_PORT (3306 XAMPP por defecto), APP_URL=http://localhost/BiblioDigital
```
5. Instalar (crea tablas + `eliminat` + `Audit_Stock` + datos demo):
```
http://localhost/BiblioDigital/config/init_db.php
```
6. Entrar:
```
http://localhost/BiblioDigital/pages/login.php
Admin: admin@biblioteca.edu / admin123
Alumno pendiente: estudiant@biblioteca.edu / user123
```

> `pages/*.html` son maquetas estáticas de la Fita 3. La app real son los `.php`.
> La raíz (`/`) redirige a `pages/login.php`.
> Si tu BD ya existía de antes, ejecuta además `migrate_activation.php` y `migrate_f06.php` una vez.

## Email sin SMTP

Si `MAIL_USERNAME`/`MAIL_PASSWORD` están vacíos en `.env`, no se envía por SMTP y se guarda en `logs/emails.log`. El registro y `activate.php` muestran además un enlace directo de activación para pruebas.

Para Gmail real: crea una App Password y ponla en `.env` (nunca en git).

## Licencia

MIT — ver [LICENSE](LICENSE).
