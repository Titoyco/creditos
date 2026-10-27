# Regla General de UI: Ordenamiento Interactivo en Tablas de Resultado (Click-to-Sort)

## Regla Obligatoria
En **todas las tablas de resultado** y listados operativos del sistema (Créditos, Clientes, Recibos, Cobranzas, Catálogo, Stock, etc.), los encabezados de columnas ordenables **deben permitir cambiar el orden de la lista haciendo clic**, alternando entre **ascendente (`asc`)** y **descendente (`desc`)** con cada clic consecutivo.

---

## 1. Comportamiento y Preservación de Estado

1. **Alternancia de Orden (Toggle):**
   - Si la columna clicada es la columna actualmente ordenada:
     - Si está en `asc`, cambia a `desc`.
     - Si está en `desc`, cambia a `asc`.
   - Si se hace clic en una columna diferente, toma esa columna con su dirección inicial (por defecto `asc` o `desc` según convenga al dato).

2. **Preservación Integral de Filtros (Sin Pérdida de Contexto):**
   - El enlace del encabezado **nunca debe perder los filtros activos** en la URL (`search`, `sucursal`, `tipo`, `estado`, rangos de fechas, checkboxes financieros, etc.).
   - Debe usar `request()->query()` al construir la URL de orden:
     ```php
     route('modulo.index', array_merge(request()->query(), [
         'sort' => $columna,
         'direction' => ($sort === $columna && $direction === 'asc') ? 'desc' : 'asc',
         'page' => 1 // Resetear siempre a la primera página para evitar vacíos
     ]))
     ```

3. **Exportaciones e Impresión:**
   - La exportación a Excel / CSV y las vistas de impresión deben respetar exactamente el mismo ordenamiento seleccionado por el operador en pantalla.

---

## 2. Indicadores Visuales y Microinteracciones

1. **Estado Activo:**
   - Si la columna está activa con orden ascendente: mostrar `▲` (o icono `chevron-up`) destacado (ej: `text-blue-600 dark:text-blue-400 font-black ml-1`).
   - Si la columna está activa con orden descendente: mostrar `▼` (o icono `chevron-down`) destacado (ej: `text-blue-600 dark:text-blue-400 font-black ml-1`).

2. **Estado Inactivo (Hover):**
   - Mostrar un indicador tenue o neutro `⇅` (ej: `text-slate-300 dark:text-slate-600 group-hover:text-slate-500 font-normal ml-1`).
   - El texto del encabezado debe tener feedback sutil en hover (`hover:text-blue-600 dark:hover:text-blue-400 transition-colors`).

3. **Alineación de Encabezados:**
   - Columnas a la izquierda (texto/cliente/ID): enlace con `flex justify-between items-center`.
   - Columnas centradas (fecha/cuota/estado): enlace con `flex justify-center items-center space-x-1`.
   - Columnas numéricas / importes a la derecha (totales, saldos, cuota): enlace con `flex justify-end items-center space-x-1`.

---

## 3. Implementación Estándar en Backend (Controladores)

En el controlador del recurso (ej: `CreditoController@index`, `ClienteController@index`):

```php
// Whitelist de columnas seguras contra inyección SQL
$sortableColumns = [
    'fecha'       => 'creditos.fecha_credito',
    'cliente'     => 'clientes.nombre',
    'valor_cuota' => 'creditos.valor_cuota_ref',
    'total'       => 'total_valor_base',
    'a_pagar'     => 'total_saldo_actual',
    // ...
];

$sort = $request->get('sort', 'fecha');
$direction = strtolower($request->get('direction', 'desc'));

if (!array_key_exists($sort, $sortableColumns)) {
    $sort = 'fecha';
}

if (!in_array($direction, ['asc', 'desc'], true)) {
    $direction = 'desc';
}

$orderByCol = $sortableColumns[$sort];
$query->orderBy($orderByCol, $direction);

// Orden secundario determinista
if ($orderByCol !== 'creditos.id') {
    $query->orderBy('creditos.id', 'desc');
}
```
