# Plan: Dashboard principal y vistas de usuario

## Contexto

La pantalla principal del sistema debe mostrar información consolidada: tabla de posiciones de la quiniela activa del usuario, seguida de los partidos pendientes. También debe haber vistas individuales donde cada participante (y el admin) pueda ver el detalle de predicciones propias contrastadas con resultados reales. Las quinielas históricas deben aparecer en un segundo plano.

**Precondiciones**: planes `data-model.md`, `quiniela-management.md`, `user-predictions.md` y `results-scoring.md` completados.

---

## Arquitectura

### Pantalla principal (Dashboard)

El dashboard existente (`resources/views/dashboard.blade.php`) se convierte a Livewire SFC y se reestructura para mostrar:

1. **Sección: Mis quinielas activas** (primer plano)
   - Si el usuario no está en ninguna quiniela: prompt para unirse ("¿Tienes un código?")
   - Si está en quinielas activas: tabs o cards para cambiar entre ellas
   - Cada quiniela activa muestra:
     - Tabla de posiciones (componente reutilizable `leaderboard-table`)
     - Lista de partidos pendientes (próximos, ordenados por fecha)
   
2. **Sección: Quinielas históricas** (segundo plano, colapsable)
   - Lista de quinielas donde participó y están en `historical`
   - Click → navega a vista histórica de esa quiniela

### Vistas individuales

3. **Vista individual del usuario** (accesible por el propio usuario y por el admin):
   - Para cada partido: predicción del usuario vs resultado real
   - Indicador visual de acierto/fallo (check/cross con colores)
   - Puntaje obtenido por categoría
   - Solo muestra partidos completados (con resultado)

### Navegación

- Sidebar: enlace "Dashboard" (existente) se mantiene como pantalla principal
- Sidebar: enlace "Mis quinielas" → lista de quinielas donde el usuario es owner o participante
- Desde el dashboard, clic en una quiniela → vista detallada de esa quiniela

---

## Archivos a crear o modificar

| Archivo | Descripción |
|---------|-------------|
| `resources/views/dashboard.blade.php` | Convertir a SFC con @volt |
| `resources/views/pages/quinielas/⚡index.blade.php` | Lista de quinielas del usuario (owner + participante) |
| `resources/views/pages/quinielas/⚡show-public.blade.php` | Vista pública de una quiniela (participante) |
| `resources/views/pages/quinielas/leaderboard/⚡index.blade.php` | Tabla de posiciones reutilizable |
| `resources/views/pages/quinielas/user/⚡show.blade.php` | Vista individual de predicciones de un usuario |
| `resources/views/layouts/app/sidebar.blade.php` | Actualizar navegación |

---

## Fases con tareas atómicas

### Fase 1: Listado de quinielas del usuario

- [x] 1.1 Crear ruta `GET /quinielas` → `quinielas.index`
- [x] 1.2 Crear SFC `resources/views/pages/quinielas/⚡index.blade.php`:
  - Dos secciones: "Quinielas activas" y "Quinielas históricas"
  - Cada quiniela muestra: nombre, rol (owner/participante), progreso (partidos completados/totales), botón "Entrar"
  - Si es owner: badge "Admin" y link al panel de administración
  - Si no tiene quinielas activas: estado vacío con formulario para unirse por código
  - Las históricas colapsadas por defecto (collapsible con Alpine)
- [x] 1.3 Actualizar `sidebar.blade.php`: agregar enlace "Mis quinielas" (icon: trophy)

### Fase 2: Vista pública de quiniela (participante)

- [x] 2.1 Crear ruta `GET /quinielas/{quiniela}/view` → `quinielas.public`
- [x] 2.2 Crear SFC `resources/views/pages/quinielas/⚡show-public.blade.php`:
  - Header: nombre de quiniela, deadline, enlace a predicciones, enlace a reglas
  - Si deadline expirado o es admin: tabla de posiciones (componente leaderboard)
  - Si deadline no expirado: mensaje "Las predicciones de otros participantes estarán disponibles después de [fecha]"
  - Lista de partidos con fechas
  - Acceso al log de actividades (prediction_logs recientes)

### Fase 3: Dashboard principal

- [x] 3.1 Convertir `resources/views/dashboard.blade.php` a Livewire SFC (`@volt`)
- [x] 3.2 Implementar lógica del dashboard:
  - Obtener quinielas activas del usuario (owner + participante)
  - Para la primera quiniela activa (o la seleccionada): mostrar leaderboard + próximos partidos
  - Selector/tabs para cambiar entre quinielas activas
  - Sección colapsable "Quinielas históricas"
  - Si no tiene quinielas: mostrar CTA "Unirse a una quiniela" + "Crear quiniela"
- [x] 3.3 Leaderboard embebido: usar componente Livewire hijo o incluir directamente la tabla de posiciones
- [x] 3.4 Lista de próximos partidos:
  - Solo partidos `is_completed = false` de la quiniela activa
  - Ordenados por `match_date` ascendente
  - Mostrar: fecha formateada, equipo local vs visitante, grupo (si aplica)
  - Badge "Hoy", "Mañana" o fecha relativa
- [x] 3.5 Validar responsive: en móvil, leaderboard con scroll horizontal, partidos en lista vertical

### Fase 4: Vista individual de predicciones de un usuario

- [x] 4.1 Crear ruta `GET /quinielas/{quiniela}/users/{user}` → `quinielas.user.show`
- [x] 4.2 Crear SFC `resources/views/pages/quinielas/user/⚡show.blade.php`:
  - Header: nombre del usuario, puntaje total, estadísticas (aciertos exactos/ganador/goles)
  - Tabla: partido | predicción | resultado real | marcador exacto | ganador/empate | goles equipo | puntos
  - Cada fila coloreada según resultado: verde (exacto), amarillo (parcial), gris/rojo (fallo)
  - Solo partidos completados (con resultado)
  - Accesible por: el propio usuario (sus predicciones), el admin (cualquier usuario)
  - Back link: "Volver a tabla de posiciones"
- [x] 4.3 Agregar link desde la tabla de posiciones (clic en nombre de usuario → vista individual)

### Fase 5: Vista histórica de quiniela

- [x] 5.1 Crear ruta `GET /quinielas/{quiniela}/history` → `quinielas.history`
- [x] 5.2 Crear SFC similar a `show-public` pero para quinielas en estado `historical`:
  - Mostrar tabla de posiciones final
  - Mostrar todos los partidos con resultados
  - Sin opciones de edición (solo lectura)
  - Enlace para volver al listado de quinielas

### Fase 6: Navegación y mejoras de UX

- [x] 6.1 Agregar breadcrumbs en todas las pantallas de quiniela
- [x] 6.2 Indicador visual de quinielas activas vs históricas en el listado (badge de estado)
- [x] 6.3 En el sidebar, badge con conteo de quinielas activas
- [x] 6.4 Back navigation consistente: desde vista individual → tabla, desde tabla → dashboard

### Fase 7: Testing

- [x] 7.1 Feature test: dashboard muestra quinielas activas del usuario
- [x] 7.2 Feature test: dashboard muestra CTA cuando no tiene quinielas
- [x] 7.3 Feature test: quinielas históricas están colapsadas por defecto
- [x] 7.4 Feature test: vista individual de usuario muestra predicciones vs resultados reales
- [x] 7.5 Feature test: participante no ve enlace "Admin" en quinielas de otros
- [x] 7.6 Feature test: vista histórica es de solo lectura
- [x] 7.7 Ejecutar suite completa → todo verde

---

## Fuera de alcance

- Gráficos estadísticos (barras, distribución de aciertos)
- Comparación side-by-side de predicciones entre usuarios
- Exportación de tabla de posiciones (CSV/Excel)
- Filtros avanzados en vista individual
- Modo "invitado" para ver quinielas públicas sin registro

---

## Decisiones tomadas

- **Dashboard como SFC único**: el dashboard es un componente Livewire SFC que maneja su propio estado. No se divide en sub-componentes anidados a menos que sea necesario para rendimiento.
- **Una quiniela activa a la vez en el dashboard**: se muestra de a una para no saturar la pantalla. El usuario cambia entre quinielas con tabs. Esto mantiene el diseño limpio y el rendimiento óptimo.
- **Vista individual solo para partidos completados**: no tiene sentido mostrar predicciones para partidos sin resultado en la vista de contraste. Los partidos pendientes se ven en la pantalla de predicciones.
- **Tabla de posiciones como partial reutilizable**: se implementa como un partial Blade (`@include`) o un componente Livewire hijo para evitar duplicar código entre el dashboard del participante, el dashboard del admin y la vista pública.
- **Históricas en segundo plano**: diseño visual que las distingue (menor opacidad, sección colapsable por defecto, fondo ligeramente más oscuro).
