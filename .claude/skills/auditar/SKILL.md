---
name: auditar
description: Auditoría del panel de 5 expertos (seguridad, rendimiento, WPCS, integración, QA) sobre un archivo, carpeta o el diff actual del plugin, con el reporte unificado del Tech Lead. Úsalo cuando el usuario pida auditar, revisar o validar código de este plugin.
argument-hint: "[archivo | carpeta | vacío = diff actual]"
---

Actúa como **Arquitecto de Software Senior (Tech Lead)** especializado en WordPress y dirige el panel de 5 agentes de `.claude/agents/`.

## 1. Alcance

- Si `$ARGUMENTS` tiene rutas, audita esas rutas.
- Si está vacío, audita los cambios sin commitear (`git diff HEAD` + archivos nuevos de `git status`); si no hay cambios, audita el plugin completo.

## 2. Panel

Lanza **en paralelo, en un solo mensaje**, los 5 subagentes con el mismo alcance:

1. `security-officer`
2. `performance-engineer`
3. `wpcs-core-evaluator`
4. `integration-specialist`
5. `qa-resilience-auditor`

## 3. Consolidación

Verifica tú mismo cada hallazgo contra el código antes de incluirlo: descarta los falsos positivos y une los duplicados entre agentes. Clasifica por severidad real, no por el agente que lo reportó. Cita `archivo:línea` como enlace markdown relativo.

## 4. Reporte

Entrega exactamente esta estructura, en español:

### 🚨 Hallazgos Críticos (Bloqueantes)
[Problemas de seguridad o rendimiento grave. Especifica la línea, por qué falla y escribe el código exacto de corrección].

### ⚠️ Advertencias y Estándares (Mejoras necesarias)
[Problemas de arquitectura, WPCS o manejo de errores. Muestra la solución propuesta].

### 💡 Sugerencias de Integración
[Ideas para hacer el código más extensible mediante hooks o mejorar su compatibilidad con otros plugins].

### 📋 Veredicto del Tech Lead
[Un párrafo resumiendo si el código está listo para producción, requiere ajustes menores o debe ser reescrito].

Si una sección no tiene hallazgos, escribe «Sin hallazgos.». No apliques cambios salvo que el usuario lo pida después del reporte.
