# Plan: Registro de resultados y motor de puntuación

## Contexto

El administrador debe poder registrar el resultado real de cada partido a medida que se completan. Al registrar un resultado, el sistema debe calcular automáticamente los puntajes de todos los participantes según las reglas configuradas y actualizar la tabla de posiciones en tiempo real.

**Precondiciones**: planes `data-model.md` y `quiniela-management.md` completados. Deben existir partidos y predicciones.

---

## Arquitectura

### Motor de puntuación

Se implementa como un **Service** puro (sin dependencias HTTP) para poder invocarse tanto desde la UI como desde comandos de consola o tests.

`app/Services/ScoringService.php`:

```
calculatePoints(Prediction $prediction, Match $match, ScoringRule $rules): array
```
Retorna un array con:
- `exact_score` (int): puntos obtenidos
- `winner_draw` (int): puntos obtenidos
- `one_team_goals` (int): puntos obtenidos
- `total` (int): suma de los tres

`recalculateQuiniela(Quiniela $quiniela): void`
Recorre todas las predicciones de partidos completados y recalcula puntajes. Útil para cuando se cambian las reglas de puntuación a mitad de la quiniela o se corrige un resultado.

### Flujo de registro de resultado

1. Admin ve lista de partidos con opción de registrar/editar resultado
2. Ingresa goles de local y visitante
3. Guarda → se marca `is_completed = true`
4. Se dispara el cálculo de puntuación para ese partido (todas las predicciones)
5. La tabla de posiciones se actualiza (en el mismo request o vía Livewire reactivity)

### Tabla de posiciones (leaderboard)

Se calcula on-the-fly agregando los puntajes por predicción, agrupados por usuario. No se persiste en una tabla — se calcula al momento para garantizar consistencia.

Orden:
1. Mayor `total_score` descendente
2. En caso de empate: mayor `exact_score_count` descendente
3. En caso de persistir empate: nombre de usuario alfabético

### Query para tabla de posiciones

```sql
SELECT 
  u.id, u.name,
  COUNT(p.id) as predictions_count,
  COUNT(p.id) FILTER (WHERE m.is_completed) as completed_count,
  COUNT(p.id) FILTER (WHERE exact_score > 0) as exact_score_count,
  COUNT(p.id) FILTER (WHERE winner_draw > 0) as winner_draw_count,
  COUNT(p.id) FILTER (WHERE one_team_goals > 0) as one_team_goals_count,
  COALESCE(SUM(ps.total), 0) as total_score
FROM users u
JOIN quiniela_user qu ON u.id = qu.user_id
JOIN predictions p ON u.id = p.user_id
JOIN matches m ON p.match_id = m.id
LEFT JOIN prediction_scores ps ON p.id = ps.prediction_id
WHERE qu.quiniela_id = ? AND qu.deleted_at IS NULL AND m.is_completed = true
GROUP BY u.id, u.name
ORDER BY total_score DESC, exact_score_count DESC, u.name ASC
```

**Nota**: Si no se quiere tabla intermedia `prediction_scores`, los puntajes pueden calcularse como columnas virtuales en la query o como accessors en el modelo `Prediction`. Para rendimiento en quinielas grandes (>100 participantes, >50 partidos), se recomienda materializar los puntajes en columnas de la tabla `predictions` (`exact_score_points`, `winner_draw_points`, `one_team_goals_points`) que se actualizan al registrar el resultado del partido.

**Decisión**: materializar puntajes en columnas de `predictions`. Ventajas: queries más simples, sin joins adicionales, resultados inmediatos. Desventaja: hay que recalcular si cambian las reglas (edge case poco frecuente).

---

## Archivos a crear o modificar

| Archivo | Descripción |
|---------|-------------|
| `app/Services/ScoringService.php` | Motor de puntuación |
| `database/migrations/xxx_add_score_columns_to_predictions.php` | Agregar `exact_score_points`, `winner_draw_points`, `one_team_goals_points` a predictions |
| `app/Models/Prediction.php` | Actualizar con accessors de puntaje total y columnas nuevas |
| `resources/views/pages/quinielas/matches/⚡results.blade.php` | Pantalla para registrar/editar resultados |
| `resources/views/pages/quinielas/leaderboard/⚡index.blade.php` | Tabla de posiciones |

---

## Fases con tareas atómicas

### Fase 1: Migración de columnas de puntuación

- [x] 1.1 Crear migración: agregar `exact_score_points`, `winner_draw_points`, `one_team_goals_points` (integer, nullable, default null) a tabla `predictions` — null significa "aún no calculado"
- [x] 1.2 Agregar accessor `total_points` en modelo `Prediction` que sume las tres columnas (tratando null como 0)
- [x] 1.3 Ejecutar migración

### Fase 2: ScoringService

- [x] 2.1 Crear `app/Services/ScoringService.php` con:
  - `calculateMatchScore( Prediction $prediction, Match $match, ScoringRule $rules): array`
    - Retorna `{exact_score, winner_draw, one_team_goals}` en puntos reales
  - `scoreMatch(Match $match): void`
    - Recorre todas las predicciones del partido
    - Calcula puntajes con `calculateMatchScore`
    - Actualiza las columnas de puntuación en cada Prediction
  - `recalculateQuiniela(Quiniela $quiniela): void`
    - Recorre todos los partidos completados y llama a `scoreMatch` para cada uno
- [x] 2.2 Lógica de `calculateMatchScore`:
  ```
  exact_score   = (pred.home == match.home && pred.away == match.away) ? rules.points_exact_score : 0
  winner_draw   = (sign(pred) == sign(match)) ? rules.points_winner_draw : 0
  one_team_goals = (!exact_score && (pred.home == match.home || pred.away == match.away)) ? rules.points_one_team_goals : 0
  ```
- [x] 2.3 El método `sign(resultado)` retorna 'home' si home > away, 'draw' si iguales, 'away' si away > home

### Fase 3: Interfaz de registro de resultados

- [x] 3.1 Agregar ruta `GET /quinielas/{quiniela}/matches/results`
- [x] 3.2 Crear SFC `resources/views/pages/quinielas/matches/⚡results.blade.php`:
  - Lista de partidos con: fecha, equipos, inputs para home_score y away_score
  - Partidos sin resultado: inputs habilitados, badge "Pendiente"
  - Partidos con resultado: inputs con valor actual, badge "Completado", botón "Editar" (para correcciones)
  - Guardar dispara `ScoringService::scoreMatch($match)` y actualiza la UI
- [x] 3.3 Validación: home_score y away_score deben ser enteros >= 0, ambos requeridos si se está registrando un resultado
- [x] 3.4 Al guardar un resultado: marcar `match.is_completed = true`, ejecutar scoring, mostrar toast

### Fase 4: Tabla de posiciones (leaderboard)

- [x] 4.1 Agregar ruta `GET /quinielas/{quiniela}/leaderboard`
- [x] 4.2 Crear SFC `resources/views/pages/quinielas/leaderboard/⚡index.blade.php`:
  - Tabla con columnas dinámicas según `ScoringRule`:
    - # (posición), Participante, Partidos jugados/completados, **columnas condicionales** (solo si puntos > 0), Puntaje total
  - Columnas condicionales: 
    - "Marcador exacto" (si `points_exact_score > 0`)
    - "Ganador/Empate" (si `points_winner_draw > 0`) 
    - "Goles de un equipo" (si `points_one_team_goals > 0`)
  - Ordenamiento: total_score DESC, luego exact_score_count DESC
  - Highlight de la fila del usuario actual
  - Colores: primer lugar dorado sutil, segundo plateado, tercero bronce (Flux tokens)
- [x] 4.3 La tabla se actualiza reactivamente al guardar resultados

### Fase 5: Actualizar el panel del admin

- [x] 5.1 Agregar pestaña/link "Resultados" en el panel del admin (quiniela.show)
- [x] 5.2 Agregar pestaña/link "Tabla de posiciones" en el panel del admin

### Fase 6: Testing

- [x] 6.1 Unit test: `calculateMatchScore` con marcador exacto → devuelve puntos correctos
- [x] 6.2 Unit test: `calculateMatchScore` con ganador (sin goles exactos) → puntos de winner_draw
- [x] 6.3 Unit test: `calculateMatchScore` con acierto de goles de un equipo → puntos one_team_goals
- [x] 6.4 Unit test: `calculateMatchScore` con predicción totalmente incorrecta → 0 puntos
- [x] 6.5 Unit test: `calculateMatchScore` no suma one_team_goals si ya es exact_score
- [x] 6.6 Feature test: admin registra resultado y `is_completed = true` + puntajes calculados
- [x] 6.7 Feature test: tabla de posiciones refleja puntajes correctos después de registrar resultados
- [x] 6.8 Feature test: tabla de posiciones oculta columnas con 0 puntos en ScoringRule
- [x] 6.9 Feature test: `recalculateQuiniela` recalcula todos los puntajes correctamente
- [x] 6.10 Ejecutar suite completa → todo verde

---

## Fuera de alcance

- Historial de cambios en resultados de partidos (solo se guarda el resultado final)
- Cálculo de puntuación en tiempo real vía WebSockets (usa Livewire polling si es necesario)
- Notificaciones cuando se registra un resultado

---

## Decisiones tomadas

- **Puntajes materializados**: se guardan en columnas de `predictions` (no se calculan al vuelo en cada consulta). Esto hace las queries de leaderboard más rápidas y simples. Si el admin cambia las reglas de puntuación, debe usar el botón "Recalcular puntajes" que invoca `recalculateQuiniela`.
- **No hay tabla `prediction_scores` separada**: los puntajes van como columnas en `predictions` para mantener el modelo simple (una predicción = un registro con sus puntajes). Si en el futuro se necesita historial de cambios de puntaje, se puede extraer.
- **Resultado parcial de partido no soportado**: un partido está completado o no. No hay soporte para "resultado en vivo" o "parcial".
- **Actualización de leaderboard**: Livewire re-renderiza la tabla al guardar resultados. Sin polling ni WebSockets en esta versión.
- **Corrección de resultados**: el admin puede editar un resultado ya registrado. Al guardar, se recalcula el scoring para ese partido (afecta a todos los participantes). No se registra log de cambios en resultados de partidos (MVP).
