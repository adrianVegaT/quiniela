# Plan: Gestión de quiniela (Admin CRUD)

## Contexto

El administrador (creador de la quiniela) necesita pantallas para crear y gestionar su quiniela: registrar equipos, agruparlos, programar partidos, configurar puntuaciones y obtener el código de invitación. Estas operaciones son exclusivas del owner de la quiniela.

**Precondición**: el plan `data-model.md` debe estar completado (entidades y migraciones listas).

**Alcance**: formularios de creación y edición para Quiniela, Teams, Groups, Matches y ScoringRules. Visualización del código de invitación. Sin participantes aún (eso va en plan `user-predictions.md`).

---

## Arquitectura

### Flujo del admin
1. Crear quiniela → configurar nombre, descripción, deadline
2. Se genera automáticamente código único de 8 caracteres
3. Registrar equipos (uno a uno o carga masiva simple)
4. (Opcional) Crear grupos y asignar equipos
5. Registrar partidos con equipos, grupo, fecha/hora
6. Configurar reglas de puntuación e instrucciones
7. Compartir código con participantes (externo a la plataforma)

### Componentes Livewire SFC

Todas las pantallas usan Livewire 4 SFC (`@volt` en Blade). Se ubican en `resources/views/pages/quinielas/`.

### Rutas

Todas bajo prefijo `/quinielas`, grupo `auth` + `verified`:
- `GET /quinielas/create` → `quinielas.create`
- `POST /quinielas` → crear (redirige a `/quinielas/{quiniela}`)
- `GET /quinielas/{quiniela}` → `quinielas.show` (panel principal del admin)
- `GET /quinielas/{quiniela}/teams` → gestión de equipos
- `GET /quinielas/{quiniela}/groups` → gestión de grupos
- `GET /quinielas/{quiniela}/matches` → gestión de partidos
- `GET /quinielas/{quiniela}/scoring` → configuración de puntuación

### Políticas de autorización

- `QuinielaPolicy`: `create` (cualquier usuario autenticado), `view`, `update`, `delete` (solo owner)
- `TeamPolicy`, `GroupPolicy`, `MatchPolicy`, `ScoringRulePolicy`: heredan autorización de `Quiniela` vía `before` o delegando al método `update` de `QuinielaPolicy`

---

## Archivos a crear o modificar

| Archivo | Descripción |
|---------|-------------|
| `routes/web.php` | Agregar grupo de rutas de quinielas |
| `resources/views/layouts/app/sidebar.blade.php` | Agregar enlace "Mis quinielas" |
| `app/Policies/QuinielaPolicy.php` | Política de autorización |
| `resources/views/pages/quinielas/⚡create.blade.php` | Formulario de creación |
| `resources/views/pages/quinielas/⚡show.blade.php` | Panel del admin |
| `resources/views/pages/quinielas/teams/⚡index.blade.php` | CRUD de equipos |
| `resources/views/pages/quinielas/groups/⚡index.blade.php` | CRUD de grupos + asignación |
| `resources/views/pages/quinielas/matches/⚡index.blade.php` | CRUD de partidos |
| `resources/views/pages/quinielas/scoring/⚡index.blade.php` | Configuración de puntuación |
| `app/Livewire/Quinielas/Actions/` | Acciones Livewire (create, update, delete) |

---

## Fases con tareas atómicas

### Fase 1: Rutas, autorización y navegación

- [x] 1.1 Agregar grupo de rutas en `web.php`: `/quinielas` con middleware `auth` + `verified`
- [x] 1.2 Crear `QuinielaPolicy` con métodos `create`, `view`, `update`, `delete`
- [x] 1.3 Registrar `QuinielaPolicy` en `AuthServiceProvider`
- [x] 1.4 Agregar enlace "Mis quinielas" al sidebar de la app

### Fase 2: Crear y listar quinielas

- [x] 2.1 Crear SFC `resources/views/pages/quinielas/⚡index.blade.php`: lista de quinielas del usuario (donde es owner), con estado activo/cerrado/histórico. Botón "Crear quiniela" en la parte superior.
- [x] 2.2 Crear SFC `resources/views/pages/quinielas/⚡create.blade.php`: formulario con campos name, description (opcional), prediction_edit_deadline (datetime picker) — usar `<flux:input>`, `<flux:textarea>`, `<flux:datetime>`.
- [x] 2.3 Al guardar: generar código único de 8 caracteres alfanuméricos, asignar `owner_id = auth()->id()`, `status = 'active'`, crear `ScoringRule` con defaults (3, 1, 0). Redirigir a `quinielas.show`.
- [x] 2.4 Validación: nombre requerido (máx 100), código autogenerado (sin input del usuario), deadline opcional (debe ser fecha futura)

### Fase 3: Panel del admin (quiniela show)

- [x] 3.1 Crear SFC `resources/views/pages/quinielas/⚡show.blade.php`: panel con sub-navegación (tabs o pills):
  - Resumen: nombre, descripción, código de invitación (copiable), deadline, estado
  - Equipos (link a teams.index)
  - Grupos (link a groups.index)
  - Partidos (link a matches.index)
  - Puntuación (link a scoring.index)
  - Participantes (link a pantalla futura de gestión de usuarios)
- [x] 3.2 Mostrar código de invitación con botón de copiar al portapapeles (usando Alpine.js `navigator.clipboard`)
- [x] 3.3 Botón para editar nombre/descripción/deadline de la quiniela

### Fase 4: Gestión de equipos

- [x] 4.1 Crear SFC `resources/views/pages/quinielas/teams/⚡index.blade.php`:
  - Listado de equipos de la quiniela con nombre y grupo asignado (si tiene)
  - Modal o inline form para crear nuevo equipo (solo campo `name`, opcional `flag_url`)
  - Botón de eliminar equipo (con confirmación) — solo si no tiene partidos asociados
  - Edición inline del nombre del equipo
- [x] 4.2 Posibilidad de carga masiva: textarea donde pegar nombres de equipos (uno por línea), se crean en lote
- [x] 4.3 Validación: nombre requerido, único dentro de la quiniela

### Fase 5: Gestión de grupos

- [x] 5.1 Crear SFC `resources/views/pages/quinielas/groups/⚡index.blade.php`:
  - Listado de grupos creados
  - Crear grupo: solo nombre (ej. "Grupo A")
  - Para cada grupo: lista de equipos asignados con opción de arrastrar (drag & drop opcional — versión inicial con selects)
  - Select para agregar equipos al grupo (equipos no asignados a ningún grupo)
  - Quitar equipo del grupo
- [x] 5.2 Validación: nombre de grupo requerido, máximo 10 chars; no duplicar equipos en grupos

### Fase 6: Gestión de partidos

- [x] 6.1 Crear SFC `resources/views/pages/quinielas/matches/⚡index.blade.php`:
  - Listado de partidos ordenados por fecha (próximos primero)
  - Filtro por grupo
  - Indicador visual: partido pendiente (gris), completado (verde/check)
  - Modal/form para crear partido:
    - Select de equipo local (filtrado por grupo si se selecciona grupo primero)
    - Select de equipo visitante (no puede ser igual al local)
    - Select de grupo (opcional)
    - DateTime picker para fecha/hora
    - Campo venue (opcional)
  - Editar partido (solo si no está completado, o admin siempre puede)
  - Eliminar partido (solo si no tiene predicciones)
- [x] 6.2 Validación: equipos requeridos, no pueden ser iguales, fecha requerida (puede ser pasada para registrar partidos ya jugados en carga inicial)

### Fase 7: Configuración de puntuación

- [x] 7.1 Crear SFC `resources/views/pages/quinielas/scoring/⚡index.blade.php`:
  - Tres campos numéricos: puntos por marcador exacto, puntos por ganador/empate, puntos por goles de un equipo
  - Campo textarea para instrucciones/base de competencia (texto libre)
  - Botón guardar
  - Preview en tiempo real de lo que verán los participantes
- [x] 7.2 Validación: valores numéricos >= 0, máx 100

### Fase 8: Testing

- [x] 8.1 Feature test: usuario autenticado crea quiniela y es redirigido al panel
- [x] 8.2 Feature test: usuario no owner no puede acceder al panel de administración
- [x] 8.3 Feature test: crear equipos, grupos, partidos y verificar que persisten
- [x] 8.4 Feature test: asignar equipos a grupos y verificar relación
- [x] 8.5 Feature test: configurar scoring rules y verificar defaults
- [x] 8.6 Feature test: código de quiniela es único y se genera automáticamente
- [x] 8.7 Ejecutar suite completa → todo verde

---

## Fuera de alcance

- Inscripción de participantes (plan `user-predictions.md`)
- Registro de resultados de partidos (plan `results-scoring.md`)
- Visualización de tabla de posiciones (plan `dashboard-views.md`)
- Drag & drop avanzado para asignación de equipos a grupos (MVP con selects)
- Notificaciones por email o externas al compartir código

---

## Decisiones tomadas

- **Código de quiniela autogenerado**: 8 caracteres alfanuméricos (mayúsculas + números, sin caracteres confusos como 0/O, 1/I/L). Se genera en el servidor al crear la quiniela, sin input del usuario.
- **Carga masiva de equipos**: textarea simple (un nombre por línea). Sin CSV/Excel en esta versión. Botón "Agregar todos" que crea en lote.
- **Grupos opcionales**: no toda quiniela necesita grupos (ej. formato de liga). Si no se crean grupos, los partidos se muestran sin agrupar.
- **Fechas de partido pasadas**: el admin puede registrar partidos con fecha pasada para carga inicial de datos históricos. La validación de "no se puede predecir un partido ya iniciado" es para los participantes, no para el admin creando la quiniela.
- **Eliminación de equipos con partidos**: bloqueada por integridad referencial. Se muestra mensaje: "No se puede eliminar: el equipo tiene partidos registrados."
- **Sub-navegación en panel**: `<flux:navbar>` con `flux:navbar.item` (no existe `<flux:tabs>` en Flux v2). Se usa `wire:click` para cambiar tabs en la propiedad `$tab`.
- **Rutas con Route::livewire() y namespace**: se usa `Route::livewire('/', 'pages::quinielas.index')` porque los namespaces de Livewire están registrados como `pages => resources/views/pages`. El doble dos puntos (`::`) es necesario para que Livewire resuelva el namespace.
- **@volt SFC sin render()**: en sub-componentes se elimina `render()` y se usan `#[Computed]` para pasar datos a la vista. El HTML después de `?>` ES la vista del componente.
