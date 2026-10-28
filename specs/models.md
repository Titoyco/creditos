# Especificación de Modelos: Clientes, Créditos y Cuotas

Este documento describe en detalle las entidades principales del sistema (`Cliente`, `Credito` y `Cuota`), sus esquemas en base de datos (migraciones), tipos de datos, atributos asignables en masa (*fillable*), relaciones Eloquent y métodos / accesores calculados.

---

## 0. Armonización con `routes-and-controllers.md` (revisión vigente)

Cambios de coherencia aplicados (código + especificaciones), ya verificados:

| Tema | Definición única adoptada | Dónde se corrigió |
|---|---|---|
| Lote origen del crédito | FK relacional **`creditos.id_presentacion`** (`Credito::presentacion()`). **No existe** columna física `presentacion_origen`. | §2.1, §2.1.1, §2.3 |
| Lote exigible de una cuota | `presentaciones.numero + cuotas.num_cuota` ⇒ `Cuota::lote_calculado` (accesor oficial); `lote_presentacion` queda **deprecado** (alias). | §3.4 |
| FK de firma autorizada | `creditos.id_autorizado` → **`cliente_autorizados.id`**; `NULL` = crédito del titular. | §2.1, §2.1.1, §2.3 |
| Consulta de cierre de lote | INNER JOIN `creditos.id_presentacion` → `presentaciones` (nunca `creditos.presentacion_origen`). | `routes-and-controllers.md` §3.3 |
| Tasas de presentación | Columnas reales `interes_credito` / `interes_mora` (en `$fillable`); `tasa_cuotas` / `tasa_mora` **no existen** como columnas. | `routes-and-controllers.md` §3.3 |
| Reglas de negocio en alta | Validación en BD de presentación abierta (`fecha_fin IS NULL`), cliente no suspendido, tope `cantidad_cuotas <= cliente.max_cuotas` y disponible `(deuda + importe) <= cliente.limite_efectivo`. Tasa tomada de `interes_credito`. | `routes-and-controllers.md` §2.1, §3.1 |
| Cobranza y cancelación | `pagarCuota` valida `monto` (min 0.01 y max saldo) y `medio`; actualiza `entrego += monto`, `saldo_actual = max(0, saldo_actual - monto)` y asigna `creditos.fecha_cancelacion = now()` al quedar saldo 0. | `routes-and-controllers.md` §2.1, §3.1 |

---

## 1. Modelo `Cliente`

- **Clase**: `App\Models\Cliente`
- **Tabla**: `clientes`
- **Migración**: `database/migrations/2026_03_21_124052_create_clientes_table.php`

### 1.1 Esquema de Base de Datos y Tipos de Datos

| Columna | Tipo BD | Modificadores / Restricciones | Descripción |
| :--- | :--- | :--- | :--- |
| `id` | `bigint unsigned` | PK, Auto-increment | Identificador único del cliente |
| `dni` | `integer` | UNIQUE | DNI / Documento del cliente |
| `nombre` | `varchar(191)` | Not Null | Apellido y Nombre del cliente |
| `id_sucursal` | `bigint unsigned` | FK -> `sucursales(id)` | Sucursal a la que pertenece |
| `medio_pago_preferido` | `integer` | Default `0` | Forma de cobro preferida (0: Efectivo, 1: Banco, etc.) |
| `cbu` | `varchar(25)` | Nullable | CBU / Cuenta bancaria para débitos |
| `tipo_haber` | `bigint` | Default `1` | Clasificación del ingreso / haber del cliente |
| `saldo` | `decimal(12,2)` | Default `0.00` | Saldo actual en cuenta |
| `limite` | `decimal(12,2)` | Default `0.00` | Límite crediticio asignado |
| `max_cuotas` | `integer` | Default `3` | Cantidad máxima permitida de cuotas |
| `suspendido` | `boolean` | Default `false` | Indicador de suspensión preventiva |
| `estado_banco` | `varchar(3)` | Nullable | Código de estado bancario (Ej: ACT, MOR, BAJ) |
| `fecha_estado_banco` | `date` | Nullable | Fecha del último estado bancario |
| `direccion` | `varchar(191)` | Not Null | Dirección particular del cliente |
| `id_localidad` | `bigint unsigned` | FK -> `localidades(id)` | Localidad particular del cliente |
| `email` | `varchar(191)` | Nullable | Correo electrónico de contacto |
| `tel1` | `varchar(50)` | Not Null | Teléfono principal de contacto |
| `tel2` | `varchar(50)` | Nullable | Teléfono secundario alternativo |
| `familiar` | `varchar(191)` | Nullable | Nombre del familiar / referencia personal |
| `tipo_familiar` | `varchar(50)` | Nullable | Vínculo familiar (Ej: Padre, Hermano, Cónyuge) |
| `direccion_familiar` | `varchar(191)` | Nullable | Dirección de residencia del familiar |
| `id_localidad_familiar` | `bigint unsigned` | Nullable, FK -> `localidades(id)` | Localidad de residencia del familiar |
| `email_familiar` | `varchar(191)` | Nullable | Correo del familiar |
| `tel_familiar` | `varchar(50)` | Nullable | Teléfono del familiar |
| `trabajo` | `varchar(191)` | Nullable | Empleador o lugar de trabajo |
| `direccion_trabajo` | `varchar(191)` | Nullable | Dirección del lugar de trabajo |
| `id_localidad_trabajo` | `bigint unsigned` | Nullable, FK -> `localidades(id)` | Localidad del lugar de trabajo |
| `tel_trabajo` | `varchar(50)` | Nullable | Teléfono laboral |
| `obs` | `text` | Nullable | Observaciones y notas generales del cliente |
| `created_at` | `timestamp` | Nullable | Fecha y hora de creación del registro |
| `updated_at` | `timestamp` | Nullable | Fecha y hora de última modificación |

### 1.2 Atributos Fillable

```php
protected $fillable = [
    'dni', 'nombre', 'medio_pago_preferido', 'cbu', 'id_sucursal', 'tipo_haber',
    'saldo', 'limite', 'max_cuotas', 'suspendido', 'obs', 'estado_banco', 'fecha_estado_banco',
    'direccion', 'id_localidad', 'email', 'tel1', 'tel2',
    'familiar', 'tipo_familiar', 'direccion_familiar', 'id_localidad_familiar', 'email_familiar', 'tel_familiar',
    'trabajo', 'direccion_trabajo', 'id_localidad_trabajo', 'tel_trabajo'
];
```

### 1.3 Relaciones Eloquent

- **`sucursal()`**: `BelongsTo` con `Sucursal::class` (FK: `id_sucursal`).
- **`localidad()`**: `BelongsTo` con `Localidad::class` (FK: `id_localidad`).
- **`localidadFamiliar()`**: `BelongsTo` con `Localidad::class` (FK: `id_localidad_familiar`).
- **`localidadTrabajo()`**: `BelongsTo` con `Localidad::class` (FK: `id_localidad_trabajo`).
- **`creditos()`**: `HasMany` con `Credito::class` (FK en créditos: `id_cliente`).
- **`autorizados()`**: `BelongsToMany` con `Cliente::class` a través de tabla pivote `cliente_autorizados` (Claves: `id_titular` -> `id_autorizado`), con campos pivote `['relacion_aut', 'fecha_autorizado', 'limite_aut']` y timestamps. Clientes a quienes este titular autorizó a comprar.
- **`titulares()`**: `BelongsToMany` con `Cliente::class` a través de tabla pivote `cliente_autorizados` (Claves: `id_autorizado` -> `id_titular`), con los mismos campos pivote. Titulares de los cuales este cliente es autorizado/adicional.
- **`novedades()`**: `HasMany` con `ClienteNovedad::class` (FK: `id_cliente`), ordenado por `latest()`.

### 1.4 Accesores y Métodos de Conveniencia

- **`getLimiteEfectivoAttribute()`**: Determina el límite de crédito aplicable. Si actúa como cliente autorizado y tiene `limite_aut` definido en la tabla intermedia, toma dicho valor; en caso contrario, toma el límite asignado en su propio registro.


---

## 2. Modelo `Credito`

- **Clase**: `App\Models\Credito`
- **Tabla**: `creditos`
- **Migración**: `database/migrations/2026_07_22_224059_create_creditos_table.php`

### 2.1 Esquema de Base de Datos y Tipos de Datos

| Columna | Tipo BD | Modificadores / Restricciones | Descripción |
| :--- | :--- | :--- | :--- |
| `id` | `bigint unsigned` | PK, Auto-increment | Identificador único del crédito |
| `id_cliente` | `bigint unsigned` | FK -> `clientes(id)` on delete cascade | Titular de la operación crediticia |
| `id_sucursal` | `bigint unsigned` | FK -> `sucursales(id)` | Sucursal donde se origina la operación |
| `id_autorizado` | `bigint unsigned` | FK -> `cliente_autorizados(id)` on delete cascade | Registro de autorización que realiza o habilita la compra |
| `id_presentacion` | `bigint unsigned` | FK -> `presentaciones(id)` | **Lote / Presentación de ORIGEN del crédito** — fuente de verdad del lote inicial (ver §2.1.1). No existe columna física `presentacion_origen`. |
| `credito` | `varchar(20)` | UNIQUE | Código / número de comprobante o venta |
| `tipo_credito` | `varchar(1)` | Not Null | Letra o clasificación del crédito (ej. `C`, `D`, `R`) |
| `importe_original` | `decimal(12,2)` | Not Null | Monto de capital inicial financiado |
| `cantidad_cuotas` | `integer` | Not Null | Número total de cuotas pactadas |
| `valor_cuota_ref` | `decimal(12,2)` | Not Null | Monto de referencia pactado por cuota |
| `fecha_credito` | `date` | Not Null | Fecha de emisión / alta del crédito |
| `fecha_cancelacion` | `date` | Nullable | Fecha efectiva de cancelación total del crédito |
| `created_at` | `timestamp` | Nullable | Fecha y hora de creación |
| `updated_at` | `timestamp` | Nullable | Fecha y hora de última modificación |

### 2.1.1 Lote de origen y firma autorizada (armonizado con `routes-and-controllers.md` §3.3)

**Lote origen (definición canónica):**

- La **fuente de verdad** del lote donde nació el crédito es la FK relacional **`creditos.id_presentacion`**,
  expuesta por `Credito::presentacion()` (`belongsTo(Presentacion::class, 'id_presentacion')`).
- **No** existe la columna física `creditos.presentacion_origen` (verificado en
  `database/migrations/2026_07_22_224059_create_creditos_table.php`, que sólo declara `id_presentacion`).
  Cualquier consulta que necesite el lote de origen debe hacer **INNER JOIN con `presentaciones`**
  a través de `creditos.id_presentacion`.
- El **lote exigible de una cuota** se obtiene sumando el número del lote origen más el ordinal de la cuota:
  `presentaciones.numero + cuotas.num_cuota` ⇒ accesor oficial `Cuota::lote_calculado`
  (`Cuota::getLoteCalculadoAttribute()`, ver §3.4).
- Ejemplo: crédito creado en la presentación `numero = 500`, cuota `num_cuota = 3` ⇒ lote `503`
  (exigible cuando el sistema abra la presentación 503).

**Firma autorizada (`id_autorizado`):**

- `creditos.id_autorizado` es FK a **`cliente_autorizados(id)`** (la *autorización*), **no** a `clientes(id)`.
- `NULL` = crédito tomado directamente por el titular (`id_cliente`).
- Para obtener los datos del firmante se navega `credito.autorizado.cliente`
  (`ClienteAutorizado::cliente()` ⇒ `belongsTo(Cliente::class, 'id_autorizado')`).
- El valor correcto a enviar en el alta es `cliente_autorizados.id` (en `clientes.index` se obtiene con
  `$autorizado->pivot->id`, ya que `Cliente::autorizados()` es un `BelongsToMany` sobre esa tabla).

### 2.2 Atributos Fillable

```php
protected $fillable = [
    'id_cliente', 
    'id_sucursal', 
    'id_autorizado',
    'id_presentacion',
    'credito',
    'tipo_credito',
    'importe_original',
    'cantidad_cuotas', 
    'valor_cuota_ref',
    'fecha_credito', 
    'fecha_cancelacion'
];
```

### 2.3 Relaciones Eloquent

- **`cliente()`**: `BelongsTo` con `Cliente::class` (FK: `id_cliente`). Cliente titular del crédito.
- **`autorizado()`**: `BelongsTo` con `ClienteAutorizado::class` (FK: `id_autorizado` → `cliente_autorizados.id`). Autorización bajo la cual se tomó el crédito; `NULL` = crédito propio del titular. No es una FK a `clientes` (ver §2.1.1).
- **`sucursal()`**: `BelongsTo` con `Sucursal::class` (FK: `id_sucursal`). Sucursal asociada al crédito.
- **`presentacion()`**: `BelongsTo` con `Presentacion::class` (FK: `id_presentacion`). **Lote de ORIGEN del crédito** — fuente de verdad del lote inicial (§2.1.1); sustituye a la inexistente columna `presentacion_origen`.
- **`cuotas()`**: `HasMany` con `Cuota::class` (FK en cuotas: `id_credito`). Cuotas que componen el crédito.

- **`getFullIdentificadorAttribute()`**: Genera el identificador único compuesto del crédito bajo el formato oficial `{tipo_credito}{numero_a_7_digitos}{codigo_sucursal}` (Ej: `C0001234TW`, `C0001234IN`, `R0001234TW`, `D0001234TW`, `C0001234US`).
- **`getCuotaProgresoAttribute()`**: Calcula la cuota en curso ("cuota que va") respecto al total pactado bajo el formato `"X / Y"` (Ej: `"2 / 4"`, `"4 / 4"`), según la diferencia entre la presentación activa de la provincia y la presentación de origen del crédito: `min(cantidad_cuotas, max(0, pres_activa - pres_origen))`.

---

## 3. Modelo `Cuota`

- **Clase**: `App\Models\Cuota`
- **Tabla**: `cuotas`
- **Migración**: `database/migrations/2026_07_22_224059_create_cuotas_table.php`

### 3.1 Esquema de Base de Datos y Tipos de Datos

| Columna | Tipo BD | Modificadores / Restricciones | Descripción |
| :--- | :--- | :--- | :--- |
| `id` | `bigint unsigned` | PK, Auto-increment | Identificador único de la cuota |
| `id_credito` | `bigint unsigned` | FK -> `creditos(id)` on delete cascade | Crédito al que corresponde la cuota |
| `num_cuota` | `integer` | Not Null | Número de cuota ordinal (1, 2, 3...) |
| `valor_base` | `decimal(12,2)` | Not Null | Importe pactado original (Capital + Interés de financiación) |
| `intereses` | `decimal(12,2)` | Default `0.00` | Recargos o mora calculada acumulada |
| `entrego` | `decimal(12,2)` | Default `0.00` | Importe acumulado pagado por el cliente |
| `saldo_actual` | `decimal(12,2)` | Not Null | Saldo exigible neto actual (`[valor_base + intereses] - entrego`) |
| `fecha_cobro` | `date` | Nullable | Fecha efectiva en que se percibió el pago |
| `medio_pago` | `varchar(50)` | Nullable | Medio utilizado para la cobranza |
| `referencia_pago` | `varchar(191)` | Nullable | Comprobante / identificador de transacción de pago |
| `banco_envio` | `varchar(100)` | Nullable | Entidad bancaria o cámara a la que se remite el cobro |
| `fecha_ultimo_envio` | `date` | Nullable | Fecha del último envío al débito o cobro bancario |
| `estado_bancario` | `varchar(5)` | Nullable | Código de respuesta bancaria (Ej: ACE, R10, R02) |
| `obs_devolucion` | `text` | Nullable | Detalle de motivos o motivo de rechazo bancario |
| `created_at` | `timestamp` | Nullable | Fecha y hora de creación |
| `updated_at` | `timestamp` | Nullable | Fecha y hora de última modificación |

### 3.2 Atributos Fillable

```php
protected $fillable = [
    'id_credito',
    'num_cuota',
    'valor_base',
    'intereses',
    'entrego',
    'saldo_actual',
    'fecha_cobro',
    'medio_pago',
    'referencia_pago',
    'banco_envio',
    'fecha_ultimo_envio',
    'estado_bancario',
    'obs_devolucion'
];
```

### 3.3 Relaciones Eloquent

- **`credito()`**: `BelongsTo` con `Credito::class` (FK: `id_credito`). Crédito contenedor de la cuota.

### 3.4 Accesores y Métodos de Conveniencia

- **`getLoteCalculadoAttribute()` → `lote_calculado`**: ⭐ **ACCESOR OFICIAL DE LOTE**. Suma el número base de la
  presentación de origen (`$this->credito->presentacion->numero`) con el número de cuota (`$this->num_cuota`).
  Definición canónica del sistema: lote exigible = `presentaciones.numero + cuotas.num_cuota`
  (ver §2.1.1 y `routes-and-controllers.md` §3.3). Es el accesor usado por `CreditoController@create`.
- **`getLotePresentacionAttribute()` → `lote_presentacion`**: ⚠ **DEPRECADO**. Nomenclatura obsoleta y
  contradictoria: su implementación anterior (`$this->credito->presentacion + $this->num_cuota`) sumaba un
  *Modelo* a un entero (error fatal de PHP). Se conserva únicamente como alias que **delega en `lote_calculado`**
  para no romper vistas antiguas. **Todo código nuevo debe usar `lote_calculado`**; no usar `lote_presentacion`
  ni introducir columnas del tipo `presentacion_origen` en consultas.
- **`getCodigoGestionAttribute()`**: Genera el código único de seguimiento concatenando el identificador compuesto del crédito (`full_identificador`) y el número de cuota (Ej: `R0001234TW1` o `C0001234TW1`).

---

## 4. Diagrama de Relaciones Entidad-Relación

```text
+--------------------+           1:N          +--------------------+           1:N          +--------------------+
|      Cliente       | ---------------------> |      Credito       | ---------------------> |       Cuota        |
+--------------------+                        +--------------------+                        +--------------------+
| id (PK)            |                        | id (PK)            |                        | id (PK)            |
| dni                |                        | id_cliente (FK)    |                        | id_credito (FK)    |
| nombre             |                        | id_autorizado (FK) |                        | num_cuota          |
| id_sucursal (FK)   |                        | id_sucursal (FK)   |                        | valor_base         |
| id_localidad (FK)  |                        | id_presentacion(FK)|                        | intereses          |  ← LOTE ORIGEN (§2.1.1)
| saldo, limite...   |                        | credito (codigo)   |                        | entrego            |
+--------------------+                        | tipo_credito       |                        | saldo_actual       |
       |     ^                                | importe_original   |                        | estado_bancario    |
       |     |                                | cantidad_cuotas    |                        +--------------------+
       |     |                                +--------------------+
       |     |
       |     +-------------------------+
       |   N:M autorizados/titulares   |
       v                               |
+------------------------------------------+
|            ClienteAutorizado             |
+------------------------------------------+
| id (PK)                                  |
| id_titular (FK -> clientes.id)           |
| id_autorizado (FK -> clientes.id)        |
| limite_aut, relacion_aut...              |
+------------------------------------------+
```

---

## 5. Notas de integridad referencial (armonizadas con `routes-and-controllers.md`)

0. **`clientes.tipo_haber` es `BIGINT NOT NULL DEFAULT 1`.** Es la única columna `NOT NULL` del legajo que
   **no** tiene un campo `required` en el formulario (queda como input numérico opcional). Por eso el alta
   se rompe con `SQLSTATE[23000] … Column 'tipo_haber' cannot be null` si el operador lo deja vacío:
   `nullable` deja pasar el vacío, se persiste `NULL` explícito y eso **anula el default del esquema**.
   Regla vigente: `ClienteController::normalizarDatosCliente()` aplica `TIPO_HABER_POR_DEFECTO = 1` cuando el
   campo viene vacío (alta y edición), y `clientes/form.blade.php` precarga `1` + `min="1"` para que el
   operador ni siquiera pueda enviarlo vacío. Reglas Deploy: cualquier columna `NOT NULL` sin default
   utilizable debe tener default en la capa de aplicación, nunca confiar en que el `INSERT` omita la clave.
1. `creditos.id_autorizado` → **`cliente_autorizados.id`** (⚠ **no** `clientes.id`). `NULL` = crédito del titular.
2. `creditos.id_presentacion` → `presentaciones.id`: **lote de origen**; no existe `creditos.presentacion_origen`.
3. Lote exigible de una cuota = `presentaciones.numero + cuotas.num_cuota` ⇒ `Cuota::lote_calculado` (accesor oficial).
4. `presentaciones` declara las columnas reales **`interes_credito` / `interes_mora`** (ambas en `$fillable`);
   `tasa_cuotas` / `tasa_mora` **no existen** como columnas de la tabla.
5. `Cliente::autorizados()` expone `pivot->id` (= `cliente_autorizados.id`), que es el valor que debe viajar en
   `id_autorizado` al dar de alta un crédito adicional (`clientes.index` → `creditos.create`).
6. El límite crediticio disponible de un cliente se calcula contra sus cuotas con `saldo_actual > 0` pertenecientes
   a créditos abiertos (`fecha_cancelacion IS NULL`). La evaluación toma `cliente.limite_efectivo` (que prioriza `limite_aut`).
7. La cancelación de un crédito (`fecha_cancelacion = now()`) se ejecuta automáticamente en la cobranza (`pagarCuota`)
   en el instante en que no restan cuotas con `saldo_actual > 0`.
8. **Auditoría y reversiones compensatorias:**
   - Los modelos `Cliente`, `Credito`, `Cuota` y `Presentacion` implementan la interfaz `OwenIt\Auditing\Contracts\Auditable` con el trait `OwenIt\Auditing\Auditable`.
   - La anulación de cobros (`anularPago`) restituye el capital adeudado en `cuotas.saldo_actual = saldo_actual + entrego`, borra `entrego`, `fecha_cobro` y `medio_pago`, y reactiva el crédito fijando `fecha_cancelacion = NULL`.
9. **Catálogos del sistema (`sistema.sucursales.*` / `sistema.ubicaciones.*`):**
   - `sucursales` = `id`, `nombre` (varchar 191), `codigo_sucursal` (**varchar 10, UNIQUE** — ensanchado desde
     `varchar(1)` por `2026_09_27_231133_alter_codigo_sucursal_in_sucursales_table`) y **`direccion`**
     (varchar 191, nullable — agregada por `2026_09_27_230928_add_direccion_to_sucursales_table`).
     `Sucursal::$fillable = ['nombre', 'codigo_sucursal', 'direccion']`.
   - Relaciones nuevas en `Sucursal`: `indices()`, **`clientes()`** (FK `clientes.id_sucursal`) y
     **`creditos()`** (FK `creditos.id_sucursal`), usadas por `SucursalController@destroy` para bloquear el
     borrado de sucursales con registros vinculados.
   - `Localidad` suma las relaciones inversas **`clientes()`** (FK `clientes.id_localidad`),
     **`clientesFamiliar()`** (FK `clientes.id_localidad_familiar`) y **`clientesTrabajo()`**
     (FK `clientes.id_localidad_trabajo`), que `UbicacionController@destroyLocalidad` evalúa en conjunto
     antes de eliminar.
   - `Sucursal`, `Localidad` y `Provincia` implementan `OwenIt\Auditing\Contracts\Auditable` (con el trait
     `Auditable`), por lo que los ABM quedan trazado en la tabla `audits` y son reversibles desde
     `sistema.auditoria.rollback`.
   - `Provincia::$fillable = ['nombre']` con restricción **UNIQUE** en BD, replicada en el ABM como
     `unique:provincias,nombre` (+ `Rule::unique(...)->ignore($id)` en la edición).
   - `Localidad::$fillable = ['id_provincia', 'nombre', 'codigo_postal']`; `codigo_postal` es **nullable**
     y se normaliza el `nombre` con `ucwords(strtolower(trim()))` en altas y ediciones.

   - La anulación de créditos (`anularCredito`) exige que el crédito no posea cuotas con amortización (`entrego == 0`); tras asentar auditoría y novedad en legajo, destruye el crédito y sus cuotas en cascada.


---

## 6. Modelo `Sucursal` (Datos Fiscales, Comerciales y de Facturación)

- **Clase**: `App\Models\Sucursal`
- **Tabla**: `sucursales`
- **Migración principal y ampliaciones**:
  - `database/migrations/2026_03_21_124050_create_sucursales_table.php`
  - `database/migrations/2026_09_27_230928_add_direccion_to_sucursales_table.php`
  - `database/migrations/2026_10_23_000001_add_datos_fiscales_to_sucursales_table.php`

### 6.1 Esquema de Base de Datos y Datos Fiscales

| Columna | Tipo BD | Modificadores | Descripción |
| :--- | :--- | :--- | :--- |
| `id` | `bigint unsigned` | PK, Auto-increment | Identificador único de la sucursal |
| `nombre` | `varchar(191)` | Not Null | Nombre descriptivo de la sucursal (Ej: Trelew - Centro, Ushuaia) |
| `codigo_sucursal` | `varchar(10)` | UNIQUE, Nullable | Código interno / contable |
| `razon_social` | `varchar(191)` | Nullable | Razón social del responsable fiscal |
| `nombre_fantasia` | `varchar(191)` | Nullable | Nombre comercial / de fantasía ("La Marina") |
| `cuit` | `varchar(20)` | Nullable | CUIT fiscal correspondiente |
| `condicion_iva` | `varchar(100)` | Nullable | Condición frente al IVA (Ej: IVA Responsable Inscripto) |
| `ingresos_brutos` | `varchar(100)` | Nullable | Número de Ingresos Brutos / Convenio Multilateral |
| `direccion` | `varchar(191)` | Nullable | Dirección física del local comercial |
| `id_provincia` | `bigint unsigned` | Nullable, FK -> `provincias(id)` | Provincia de radicación física |
| `telefono` | `varchar(50)` | Nullable | Teléfono fijo o celular de contacto comercial |
| `email` | `varchar(191)` | Nullable | Correo electrónico comercial para facturación |
| `web` | `varchar(191)` | Nullable | Página web institucional (`www.tiendaslamarina.com.ar`) |
| `punto_venta` | `integer` | Nullable, Default `1` | Número de punto de venta asignado para facturación |
| `created_at` / `updated_at` | `timestamp` | Nullable | Marcas de tiempo |

### 6.2 Datos Fiscales Configurados por Jurisdicción

- **Chubut (Trelew):**
  - Nombre de Fantasía: *"La Marina"*
  - Razón Social: *"Tiendas la Marina SAS"*
  - CUIT: `30-71699602-2`
  - Domicilio: `Rivadavia 249 - Trelew - Chubut`
- **Tierra del Fuego (Ushuaia):**
  - Nombre de Fantasía: *"La Marina"*
  - Razón Social: *"BARZINI GUILLERMO ENRIQUE"*
  - CUIT: `20-11474524-4`
  - Domicilio: `San Martín 1255 - Ushuaia - Tierra del Fuego`

### 6.3 Relaciones Eloquent
- **`clientes()`**: `HasMany` con `Cliente::class` (`id_sucursal`).
- **`creditos()`**: `HasMany` con `Credito::class` (`id_sucursal`).
- **`ventas()`**: `HasMany` con `Venta::class` (`id_sucursal`).
- **`cajasSesiones()`**: `HasMany` con `CajaSesion::class` (`id_sucursal`).
- **`provincia()`**: `BelongsTo` con `Provincia::class` (`id_provincia`).
- **`stocks()`**: `HasMany` con `ProductoStock::class` (`id_sucursal`).

---

## 7. Modelos `CajaSesion` y `CajaMovimiento` (Control de Cajas, Arqueo y Movimientos Manuales)

- **Clases**: `App\Models\CajaSesion`, `App\Models\CajaMovimiento`
- **Tablas**: `cajas_sesiones`, `cajas_movimientos`
- **Migraciones**:
  - `database/migrations/2026_10_27_230000_create_cajas_sesiones_table.php`
  - `database/migrations/2026_10_28_080000_create_cajas_movimientos_table.php`

### 7.1 Esquema `cajas_sesiones`

| Columna | Tipo BD | Descripción |
| :--- | :--- | :--- |
| `id` | `bigint unsigned` | PK del turno de caja |
| `id_sucursal` | `bigint unsigned` | FK -> `sucursales(id)` |
| `id_usuario_apertura` | `bigint unsigned` | FK -> `users(id)` usuario que abrió la caja |
| `id_usuario_cierre` | `bigint unsigned` | FK -> `users(id)` usuario que efectuó el Cierre Z |
| `fecha_apertura` | `datetime` | Fecha y hora de apertura del turno |
| `fecha_cierre` | `datetime` | Fecha y hora de Cierre Z |
| `monto_inicial_efectivo` | `decimal(14,2)` | Fondo inicial ingresado en la apertura |
| `monto_final_efectivo_esperado` | `decimal(14,2)` | Efectivo teórico del sistema ($Fondo + VentasEf + Ingresos - Retiros$) |
| `monto_final_efectivo_real` | `decimal(14,2)` | Efectivo físico contado por el cajero en el arqueo |
| `diferencia_efectivo` | `decimal(14,2)` | Fallo de caja ($Real - Esperado$). Positivo = Sobrante, Negativo = Faltante |
| `total_efectivo` | `decimal(14,2)` | Total facturado en efectivo |
| `total_tarjeta_debito` | `decimal(14,2)` | Total facturado con tarjeta de débito |
| `total_tarjeta_credito` | `decimal(14,2)` | Total facturado con tarjeta de crédito |
| `total_credito_personal` | `decimal(14,2)` | Total financiado en cuenta corriente / crédito personal |
| `total_transferencia` | `decimal(14,2)` | Total cobrado por transferencia bancaria / QR |
| `total_otros` | `decimal(14,2)` | Total cobrado con otros medios |
| `total_general` | `decimal(14,2)` | Facturación total acumulada del turno |
| `cant_ventas_*` | `integer` | Contadores de comprobantes emitidos por cada medio de pago |
| `cant_ventas_total` | `integer` | Contador total de ventas del turno |
| `total_ingresos_efectivo` | `decimal(14,2)` | Total de ingresos manuales justificados |
| `total_retiros_efectivo` | `decimal(14,2)` | Total de retiros/gastos manuales justificados |
| `cant_ingresos_efectivo` | `integer` | Cantidad de ingresos manuales |
| `cant_retiros_efectivo` | `integer` | Cantidad de retiros/gastos manuales |
| `estado` | `varchar(20)` | `abierta` / `cerrada` |
| `observaciones_apertura` | `text` | Notas del operador en apertura (ej. desglose de billetes de cambio) |
| `observaciones_cierre` | `text` | Justificaciones de arqueo / notas de cierre |

### 7.2 Esquema `cajas_movimientos` (Ingresos y Retiros Manuales Justificados)

| Columna | Tipo BD | Modificadores | Descripción |
| :--- | :--- | :--- | :--- |
| `id` | `bigint unsigned` | PK | Identificador único del movimiento |
| `id_caja_sesion` | `bigint unsigned` | FK -> `cajas_sesiones(id)` ON DELETE CASCADE | Sesión de caja en la que ocurrió |
| `id_usuario` | `bigint unsigned` | FK -> `users(id)` | Usuario responsable que registró el movimiento |
| `tipo` | `varchar(20)` | Not Null (`ingreso` o `retiro`) | Clasificación del flujo de dinero |
| `monto` | `decimal(14,2)` | Not Null, Min `0.01` | Monto dinerario en pesos ARS |
| `motivo` | `varchar(255)` | Not Null | **Justificación obligatoria** (ej. "Pago flete proveedor", "Compra insumos", "Aporte sencillo") |
| `comprobante` | `varchar(100)` | Nullable | N° de recibo, remito o factura física de respaldo |
| `created_at` / `updated_at` | `timestamp` | Nullable | Marcas de tiempo de auditoría |

### 7.3 Métodos Clave de `CajaSesion`
- **`calcularTotales()`**: Suma en tiempo real las ventas activas discriminadas por medio de pago, computa los movimientos manuales (`totalIngresos`, `totalRetiros`) y formula el `efectivo_esperado`.
- **`cerrar(float $efectivoRealContado, ?string $observaciones, ?int $usuarioId)`**: Fija la fecha de cierre, persiste el arqueo, calcula la diferencia/fallo y marca `estado = 'cerrada'`.
- **`esDeHoy()`**: Determina si la sesión pertenece a la jornada actual (`fecha_apertura->isToday()`).
- **`scopeActivaParaSucursal($sucursalId)`**: Devuelve la caja con `estado = 'abierta'` correspondiente a la sucursal.

---

## 8. Modelos `Venta` y `VentaItem` (Punto de Venta POS & Facturación)

- **Clases**: `App\Models\Venta`, `App\Models\VentaItem`
- **Tablas**: `ventas`, `venta_items`
- **Migración**: `database/migrations/2026_10_23_000003_create_ventas_table.php`

### 8.1 Atributos e Integridad
- **`id_caja_sesion`**: FK relacional mandatoria hacia `cajas_sesiones.id`. Ninguna venta puede emitirse si la caja de la sucursal no se encuentra abierta y al día.
- **`medio_pago`**: Valores tipificados: `efectivo`, `tarjeta_debito`, `tarjeta_credito`, `credito_personal`, `transferencia`, `otros`.
- **`id_credito`**: FK relacional opcional hacia `creditos.id` generada automáticamente cuando la venta se cobra mediante `credito_personal`.
- **`estado`**: `completada` o `anulada`. Al anularse una venta, el stock reservado se repone de inmediato y, si generó crédito personal, dicho crédito se cancela.

---

## 9. Modelos de Stock: `Producto`, `ProductoSucursal`, `ProductoStock` y `StockMovimiento`

- **Clases**: `App\Models\Producto`, `App\Models\ProductoStock`, `App\Models\StockMovimiento`
- **Tablas**: `productos`, `productos_sucursales` / `productos_stock`, `stock_movimientos`

### 9.1 Control de Stock por Sucursal y Prevención de Stock Negativo
- Cada producto registra stock diferenciado por sucursal (`id_sucursal`, `id_producto`, `stock_actual`, `stock_minimo`).
- **Validación en Facturación:** Si el producto tiene habilitado `controla_stock = true` y su stock disponible en la sucursal es `0` (o inferior a la cantidad solicitada), el sistema bloquea la emisión para evitar saldos negativos.
- **Corrección Rápida por Recuento Físico:** El POS permite que el operador, previa confirmación por recuento en mostrador, ejecute una corrección en el acto registrando un `StockMovimiento` de tipo `recuento_fisico` para regularizar el inventario y continuar con la venta.

---

## 10. Modelo `User` (Seguridad, Autenticación y Superusuario Secreto)

- **Clase**: `App\Models\User`
- **Tabla**: `users`

### 10.1 Políticas de Seguridad y Privacidad
- **Autenticación sin credenciales expuestas:** Se eliminó todo selector o precarga de usuarios/claves en la vista de bienvenida (`/login`). El acceso requiere autenticación obligatoria mediante `username` y `password` encriptado (Bcrypt).
- **Aislamiento de Rutas:** Todas las rutas operativas (`/sistema/*`, `/ventas/*`, `/cajas/*`, `/recibos/*`, `/clientes/*`, `/creditos/*`) se encuentran blindadas bajo el middleware `auth`.
- **Superusuario Secreto (`superuser`):**
  - Credencial de emergencia con privilegios totales.
  - **Filtro de privacidad:** Excluido programáticamente de los listados y combos de administración de usuarios para que operadores comunes no detecten su existencia.
  - **Autorización exclusiva:** Operaciones críticas de alta sensibilidad (como la restauración total de la base de datos desde copias de seguridad `.zip`) quedan restringidas exclusivamente al `superuser` o administradores de nivel superior.
