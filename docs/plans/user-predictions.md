# Plan: Inscripción de usuarios y predicciones

## Contexto

Los participantes deben poder unirse a una quiniela mediante un código, llenar sus predicciones para los partidos, y consultar las reglas de puntuación. También deben ver el estado de su progreso (predicciones completadas vs pendientes). La privacidad es clave: no pueden ver predicciones de otros hasta que venza el plazo de edición.

**Precondiciones**: planes `data-model.md` y `quiniela-management.md` completados. Deben existir quinielas, equipos y partidos en la base de datos.

---

## Arquitectura

### Flujo del participante
1. Llega a la pantalla principal → ve un formulario "Unirse a una quiniela"
2. Ingresa el código → se inscribe en la quiniela
3. Ve la lista de partidos agrupados por grupo (o cronológicamente si no hay grupos)
4. Llena marcadores para cada partido
5. Puede guardar parcialmente (predicciones incompletas)
6. Puede editar predicciones hasta que:
   - La fecha global de edición expire, O
   - El partido individual ya haya iniciado (match_date <= now)
7. Ve las instrucciones y reglas de puntuación del admin
8. Ve sus propias predicciones y el log de cambios (transparencia)
9. No ve predicciones de otros participantes hasta que venza el deadline global

### Componentes Livewire SFC

Pantallas en `resources/views/pages/quinielas/` con subcarpeta `predictions/`.

### Rutas

- `POST /quinielas/join` → unirse mediante código
- `GET /quinielas/{quiniela}/predictions` → ver y llenar predicciones
- `GET /quinielas/{quiniela}/rules` → ver reglas de puntuación e instrucciones

### Reglas de negocio

| Regla | Implementación |
|-------|---------------|
| Solo se puede unir una vez por quiniela | Unique constraint `(quiniela_id, user_id)` en `quiniela_user` |
| Código debe existir y quiniela activa | Validación en backend |
| No editar predicción de partido ya iniciado | Validación: `match.match_date > now()` |
| No editar predicción después del deadline global | Validación: `quiniela.prediction_edit_deadline > now()` (si no es null) |
| Guardado parcial permitido | `is_partial = true` en Prediction; banner en UI si hay pendientes |
| Admin siempre puede editar | Verificación de rol: si es owner, ignora restricciones de deadline |
| Predicciones ajenas ocultas hasta deadline | Scope en query: solo carga predicciones propias si `now() < deadline` |

---

## Archivos a crear o modificar

| Archivo | Descripción |
|---------|-------------|
| `routes/web.php` | Agregar rutas de join y predictions |
| `app/Policies/PredictionPolicy.php` | Política de autorización |
| `resources/views/pages/quinielas/join/⚡form.blade.php` | Formulario para unirse por código |
| `resources/views/pages/quinielas/predictions/⚡index.blade.php` | Formulario de predicciones |
| `resources/views/pages/quinielas/rules/⚡index.blade.php` | Vista de reglas e instrucciones |
| `app/Services/PredictionService.php` | Lógica de guardado y validación de predicciones |
| `app/Actions/JoinQuiniela.php` | Acción de inscripción (invocable) |

---

## Fases con tareas atómicas

### Fase 1: Inscripción a una quiniela

- [x] 1.1 Crear ruta `POST /quinielas/join` (auth + verified)
- [x] 1.2 Crear acción `app/Actions/JoinQuiniela.php`: recibe código, busca quiniela activa, valida que no esté ya inscrito, crea registro en `quiniela_user` con `joined_at = now()`
- [x] 1.3 Crear SFC `resources/views/pages/quinielas/join/⚡form.blade.php`: input para código + botón "Unirse"
  - Si el código no existe: mensaje de error "Código no válido"
  - Si ya está inscrito: mensaje "Ya estás participando en esta quiniela" + link
  - Si éxito: redirige a la pantalla de predicciones de esa quiniela
- [x] 1.4 Validación: código requerido, 8 caracteres, case-insensitive al buscar; la quiniela debe tener status = 'active'

### Fase 2: Pantalla de predicciones (llenado)

- [x] 2.1 Crear `PredictionService` con métodos:
  - `savePrediction(Match $match, User $user, ?int $homeScore, ?int $awayScore): Prediction`
  - `canEdit(Quiniela $quiniela, Match $match, User $user): bool`
  - `getProgress(Quiniela $quiniela, User $user): array` (completadas/totales)
- [x] 2.2 Crear ruta `GET /quinielas/{quiniela}/predictions`
- [x] 2.3 Crear `PredictionPolicy`: `view` (si es owner o deadline vencido), `create/update` (si deadline no vencido y partido no iniciado, o si es owner)
- [x] 2.4 Crear SFC `resources/views/pages/quinielas/predictions/⚡index.blade.php`:
  - Header: nombre de la quiniela, deadline (countdown si está configurado), progreso (X/Y partidos completados)
  - Partidos agrupados por grupo (si la quiniela tiene grupos), ordenados por fecha dentro de cada grupo
  - Cada partido muestra: fecha/hora, equipo local vs equipo visitante, inputs para goles local y visitante (numéricos, min=0)
  - Si el partido ya inició: inputs deshabilitados con mensaje "Partido en curso"
  - Si el deadline global expiró: todos los inputs deshabilitados
  - Botón "Guardar" que guarda todas las predicciones (incluso parciales)
  - Indicador visual de predicciones guardadas vs pendientes (badge en cada partido)
- [x] 2.5 El guardado debe:
  - Validar que los campos tengan valores numéricos >= 0 o estén vacíos (parcial)
  - Crear o actualizar la Prediction (upsert por match_id + user_id)
  - Registrar PredictionLog con acción 'created' o 'updated'
  - Marcar `is_partial = true` si hay algún partido sin predecir
  - Mostrar toast de confirmación con `<flux:toast>`

### Fase 3: Visualización de reglas de puntuación

- [x] 3.1 Crear ruta `GET /quinielas/{quiniela}/rules`
- [x] 3.2 Crear SFC `resources/views/pages/quinielas/rules/⚡index.blade.php`:
  - Mostrar las 3 categorías de puntuación con sus valores
  - Mostrar ejemplos visuales de cada tipo de acierto
  - Mostrar el texto de instrucciones del admin (desde `scoring_rules.instructions`)
  - Link para volver a predicciones

### Fase 4: Privacidad de predicciones ajenas

- [x] 4.1 Implementar helper `canViewOtherPredictions(Quiniela $quiniela): bool`:
  - Retorna true si el usuario es owner de la quiniela
  - Retorna true si `now() > quiniela.prediction_edit_deadline` (si deadline no es null)
  - Retorna false en cualquier otro caso
- [x] 4.2 En el SFC de predicciones, si el deadline no ha expirado y no es admin, mostrar solo las predicciones propias
- [x] 4.3 En las pantallas de tabla general (plan `dashboard-views.md`), aplicar el mismo filtro

### Fase 5: Vista de predicciones propias y log del usuario

- [x] 5.1 En la pantalla de predicciones, agregar una sección "Mi historial de cambios" con el log de PredictionLog para las predicciones del usuario
- [x] 5.2 Mostrar: partido, marcador anterior → marcador nuevo, quién hizo el cambio (usuario o admin), fecha y motivo (si es admin_edit)

### Fase 6: Testing

- [x] 6.1 Feature test: usuario se une a quiniela con código válido → aparece en `quiniela_user`
- [x] 6.2 Feature test: usuario intenta unirse con código inválido → error
- [x] 6.3 Feature test: usuario intenta unirse dos veces → error
- [x] 6.4 Feature test: usuario guarda predicciones y se registran correctamente
- [x] 6.5 Feature test: usuario edita predicción y se crea PredictionLog con acción 'updated'
- [x] 6.6 Feature test: usuario no puede editar predicción de partido ya iniciado (match_date < now)
- [x] 6.7 Feature test: usuario no puede ver predicciones de otros antes del deadline
- [x] 6.8 Feature test: admin siempre puede ver todas las predicciones
- [x] 6.9 Feature test: guardado parcial: si faltan partidos, `is_partial = true`
- [x] 6.10 Ejecutar suite completa → todo verde

---

## Fuera de alcance

- Tabla de posiciones y rankings (plan `results-scoring.md` y `dashboard-views.md`)
- Edición de predicciones por el admin (plan `audit-pdf-close.md`)
- Impresión PDF de boletas (plan `audit-pdf-close.md`)
- Notificaciones de deadline próximo

---

## Decisiones tomadas

- **Upsert en predicciones**: usar `updateOrCreate` con clave `(match_id, user_id)`. Si ya existe, se actualiza y se loguea el cambio.
- **PredictionLog en cada cambio**: se registra tanto la creación inicial (acción `created`) como cada edición (`updated` por el usuario, `admin_edit` por el admin). El campo `changed_by_user_id` identifica quién hizo el cambio.
- **Guardado parcial**: el usuario puede guardar aunque no haya llenado todos los partidos. La UI muestra un banner "Te faltan X partidos por predecir" y el campo `is_partial` se mantiene true hasta que todos los partidos tengan predicción.
- **Validación de deadline en tiempo real**: al cargar el formulario y al guardar, se verifica servidor-side que el deadline no haya expirado y que el partido no haya iniciado. El frontend deshabilita inputs como ayuda visual, pero la validación real es en backend.
- **Conteo de progreso**: no se usa `is_partial` para el conteo; se calcula en tiempo real como `count(partidos_con_prediccion) / count(total_partidos)`. `is_partial` es solo un flag de conveniencia.
