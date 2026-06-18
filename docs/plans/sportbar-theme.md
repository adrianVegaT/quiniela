# Plan: Tema SportBar — Rediseño visual completo

## Contexto

La apariencia actual usa el tema por defecto de Flux UI con esquema zinc. El usuario quiere aplicar el diseño "SportBar" definido en el prototipo `propuesta_a_sportbar.html`: fondo oscuro (#0a0a0a), acento verde (#10b981), layout responsivo con sidebar fijo en desktop y bottom nav en mobile, tarjetas con bordes sutiles, y una experiencia visual más deportiva y moderna.

**Enfoque**: Tematizar Flux UI sin eliminarlo. Se modifica la capa de presentación (CSS + estructura de layouts + composición de vistas) conservando toda la lógica de negocio, componentes Livewire SFC y tests existentes.

**Precondiciones**: sistema funcional con todos los planes anteriores completados.

---

## Arquitectura

### Estrategia de tematización

1. **CSS-first**: variables CSS en `app.css` para que Flux UI herede los colores del tema SportBar
2. **Layout restructuring**: `sidebar.blade.php` se reestructura para sidebar fijo + bottom nav mobile
3. **View composition**: las vistas existentes ajustan su markup para acercarse al prototipo sin perder los componentes Flux

### Archivos a modificar

| Archivo | Tipo de cambio |
|---------|----------------|
| `resources/css/app.css` | Tema CSS: paleta surface-*, accent, variables Flux |
| `resources/views/layouts/app/sidebar.blade.php` | Reestructuración completa: sidebar + bottom nav |
| `resources/views/layouts/app.blade.php` | Pasar título, wrapper responsive |
| `resources/views/layouts/auth/split.blade.php` | Tema SportBar en panel branding |
| `resources/views/pages/auth/login.blade.php` | Sin cambios estructurales (ya usa split layout) |
| `resources/views/pages/✦dashboard.blade.php` | Grid de cards, stats row, composición |
| `resources/views/pages/quinielas/✦index.blade.php` | Grid multi-columna, estilo de tarjetas |
| `resources/views/pages/quinielas/predictions/✦index.blade.php` | Grid de partidos, header con progreso |
| `resources/views/pages/quinielas/leaderboard/✦index.blade.php` | Podio top-3, estilo de tabla |
| `resources/views/pages/quinielas/✦show-public.blade.php` | Estilo de tabla, composición |

---

## Fases con tareas atómicas

### Fase 1: Tema CSS — Paleta y variables

- [x] 1.1 Leer `resources/css/app.css` actual y entender estructura Tailwind v4
- [x] 1.2 Agregar paleta de colores `surface-*` y `accent` mediante `@theme`
- [x] 1.3 Sobrescribir variables CSS de Flux UI (`--color-zinc-*`, `--color-white`, `--color-accent`) para que componentes Flux hereden el tema
- [x] 1.4 Estilizar body: fondo `#0a0a0a`, tipografía Inter
- [x] 1.5 Compilar con `npm run build` y verificar que no haya errores

### Fase 2: Layout raíz — Sidebar + bottom nav

- [x] 2.1 Reestructurar `sidebar.blade.php`: sidebar fijo en desktop con fondo surface-900, estilizar items con estado activo (accent border-right), sección de perfil abajo
- [x] 2.2 Agregar bottom nav mobile (`lg:hidden`) con 4 tabs (Inicio, Quinielas, Predicciones, Tabla) estilo prototipo
- [x] 2.3 Mantener compatibilidad con `<flux:main>` y el slot de contenido
- [x] 2.4 Ajustar `app.blade.php` para el wrapper responsivo (`max-w-md mx-auto lg:max-w-none lg:ml-64`)

### Fase 3: Auth — Login split-screen

- [x] 3.1 Actualizar `split.blade.php`: panel izquierdo con branding SportBar (logo grande, nombre, stats como en prototipo)
- [x] 3.2 El panel derecho del formulario se mantiene funcional con los inputs Flux actuales

### Fase 4: Vistas principales

- [x] 4.1 Dashboard: convertir cards de acciones a grid visual estilo prototipo (icono, título, descripción) con `lg:grid-cols-4`
- [x] 4.2 Dashboard: agregar stats row en desktop (participantes, partidos, predicciones, posición)
- [x] 4.3 Dashboard: código de invitación en card estilizada
- [x] 4.4 Quinielas index: grid de tarjetas `lg:grid-cols-2 xl:grid-cols-3`
- [x] 4.5 Quinielas index: formulario de unirse con código estilizado
- [x] 4.6 Predictions: header con progreso + límite + botón guardar (barra horizontal)
- [x] 4.7 Predictions: partidos en grid `lg:grid-cols-2` dentro de cada grupo
- [x] 4.8 Predictions: inputs de score estilizados (más compactos, centrados)
- [x] 4.9 Leaderboard: tabla estilizada con badges de posición, highlight de fila del usuario, puntajes en badges verdes
- [x] 4.10 Leaderboard: highlight de fila actual del usuario
- [x] 4.11 Show-public: aplicar mismo estilo de leaderboard y cards

### Fase 5: Testing y verificación

- [x] 5.1 Ejecutar `npm run build` para compilar assets
- [x] 5.2 Ejecutar `php artisan test --compact` — **108/108 tests pass**
- [x] 5.3 Verificar responsive: revisar vistas en mobile (bottom nav presente) y desktop (sidebar presente)
- [x] 5.4 Ejecutar `vendor/bin/pint --format agent` para formatear archivos PHP modificados

---

## Fuera de alcance

- Animaciones y transiciones entre pantallas (SPA-style navigation)
- Gráficos estadísticos
- Modo claro (light mode) — solo tema oscuro
- Internacionalización de nuevos textos (se usan los strings existentes)
- Reemplazar iconos SVG inline del prototipo por Heroicons (se usan los iconos Flux ya integrados)

---

## Decisiones tomadas

- **Path C (híbrido)**: tematizar Flux UI en vez de eliminarlo. Conserva funcionalidad, reduce riesgo, acelera implementación.
- **Sin eliminar Flux components**: todas las vistas mantienen `<flux:card>`, `<flux:button>`, `<flux:input>`, etc. Solo se ajusta composición y se sobrescriben colores vía CSS.
- **Bottom nav mobile existe solo en pantallas con sidebar**: en mobile, la navegación es bottom tabs. En desktop, sidebar lateral. No hay breadcrumbs en mobile para ahorrar espacio vertical.
- **Un solo `<flux:sidebar>`**: mantener estructura Flux nativa (un sidebar sticky con collapsible mobile, un header mobile con toggle). El bottom nav es un `<nav>` independiente, no un segundo sidebar. Tener dos `<flux:sidebar>` simultáneos rompe los hooks JS internos y el posicionamiento sticky.
- **Usar `zinc-*` en vez de `surface-*`**: la paleta zinc ya tiene los mismos valores que surface. Definir surface-* como duplicado crea un sistema de color paralelo innecesario que engorda el CSS y causa inconsistencias con los componentes Flux que internamente usan zinc-*.
- **Sin reglas CSS manuales para variantes de acento**: Tailwind v4 genera automáticamente `bg-accent/10`, `text-accent`, etc. desde `@theme --color-accent`. Reglas manuales con `\/` escapan la barra y crean selectores que compiten con la generación automática.
- **`@tempnam` en vendor**: fix necesario para compatibilidad PHP 8.4. `tempnam()` emite E_WARNING desde PHP 8.4, y Laravel convierte warnings a excepciones.
