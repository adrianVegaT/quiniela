# Plan: Modelo de datos (Data Layer)

## Contexto

Se va a construir la capa de datos completa del sistema de quinielas: migraciones, modelos Eloquent, factories y seeders para todas las entidades de dominio. Esta es la fundación sobre la que se construirá el resto de funcionalidades.

**Estado inicial**: el proyecto es una instalación fresca de Laravel 13 con Fortify. Solo existen las tablas de scaffolding (`users`, `sessions`, `cache`, `jobs`, `passkeys`) y el modelo `User`. No hay ninguna entidad de dominio.

**Lo que se construye**: 8 entidades de dominio distribuidas en 7 migraciones (Team + Group comparten migración por su simplicidad). Se crearán modelos, factories y seeders para datos de prueba.

---

## Arquitectura

### Diagrama de entidades y relaciones

```
Quiniela ──hasMany──> Team ──belongsToMany──> Group
    │                     (team_group pivot)
    ├──hasMany──> Group
    ├──hasMany──> Match ──belongsTo──> Team (home/away)
    │                  └──belongsTo──> Group (nullable)
    ├──hasOne───> ScoringRule
    └──belongsToMany──> User (quiniela_user pivot, withPivot: joined_at, deleted_at)
         │
         └──hasMany──> Prediction ──belongsTo──> Match
                               └──hasMany──> PredictionLog
```

### Tablas

| Tabla | Columnas clave | Notas |
|-------|---------------|-------|
| `quinielas` | id, name, description (nullable), code (unique, 8 chars), owner_id FK users, status (active/closed/historical), prediction_edit_deadline (nullable datetime), timestamps, softDeletes | Un usuario puede crear muchas quinielas |
| `teams` | id, quiniela_id FK, name, flag_url (nullable), timestamps | Pertenece a una quiniela |
| `groups` | id, quiniela_id FK, name, timestamps | Ej: "Grupo A" |
| `team_group` | team_id FK, group_id FK | Pivote muchos-a-muchos |
| `matches` | id, quiniela_id FK, home_team_id FK, away_team_id FK, group_id FK nullable, phase (nullable), round (nullable), match_date (datetime), venue (nullable), home_score (nullable integer), away_score (nullable integer), is_completed (boolean default false), timestamps | phase/round para futuro soporte de eliminatorias |
| `scoring_rules` | id, quiniela_id FK unique, points_exact_score (integer default 0), points_winner_draw (integer default 0), points_one_team_goals (integer default 0), instructions (text nullable), timestamps | Una regla por quiniela |
| `predictions` | id, match_id FK, user_id FK, home_score (integer nullable), away_score (integer nullable), is_partial (boolean default false), created_at, updated_at | Unique: match_id + user_id |
| `prediction_logs` | id, prediction_id FK, match_id FK, user_id FK (dueño de la predicción), changed_by_user_id FK, old_home_score (integer nullable), old_away_score (integer nullable), new_home_score (integer nullable), new_away_score (integer nullable), action (enum: created/updated/admin_edit), reason (text nullable), created_at | Solo crece, nunca se modifica ni borra |
| `quiniela_user` | id, quiniela_id FK, user_id FK, joined_at (datetime), deleted_at (nullable datetime) | Pivote con soft-delete manual |

### Restricciones

- `quinielas.code` unique index (8 caracteres alfanuméricos, case-insensitive)
- `predictions` unique composite key: `(match_id, user_id)`
- `scoring_rules` unique on `quiniela_id` (one-to-one)
- `matches` check: `home_team_id != away_team_id`
- Los marcadores (`home_score`, `away_score`) aceptan NULL = "aún no definido"

### Convenciones de modelos

- Todos los modelos en `app/Models/` con namespace `App\Models`
- PHPDoc de relaciones con tipos de colección (`Collection<int, Team>`)
- Fillable en cada modelo con los campos asignables masivamente
- Casts nativos de Laravel donde aplique
- SoftDeletes en `Quiniela` únicamente
- Nombres de tabla en español (`quinielas`, `equipos`... no). Se usa inglés para seguir convenciones Laravel.

---

## Archivos a crear

| Archivo | Descripción |
|---------|-------------|
| `database/migrations/xxx_create_quinielas_table.php` | Tabla principal de quinielas |
| `database/migrations/xxx_create_teams_and_groups_tables.php` | Equipos, grupos y pivote team_group |
| `database/migrations/xxx_create_matches_table.php` | Partidos |
| `database/migrations/xxx_create_scoring_rules_table.php` | Reglas de puntuación |
| `database/migrations/xxx_create_predictions_table.php` | Predicciones de usuarios |
| `database/migrations/xxx_create_prediction_logs_table.php` | Auditoría de predicciones |
| `database/migrations/xxx_create_quiniela_user_table.php` | Pivote participantes |
| `app/Models/Quiniela.php` | Modelo principal |
| `app/Models/Team.php` | Modelo equipo |
| `app/Models/Group.php` | Modelo grupo |
| `app/Models/Match.php` | Modelo partido (MatchMah as alias para palabra reservada) |
| `app/Models/ScoringRule.php` | Modelo reglas |
| `app/Models/Prediction.php` | Modelo predicción |
| `app/Models/PredictionLog.php` | Modelo log |
| `database/factories/` | Una factory por cada modelo |
| `database/seeders/DatabaseSeeder.php` | Actualizar con seeders de dominio |

---

## Fases con tareas atómicas

### Fase 1: Migraciones

- [x] 1.1 Crear migración `create_quinielas_table` (id, name, description, code unique, owner_id FK, status enum, prediction_edit_deadline nullable, timestamps, softDeletes)
- [x] 1.2 Crear migración `create_teams_and_groups_tables` (teams: id, quiniela_id FK, name, flag_url nullable, timestamps) + (groups: id, quiniela_id FK, name, timestamps) + tabla pivote `team_group` (team_id FK, group_id FK)
- [x] 1.3 Crear migración `create_matches_table` (id, quiniela_id FK, home_team_id FK, away_team_id FK, group_id FK nullable, phase nullable, round nullable, match_date, venue nullable, home_score nullable, away_score nullable, is_completed default false, timestamps)
- [x] 1.4 Crear migración `create_scoring_rules_table` (id, quiniela_id FK unique, points_exact_score default 0, points_winner_draw default 0, points_one_team_goals default 0, instructions text nullable, timestamps)
- [x] 1.5 Crear migración `create_predictions_table` (id, match_id FK, user_id FK, home_score nullable, away_score nullable, is_partial default false, timestamps) — unique composite (match_id, user_id)
- [x] 1.6 Crear migración `create_prediction_logs_table` (id, prediction_id FK, match_id FK, user_id FK, changed_by_user_id FK, old_home_score nullable, old_away_score nullable, new_home_score nullable, new_away_score nullable, action enum created/updated/admin_edit, reason text nullable, created_at)
- [x] 1.7 Crear migración `create_quiniela_user_table` (id, quiniela_id FK, user_id FK, joined_at, deleted_at nullable) — unique composite (quiniela_id, user_id)
- [x] 1.8 Ejecutar `php artisan migrate:fresh` y verificar que todas las tablas se crean correctamente

### Fase 2: Modelos

- [x] 2.1 Crear modelo `Quiniela` con relaciones: `owner()` BelongsTo User, `teams()` HasMany, `groups()` HasMany, `matches()` HasMany, `scoringRule()` HasOne, `participants()` BelongsToMany User con pivot
- [x] 2.2 Crear modelo `Team` con relaciones: `quiniela()` BelongsTo, `groups()` BelongsToMany, `homeMatches()` HasMany, `awayMatches()` HasMany
- [x] 2.3 Crear modelo `Group` con relaciones: `quiniela()` BelongsTo, `teams()` BelongsToMany, `matches()` HasMany
- [x] 2.4 Crear modelo `Match` (con alias MatchModel o similar, o usar nombre Match y blind value en match_date para evitar conflicto con palabra reservada PHP 8) — relaciones: `quiniela()`, `homeTeam()`, `awayTeam()`, `group()`, `predictions()`
- [x] 2.5 Crear modelo `ScoringRule` con relación `quiniela()` BelongsTo
- [x] 2.6 Crear modelo `Prediction` con relaciones: `match()` BelongsTo, `user()` BelongsTo, `logs()` HasMany
- [x] 2.7 Crear modelo `PredictionLog` con relaciones: `prediction()` BelongsTo, `user()` BelongsTo, `changedBy()` BelongsTo User
- [x] 2.8 Actualizar modelo `User` con relaciones: `ownedQuinielas()` HasMany, `quinielas()` BelongsToMany, `predictions()` HasMany, `predictionLogs()` HasMany, `changedPredictions()` HasMany PredictionLog

### Fase 3: Factories y Seeders

- [x] 3.1 Crear `QuinielaFactory`: estado `active()`, `closed()`, `historical()`; código autogenerado de 8 chars
- [x] 3.2 Crear `TeamFactory`: nombre de equipo fútbol aleatorio
- [x] 3.3 Crear `GroupFactory`: nombres "Grupo A".."Grupo H"
- [x] 3.4 Crear `MatchFactory`: con estados `pending()`, `completed()`; usa TeamFactory para local/visitante
- [x] 3.5 Crear `ScoringRuleFactory`: valores por defecto (3, 1, 1)
- [x] 3.6 Crear `PredictionFactory` y `PredictionLogFactory`
- [x] 3.7 Crear seeder `QuinielaSeeder` que genere una quiniela completa de prueba (8 grupos, 32 equipos estilo mundial, partidos de grupo, 5 participantes con predicciones, algunos partidos con resultados)
- [x] 3.8 Actualizar `DatabaseSeeder` para llamar a `QuinielaSeeder`
- [x] 3.9 Ejecutar `php artisan migrate:fresh --seed` y verificar datos de prueba

### Fase 4: Testing de capa de datos

- [x] 4.1 Test: `Quiniela` se crea correctamente con factory, las relaciones funcionan
- [x] 4.2 Test: `Team` pertenece a `Quiniela` y puede asociarse a `Group`
- [x] 4.3 Test: `Match` con equipos local/visitante, relación a grupo es nullable
- [x] 4.4 Test: `ScoringRule` es única por quiniela (unique constraint)
- [x] 4.5 Test: `Prediction` tiene unique composite key (match_id, user_id)
- [x] 4.6 Test: `PredictionLog` registra acción correctamente (created, updated, admin_edit)
- [x] 4.7 Test: `quiniela_user` soporta soft-delete manual (deleted_at con filtro whereNull)
- [x] 4.8 Test: soft delete de `Quiniela` funciona (withTrashed recupera)
- [x] 4.9 Ejecutar suite completa `php artisan test --compact` → todo verde

---

## Fuera de alcance

- Lógica de puntuación y cálculo (va en plan `results-scoring.md`)
- UI y formularios (planes de UI correspondientes)
- Validación de reglas de negocio (deadline, privacidad) más allá de constraints de BD
- Seeders de usuarios (ya existe `UserFactory`)
- Passkey/2FA (ya implementado por Fortify)

---

## Decisiones tomadas

- **Nombres de tabla en inglés**: `quinielas`, `teams`, `groups`, `matches`, etc. Consistencia con convenciones Laravel.
- **`Match` → `Fixture`**: el nombre `Match` como clase produjo error de sintaxis en `Match::class` porque PHP 8 interpreta `match` como keyword. Se renombró a `Fixture` (término futbolístico en inglés). La tabla en BD se mantiene `matches` ($table = 'matches' en el modelo).
- **Unique composite en predictions**: `(match_id, user_id)` garantiza que un usuario solo tiene una predicción por partido. Al editar, se actualiza el registro existente.
- **Pivote `quiniela_user` sin SoftDeletes trait**: se maneja manualmente con columna `deleted_at` y scope local para evitar cargar el trait en una tabla pivote que no es modelo completo.
- **`phase` y `round` en matches**: columnas nullable preparadas para fases eliminatorias futuras (octavos, cuartos, etc.). En esta versión solo se usa `group_id`.
- **`is_completed` en match**: flag booleano explícito para determinar si un partido tiene resultado final. Evita ambigüedad con `home_score IS NOT NULL`.
- **`instructions` en scoring_rules**: campo de texto libre para que el admin publique bases de competencia o reglas adicionales visibles a los participantes.
