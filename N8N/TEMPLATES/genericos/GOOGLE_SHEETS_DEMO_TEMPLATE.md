# 📊 Plantilla Google Sheets - WhatsApp Bot Demo

## 🔗 Crear Google Sheet

1. Ir a [Google Sheets](https://sheets.google.com)
2. Crear nuevo documento: "WhatsApp Bot Demo"
3. Crear las 4 hojas siguientes

---

## 📋 Hoja 1: Servicios

| id | nombre | duracion_min | precio | descripcion |
|----|--------|--------------|--------|-------------|
| 1 | Alisado Corto | 120 | 45000 | Tratamiento alisado cabello corto |
| 2 | Alisado Medio | 120 | 55000 | Tratamiento alisado cabello medio |
| 3 | Alisado Largo | 120 | 65000 | Tratamiento alisado cabello largo |
| 4 | Botox Corto | 120 | 35000 | Tratamiento botox cabello corto |
| 5 | Botox Medio | 120 | 45000 | Tratamiento botox cabello medio |
| 6 | Botox Largo | 120 | 55000 | Tratamiento botox cabello largo |
| 7 | Corte Bordado | 120 | 15000 | Corte bordado todo largo |
| 8 | Corte Bordado + Hidratación | 120 | 25000 | Corte bordado con hidratación |
| 9 | Corte Bordado + Nutrición | 120 | 25000 | Corte bordado con nutrición |
| 10 | Corte Bordado + Restauración | 120 | 25000 | Corte bordado con restauración |
| 11 | Corte Bordado + Nanotecnología | 120 | 30000 | Corte bordado con nanotecnología |
| 12 | Corte Puntas | 120 | 5000 | Corte de puntas |
| 13 | Abundancia | 120 | 5000 | Tratamiento de abundancia |
| 14 | Alisado Corto + Corte Bordado | 120 | 55000 | Combo alisado corto con corte |
| 15 | Alisado Medio + Corte Bordado | 120 | 65000 | Combo alisado medio con corte |
| 16 | Alisado Largo + Corte Bordado | 120 | 75000 | Combo alisado largo con corte |
| 17 | Botox Corto + Corte Bordado | 120 | 45000 | Combo botox corto con corte |
| 18 | Botox Medio + Corte Bordado | 120 | 55000 | Combo botox medio con corte |
| 19 | Botox Largo + Corte Bordado | 120 | 65000 | Combo botox largo con corte |
| 20 | Masaje Capilar + Secado | 120 | 25000 | Masaje con secado, planchado u ondas |
| 21 | Alisado Corto + Hidrat. + Corte | 120 | 65000 | Combo completo alisado corto |
| 22 | Alisado Medio + Hidrat. + Corte | 120 | 75000 | Combo completo alisado medio |
| 23 | Alisado Largo + Hidrat. + Corte | 120 | 85000 | Combo completo alisado largo |

---

## ⚙️ Hoja 2: Configuracion

| parametro | valor |
|-----------|-------|
| horario_inicio | 09:00 |
| horario_fin | 18:00 |
| dias_habiles | 1,2,3,4,5 |
| intervalo_slots | 30 |
| buffer_entre_citas | 10 |
| moneda_codigo | CLP |
| moneda_simbolo | $ |
| moneda_nombre | Pesos Chilenos |

**Nota sobre días hábiles:**
- 0 = Domingo
- 1 = Lunes
- 2 = Martes
- 3 = Miércoles
- 4 = Jueves
- 5 = Viernes
- 6 = Sábado

---

## 🚫 Hoja 3: Bloqueos

| fecha | hora_inicio | hora_fin | motivo | recurrente |
|-------|-------------|----------|--------|------------|
| * | 13:00 | 14:00 | Almuerzo | diario |
| 2026-01-01 | 09:00 | 18:00 | Año Nuevo | anual |
| 2026-05-01 | 09:00 | 18:00 | Día del Trabajo | anual |
| 2026-12-25 | 09:00 | 18:00 | Navidad | anual |

**Tipos de recurrencia:**
- `no` - Bloqueo único (solo esa fecha específica)
- `diario` - Se repite todos los días (usar `*` en fecha)
- `anual` - Se repite cada año en la misma fecha

---

## 📅 Hoja 4: Citas

| id | nombre | telefono | email | fecha | hora | hora_fin | servicio | estado | created_at |
|----|--------|----------|-------|-------|------|----------|----------|--------|------------|
| | | | | | | | | | |

**Estados posibles:**
- `confirmado` - Cita activa
- `cancelado` - Cita cancelada por el cliente
- `completado` - Cita atendida
- `no_show` - Cliente no asistió

---

## 🔧 Configuración en n8n

### Obtener SPREADSHEET_ID

1. Abrir tu Google Sheet
2. Copiar el ID de la URL:
   ```
   https://docs.google.com/spreadsheets/d/[SPREADSHEET_ID]/edit
   ```
3. Reemplazar `{{SPREADSHEET_ID}}` en el workflow

### Permisos necesarios

El correo de la cuenta de servicio de Google necesita acceso de **Editor** al documento.

---

## 📱 Variables del Workflow

| Variable | Descripción | Ejemplo |
|----------|-------------|---------|
| `{{SPREADSHEET_ID}}` | ID del Google Sheet | `1BxiM...xyz` |
| `{{BUSINESS_NAME}}` | Nombre de tu negocio | `Barbería Don Pedro` |

---

## ✅ Checklist de Configuración

- [ ] Google Sheet creado con las 4 hojas
- [ ] Servicios agregados con precios
- [ ] Configuración de horarios ajustada
- [ ] Bloqueos de feriados agregados
- [ ] Permisos de acceso configurados
- [ ] SPREADSHEET_ID copiado
- [ ] Variables reemplazadas en n8n
