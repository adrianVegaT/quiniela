# Plan: Auditoría, PDF, edición admin y cierre de quiniela

## Contexto

Funcionalidades transversales de administración y transparencia:
1. **Log de actividades**: los participantes y el admin pueden ver el historial de cambios en predicciones.
2. **Edición de predicciones por el admin**: solo el admin puede editar predicciones de cualquier usuario, con registro en el log y motivo.
3. **Impresión de boletas en PDF**: el admin puede generar un PDF con las predicciones de un usuario como respaldo oficial.
4. **Cierre de quiniela**: cuando todos los partidos están completados, el admin puede mover la quiniela a estado `historical`.

**Precondiciones**: todos los planes anteriores completados.

---

## Arquitectura

### Log de actividades

El `PredictionLog` ya registra cada cambio. Se necesita:
- Una pantalla que consolide los logs de una quiniela
- Filtros: por usuario, por tipo de acción, por fecha
- Visible para: admin (todos los logs), participantes (logs propios + logs de admin_edit sobre sus predicciones)

### Edición admin de predicciones

- El admin accede a la vista individual de un usuario → botón "Editar" en cada predicción
- Modal o inline form para cambiar marcadores
- Campo obligatorio: motivo del cambio
- Al guardar: se actualiza la Prediction, se crea PredictionLog con acción `admin_edit`, `changed_by_user_id = admin.id`, `reason = motivo`
- El log se muestra en tiempo real al usuario afectado

### PDF de boleta

Se usa `barryvdh/laravel-dompdf` para generar PDF con:
- Encabezado: nombre de la app, nombre de la quiniela, fecha/hora de impresión
- Cuerpo: tabla con todos los partidos y las predicciones del usuario
- Pie: texto "Documento generado por [AppName] — [timestamp] — ID: [quiniela_id]"
- Sin firma digital, solo sello visual

### Cierre de quiniela

Condiciones para mostrar botón "Cerrar quiniela":
- Todos los partidos tienen `is_completed = true`
- El estado actual es `active`

Al cerrar:
- `status` cambia a `closed`
- Aparece botón "Enviar a histórico" → cambia `status` a `historical`
- Las quinielas `closed` se siguen mostrando normalmente
- Las quinielas `historical` aparecen en segundo plano

---

## Archivos a crear o modificar

| Archivo | Descripción |
|---------|-------------|
| `app/Policies/PredictionPolicy.php` | Agregar método `adminEdit` |
| `resources/views/pages/quinielas/log/⚡index.blade.php` | Vista consolidada de log de actividades |
| `resources/views/pages/quinielas/user/⚡edit-prediction.blade.php` | Modal/form para edición admin |
| `app/Services/PdfService.php` | Servicio de generación de PDF |
| `resources/views/pdf/predictions.blade.php` | Plantilla Blade para el PDF |
| `composer.json` | Agregar `barryvdh/laravel-dompdf` |
| `routes/web.php` | Nuevas rutas: log, edit-prediction, pdf, close |

---

## Fases con tareas atómicas

### Fase 1: Log de actividades

- [~] 1.1 Crear ruta `GET /quinielas/{quiniela}/log` → `quinielas.log`
- [~] 1.2 Crear SFC `resources/views/pages/quinielas/log/⚡index.blade.php`:
  - Tabla con columnas: fecha/hora, partido, usuario afectado, quién cambió, acción (creado/editado/admin_edit), marcador anterior → nuevo, motivo
  - Filtros: por usuario (dropdown), por tipo de acción (select), por fecha (range)
  - Si es participante: solo ve sus propios logs + admin_edits sobre sus predicciones
  - Si es admin: ve todos los logs de la quiniela
  - Paginación (pueden ser muchos registros)
  - Orden: más reciente primero
- [~] 1.3 Agregar link "Ver log" en la vista pública de la quiniela y en el panel de admin

### Fase 2: Edición admin de predicciones

- [~] 2.1 Agregar método `adminEdit` en `PredictionPolicy` (solo owner de la quiniela)
- [~] 2.2 Crear ruta `PATCH /quinielas/{quiniela}/predictions/{prediction}/admin-edit` → `quinielas.predictions.admin-edit`
- [~] 2.3 En la vista individual de usuario (`quinielas.user.show`), si el viewer es admin, mostrar botón "Editar" en cada fila de predicción
- [~] 2.4 Crear modal/form (`resources/views/pages/quinielas/user/⚡edit-prediction.blade.php`):
  - Inline o modal con inputs para home_score y away_score
  - Campo `reason` (textarea, requerido, mín 10 caracteres)
  - Mostrar marcador original y marcador nuevo antes de confirmar
  - Al guardar: `PredictionLog` con acción `admin_edit`, `changed_by_user_id`, `reason`
  - Si el partido ya está completado: recalcular puntaje con `ScoringService::scoreMatch()`
- [~] 2.5 Nota: el admin puede editar incluso después del deadline o con partido completado (es admin)

### Fase 3: Generación de PDF

- [~] 3.1 Instalar `barryvdh/laravel-dompdf` via composer
- [~] 3.2 Crear `app/Services/PdfService.php`:
  - `generatePredictionBoleta(User $user, Quiniela $quiniela): BinaryFileResponse`
  - Usa `PDF::loadView('pdf.predictions', compact('user', 'quiniela'))`
  - Configurar papel tamaño A4, orientación portrait
- [~] 3.3 Crear plantilla `resources/views/pdf/predictions.blade.php`:
  - Header: logo/nombre de la app, "Boleta de predicciones", nombre de quiniela
  - Tabla: partido (equipos, fecha), predicción (goles)
  - Solo partidos con predicción del usuario
  - Agrupados por grupo si aplica
  - Footer en cada página: "[AppName] — Generado: [timestamp] — Quiniela ID: [id]"
- [~] 3.4 Crear ruta `GET /quinielas/{quiniela}/users/{user}/pdf` → `quinielas.users.pdf`
- [~] 3.5 Agregar botón "Imprimir boleta" en la vista individual de usuario (admin)
- [~] 3.6 El PDF se descarga al navegador (Content-Disposition: attachment) o se muestra inline

### Fase 4: Cierre y envío a histórico

- [~] 4.1 Crear rutas:
  - `POST /quinielas/{quiniela}/close` → `quinielas.close`
  - `POST /quinielas/{quiniela}/archive` → `quinielas.archive`
- [~] 4.2 En el panel del admin (`quinielas.show`):
  - Mostrar badge "Todos los partidos completados" cuando `count(matches where is_completed=false) === 0`
  - Si todos completados y status = 'active': botón "Cerrar quiniela"
  - Si status = 'closed': botón "Enviar a histórico"
- [~] 4.3 Acción `close`: cambia `status = 'closed'` con confirmación modal ("¿Estás seguro? Los participantes ya no podrán editar predicciones.")
- [~] 4.4 Acción `archive`: cambia `status = 'historical'` con confirmación modal ("¿Enviar al historial? La quiniela se mostrará en segundo plano.")
- [~] 4.5 Validación: solo el owner puede cerrar/archivar; `archive` requiere `status = 'closed'`

### Fase 5: Cancelación de quiniela (edge case)

- [~] 5.1 Crear ruta `DELETE /quinielas/{quiniela}` → `quinielas.destroy`
- [~] 5.2 Validación: solo si no tiene participantes (`quiniela_user` count = 0) y no tiene resultados (`matches where is_completed = true` count = 0)
- [~] 5.3 Soft delete de la quiniela (ya tiene SoftDeletes)
- [~] 5.4 Botón "Eliminar quiniela" visible solo si cumple condiciones, con confirmación modal

### Fase 6: Testing

- [~] 6.1 Feature test: admin edita predicción de otro usuario y se registra log con acción `admin_edit`
- [~] 6.2 Feature test: participante ve logs de admin_edit sobre sus predicciones
- [~] 6.3 Feature test: participante no ve logs de otros usuarios
- [~] 6.4 Feature test: PDF se genera correctamente con predicciones del usuario
- [~] 6.5 Feature test: PDF incluye sello visual (app name + timestamp + quiniela ID)
- [~] 6.6 Feature test: cerrar quiniela requiere todos los partidos completados
- [~] 6.7 Feature test: archivar quiniela requiere status = 'closed'
- [~] 6.8 Feature test: cancelar quiniela falla si tiene participantes o resultados
- [~] 6.9 Feature test: quiniela histórica no aparece en vista principal (solo en histórico)
- [~] 6.10 Ejecutar suite completa → todo verde

---

## Fuera de alcance

- Firma digital criptográfica en PDF
- Envío de PDF por email desde la plataforma
- Exportación masiva de múltiples boletas
- Restauración de quinielas canceladas desde la UI
- Log de cambios en resultados de partidos
- Log de cambios en configuración de puntuación

---

## Decisiones tomadas

- **PDF con laravel-dompdf**: es la librería más usada en Laravel para PDF. Alternativa: `laravel-snappy` (requiere wkhtmltopdf, más pesado). dompdf es PHP puro (sin dependencias de sistema).
- **Sello visual sin criptografía**: se incluye nombre de la app, timestamp y ID de quiniela. Suficiente como respaldo de credibilidad para una quiniela informal. Si se necesita firma digital en el futuro, se puede agregar.
- **Admin siempre puede editar**: incluso después del deadline y con partidos completados. El motivo es obligatorio para transparencia. Si edita un partido ya completado, se recalcula el puntaje automáticamente.
- **Tres estados de quiniela**: `active` (se pueden hacer predicciones), `closed` (todos los partidos completados, no se edita más), `historical` (archivada, segundo plano). El paso intermedio `closed` existe porque el admin puede querer revisar todo antes de archivar definitivamente.
- **Cancelación restrictiva**: solo si no hay participantes ni resultados. Esto evita perder datos accidentalmente. Si una quiniela tiene datos, primero hay que eliminar participantes y resultados manualmente (o no permitirlo).
- **Log filtrable pero no exportable**: el log se ve en la UI con filtros. No se exporta a CSV/PDF en esta versión.
