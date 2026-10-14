# Hospital Materno Rosa

Sistema web de **control de citas e historias medicas**. Construido en **PHP 8 + Slim Framework 4** con
plantillas **Twig** y **Tailwind CSS 4**, a partir del diseno de Figma y del modelo de base de datos
(`database.dbml`) del proyecto.

---

## Requisitos

| Herramienta | Version usada |
|---|---|
| PHP | 8.1 o superior (probado con 8.5.7) |
| Composer | 2.x |
| Node.js / npm | 18 o superior (probado con 24 / 11) |
| MySQL / MariaDB | via XAMPP (puerto 3306) |

---

## Puesta en marcha

```powershell
# 1. Dependencias PHP
composer install

# 2. Dependencias y compilacion de Tailwind
npm install
npm run build

# 3. Configuracion (copiar y ajustar credenciales)
Copy-Item .env.example .env

# 4. Encender MySQL en el panel de XAMPP y crear la base de datos
php database/setup.php

# 5. Levantar el servidor
php -S localhost:8000 -t public public/router.php
```

Abrir <http://localhost:8000>.

> En VS Code: `Ctrl+Shift+P` → *Tasks: Run Task* → **Servidor PHP**, **Tailwind: observar cambios** y
> **Base de datos: crear y cargar datos**.

### Cuentas de demostracion (clave: `123456`)

| Rol | Cedula |
|---|---|
| Administrador | `V-10000001` |
| Medico (Ginecologia) | `V-20000002` |
| Medico (Neonatologia) | `V-20000003` |
| Tecnico de Registro | `V-30000004` |
| Paciente | `V-40000005` |
| Paciente | `V-40000006` |

---

## Estructura del proyecto

```
hospital-materno/
├── config/               settings.php (config) · bootstrap.php (arranque Slim)
├── database/             schema.sql · seed.sql · setup.php
├── public/               Document root
│   ├── index.php         Front controller
│   ├── router.php        Router para el servidor embebido de PHP
│   ├── .htaccess         Rewrite para Apache
│   └── assets/           css/app.css (compilado) · js/app.js
├── resources/css/        app.css (fuente Tailwind + tokens de diseno)
├── src/
│   ├── Controllers/      Home · Auth · Paciente · Medico · Tecnico · Admin
│   ├── Middleware/       Auth · Guest · Role · VerifyCsrf
│   ├── Routes/           web.php · middleware.php
│   └── Support/          Container · Database · Session · Flash · Csrf · Validator
│                         View · TwigExtension · ErrorHandler · helpers.php
├── views/
│   ├── layouts/          public.twig · dashboard.twig
│   ├── partials/         icon.twig · flash.twig
│   ├── home/             index.twig
│   ├── auth/             login.twig (login + registro)
│   ├── paciente/         agendar · datos · citas · comprobante
│   ├── medico/           index · historias · ficha · horario
│   ├── tecnico/          index · registros · expediente · imprimir
│   └── admin/            personal · horarios
└── .vscode/              settings · extensions · launch · tasks
```

---

## Modulos y rutas

### Publico
| Ruta | Descripcion |
|---|---|
| `GET /` | Landing: hero, servicios, horarios, nosotros, contacto |
| `GET /login` | Acceso y registro de pacientes |
| `POST /login` / `POST /register` / `POST /logout` | Autenticacion |

### Paciente (`/paciente`)
- **Agendar Cita**: calendario mensual con disponibilidad por dia (Disponible / Pocos cupos / Lleno / Cerrado) y seleccion de horario.
- **Mis Datos**: datos personales editables, contacto de emergencia y antecedentes gineco-obstetricos.
- **Mis Citas**: historial, receta/diagnostico, cancelacion y comprobante imprimible.

### Medico (`/medico`)
- **Inicio**: metricas de agenda y pacientes en espera del dia.
- **Historias**: buscador y filtro por tipo; ficha clinica (Primera Vez / Control) con antecedentes, historial previo, diagnostico y plan.
- **Horario**: turnos asignados y carga de citas proximas.

### Tecnico de Registro (`/tecnico`)
- **Panel de Estadisticas**: filtros por periodo (hoy / semana / mes / personalizado), atenciones por especialidad.
- **Registros Medicos**: expedientes, vista de solo lectura, impresion y **exportacion a CSV**.

### Administrador (`/admin`)
- **Gestion de Personal**: alta de empleados con rol RBAC, clave temporal y activacion/desactivacion.
- **Horarios y Cupos**: turnos por medico, dias, cupos, consultorio y nivel de ocupacion.

---

## Base de datos

Tablas segun `database.dbml`:
`roles`, `especialidades`, `usuarios`, `pacientes`, `medicos`, `historia`, `citas`,
`detalle_historia`, `adjuntos_archivos`.

### Extensiones aplicadas al esquema original

Documentadas en `database/schema.sql` (marcadas con `EXTENSION DEL PROYECTO`):

1. **`horarios_medico`** — tabla nueva. El diseno incluye "Horarios y Cupos" y el calendario de
   disponibilidad, pero el dbml original no tenia donde guardarlos.
2. **`usuarios.nombres`, `usuarios.apellidos`, `usuarios.correo`** — el diseno muestra nombre y correo
   para todo el personal, pero el dbml solo los guardaba en `pacientes` y `medicos`.

Ambas se pueden revertir si la catedra exige el esquema estrictamente original; para ello habria que
ajustar las vistas de administracion y el calendario.

### Regenerar la base de datos

```powershell
php database/setup.php
```

> Atencion: `setup.php` **elimina y recrea** las tablas (usa `DROP TABLE`).

---

## Design system

Extraido de `src/styles/theme.css` y `fonts.css` del proyecto de Figma y definido como tokens de
Tailwind en `resources/css/app.css`:

| Token | Valor |
|---|---|
| Primario | `#E8819E` |
| Secundario | `#8EC9E8` |
| Acento | `#A8D9C2` |
| Lavanda | `#9B78D0` |
| Fondo | `#FFF8FB` |
| Texto | `#2C2438` |
| Titulos | DM Serif Display |
| Texto | Nunito |
| Radio | `1rem` |

---

## Seguridad implementada

- Contrasenas con `password_hash()` / `password_verify()` (bcrypt).
- Token **CSRF** en todos los formularios (`VerifyCsrfMiddleware`).
- Control de acceso por **rol** (`AuthMiddleware` + `RoleMiddleware`).
- Consultas con **PDO y sentencias preparadas** (sin concatenacion de SQL).
- Escapado automatico de Twig y `htmlspecialchars` en helpers.
- Regeneracion del id de sesion al iniciar sesion.

---

## Notas para la evaluacion

- El login es real contra la tabla `usuarios`.
- Los modulos operan sobre la base de datos; los datos de demostracion se cargan con `seed.sql`.
- Para modificar estilos: editar `resources/css/app.css` o los `.twig` y ejecutar `npm run build`
  (o `npm run dev` para recompilar al guardar).
