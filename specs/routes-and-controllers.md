# Rutas y Controladores — LaMarina Créditos

Alcance verificado: `routes/web.php`, `routes/auth.php` y
`app/Http/Controllers/{CreditoController, ClienteController, PresentacionController, EstadoBancoController, SucursalController}.php`.
Marcas: ⚠ = pendiente/inconsistencia verificada; ✅ = corregido en esta revisión (código + especificación).

## 0. Correcciones aplicadas en esta revisión (armonización con `models.md` / `ui-guidelines.md`)

Todos los puntos siguientes fueron **corregidos en el código** y reflejados en este documento y en
`specs/models.md`. La verificación de rutas se hizo con `php artisan route:list` (63 rutas, sin duplicados ni
métodos inexistentes) y `php -l` sobre cada archivo modificado.

| # | Inconsistencia detectada | Corrección aplicada |
|---|---|---|
| 1 | `creditos.presentacion_origen` (columna inexistente) usada en el cierre de lote | La fuente de verdad del lote origen es la FK relacional **`creditos.id_presentacion`**; el cierre ahora hace **INNER JOIN `presentaciones`** y filtra `(presentaciones.numero + cuotas.num_cuota) = ?` (§3.3). Documentado en `models.md` §2.1.1. |
| 2 | `CreditoController@store` guardaba `$request->id_cliente` en `id_autorizado` (**FK a `cliente_autorizados`**) | Se valida `id_autorizado` con `Rule::exists('cliente_autorizados','id')->where('id_titular', id_cliente)` y se persiste ese ID, o `NULL` si no viene (§2.1, §3.1). Se ajustó la cadena de vinculación (`Cliente::autorizados()` expone `pivot->id`; `create()` resuelve `ClienteAutorizado`). |
| 3 | `pagarCuota` devolvía `with('status')`, invisible para `<x-notification-modal />` | Ahora `back()->with('success', 'Pago registrado correctamente')` (§2.1, §3.1). |
| 4 | `POST /cambiar-sucursal` sin autenticación | Se agregó `->middleware('auth')` (§1.1, §2.5). |
| 5 | `sistema.presentaciones.cambiar` (GET) era **inalcanzable**: el `show` del `Route::resource` la capturaba como `{presentacion}` | La ruta ahora es **POST**, se declara **antes** del listado y el `Route::resource('presentaciones')` se reemplazó por la ruta explícita `GET /sistema/presentaciones` ⇒ `sistema.presentaciones.index` (§1.2). El formulario de la vista ya enviaba `POST` + `@csrf`. |
| 6 | 12 rutas apuntaban a métodos inexistentes (HTTP 500) | Comentadas/eliminadas en `routes/web.php`: `clientes.creditos`, `clientes.movimientos`, `creditos.edit/update/destroy`, `creditos.cuotas(.show/.edit/.update)`, `sucursales.cambiar`, y los endpoints `create/store/show/edit/update/destroy` del resource de presentaciones (§1.2). |
| 7 | `Presentacion::$fillable` no incluía las columnas reales ⇒ cierre de lote sin tasas y `DB::raw` con `NULL` | Se agregaron `interes_credito` / `interes_mora` y `$tasaMora` se castea a `float` (§3.3). |
| 8 | `redirect()->route('presentaciones.index')` (nombre inexistente) tras el `commit()` | Ahora `redirect()->route('sistema.presentaciones.index')` (§2.3, §3.3). |
| 9 | `Cuota::lote_presentacion` sumaba un *Modelo* a un entero (error fatal) y contradecía a `lote_calculado` | `lote_calculado` es el **accesor oficial**; `lote_presentacion` queda deprecado y delega en él (`models.md` §3.4). |
| 10 | `shadow-2xs` (utilidad de Tailwind v4) en dos vistas | Reemplazado por `shadow-sm` (`ui-guidelines.md` §0/§1.2/§14). |
| 11 | `resources/views/sistema/index.blade.php:17` enlazaba con `route('presentaciones.index')`, nombre **inexistente** (el real es `sistema.presentaciones.index`) ⇒ error 500 al abrir la tarjeta "Presentaciones" del dashboard de configuración | Corregido a `route('sistema.presentaciones.index')`. Se verificó que ninguna otra vista referencia rutas inexistentes (`clientes.creditos`, `clientes.movimientos`, `creditos.edit/update/destroy`, `creditos.cuotas.*`, `sucursales.cambiar`, `presentaciones.*`). |

## 1. Tabla de Rutas Web

### 1.1 Públicas / fuera del grupo `auth`

| Método | URI | Name | Controlador@Método | Middleware |
|---|---|---|---|---|
| GET | `/login` | `login` | closure → `view('auth.login')` (`web.php:26`) | (ninguno) ⚠ *código muerto: `auth.php` registra la misma URI+método después, por lo que la reemplaza en la colección de rutas; `php artisan route:list` muestra **una sola** entrada `/login` servida por el controlador Breeze. El closure nunca se ejecuta (se recomienda eliminarlo).* |
| GET | `/login` | `login` | `Auth\AuthenticatedSessionController@create` | `guest` (`auth.php`) — es la efectiva |
| POST | `/login` | — | `Auth\AuthenticatedSessionController@store` | `guest` |
| GET / POST | `/register` | `register` / — | `RegisteredUserController@create` / `@store` | `guest` |
| GET / POST | `/forgot-password` | `password.request` / `password.email` | `PasswordResetLinkController@create` / `@store` | `guest` |
| GET | `/reset-password/{token}` | `password.reset` | `NewPasswordController@create` | `guest` |
| POST | `/reset-password` | `password.store` | `NewPasswordController@store` | `guest` |
| POST | `/cambiar-sucursal` | `sucursal.update.global` | `SucursalController@updateGlobal` | `auth` ✅ *(declarada fuera del grupo `auth` pero con `->middleware('auth')` explícito; antes no exigía autenticación)* |
| GET | `/` | `dashboard` | closure → `view('dashboard')` | `auth` |

### 1.2 Grupo `auth` (`web.php:35-107`)

| Método | URI | Name | Controlador@Método | Middleware |
|---|---|---|---|---|
| GET | `/clientes` | `clientes.index` | `ClienteController@index` | `auth` |
| GET | `/clientes/nuevo` | `clientes.create` | `ClienteController@create` | `auth` |
| POST | `/clientes` | `clientes.store` | `ClienteController@store` | `auth` |
| GET | `/clientes/{cliente}` | `clientes.show` | `ClienteController@show` | `auth` |
| GET | `/clientes/{cliente}/editar` | `clientes.edit` | `ClienteController@edit` | `auth` |
| PUT | `/clientes/{cliente}` | `clientes.update` | `ClienteController@update` | `auth` |
| DELETE | `/clientes/{cliente}` | `clientes.destroy` | `ClienteController@destroy` | `auth` |
| GET | `/clientes/{cliente}/estado-cuenta` | `clientes.estado-cuenta` | `ClienteController@estadoCuenta` | `auth` |
| ❌ ~~GET~~ | ~~`/clientes/{cliente}/creditos`~~ | ~~`clientes.creditos`~~ | **RUTA ELIMINADA** (`ClienteController@creditos` no existe → 500). Alternativa funcional: `clientes.show` | — |
| ❌ ~~GET~~ | ~~`/clientes/{cliente}/movimientos`~~ | ~~`clientes.movimientos`~~ | **RUTA ELIMINADA** (`ClienteController@movimientos` no existe → 500). Alternativa: `clientes.estado-cuenta` | — |
| GET | `/creditos` | `creditos.index` | `CreditoController@index` | `auth` |
| GET | `/creditos/nuevo` | `creditos.create` | `CreditoController@create` | `auth` |
| POST | `/creditos` | `creditos.store` | `CreditoController@store` | `auth` |
| GET | `/creditos/{credito}` | `creditos.show` | `CreditoController@show` | `auth` |
| ❌ ~~GET~~ | ~~`/creditos/{credito}/editar`~~ | ~~`creditos.edit`~~ | **RUTA ELIMINADA** (`CreditoController@edit` no existe → 500) | — |
| ❌ ~~PUT~~ | ~~`/creditos/{credito}`~~ | ~~`creditos.update`~~ | **RUTA ELIMINADA** (`CreditoController@update` no existe → 500) | — |
| ❌ ~~DELETE~~ | ~~`/creditos/{credito}`~~ | ~~`creditos.destroy`~~ | **RUTA ELIMINADA** (`CreditoController@destroy` no existe → 500) | — |
| GET | `/creditos/{credito}/pagare` | `creditos.pagare` | `CreditoController@imprimirPagare` | `auth` |
| ❌ ~~GET~~ | ~~`/creditos/{credito}/cuotas`~~ | ~~`creditos.cuotas`~~ | **RUTA ELIMINADA** (`@cuotas` no existe → 500) | — |
| ❌ ~~GET~~ | ~~`/creditos/{credito}/cuotas/{cuota}`~~ | ~~`creditos.cuotas.show`~~ | **RUTA ELIMINADA** (`@showCuota` no existe → 500) | — |
| ❌ ~~GET~~ | ~~`/creditos/{credito}/cuotas/{cuota}/editar`~~ | ~~`creditos.cuotas.edit`~~ | **RUTA ELIMINADA** (`@editCuota` no existe → 500) | — |
| ❌ ~~PUT~~ | ~~`/creditos/{credito}/cuotas/{cuota}`~~ | ~~`creditos.cuotas.update`~~ | **RUTA ELIMINADA** (`@updateCuota` no existe → 500) | — |
| POST | `/creditos/{cuota}/pagar` | `creditos.pagar` | `CreditoController@pagarCuota` | `auth` |
| GET | `/sistema` | `sistema.index` | closure → `view('sistema.index')` | `auth` |
| RESOURCE | `/sistema/usuarios` | `usuarios.*` | `UserController` (CRUD completo) | `auth` |
| RESOURCE | `/sistema/permisos` | `permisos.*` | `PermisoController` (CRUD completo) | `auth` |
| RESOURCE | `/sistema/sucursales` | `sucursales.*` | `SucursalController` | `auth` ⚠ métodos de resource vacíos (respuesta nula) |
| GET | `/sistema/presentaciones` | `sistema.presentaciones.index` | `PresentacionController@index` | `auth` ✅ *(sustituye a `Route::resource('presentaciones')`, que registraba `create/store/show/edit/update/destroy` sin método en el controlador)* |
| POST | `/sistema/presentaciones/cambiar` | `sistema.presentaciones.cambiar` | `PresentacionController@cambiar` | `auth` ✅ *(corregido: verbo **POST**, declarada **antes** del listado ⇒ ya no la captura `{presentacion}`; la vista ya enviaba `POST` + `@csrf`)* |
| ❌ ~~GET~~ | ~~`/sistema/sucursales/{sucursal}/cambiar`~~ | ~~`sucursales.cambiar`~~ | **RUTA ELIMINADA** (`SucursalController@cambiar` no existe → 500). El cambio de sucursal activa es `sucursal.update.global` (§1.1) | — |
| GET | `/sistema/provincias` | `provincias.index` | `UbicacionController@provincias` | `auth` |
| GET | `/sistema/localidades` | `localidades.index` | `UbicacionController@localidades` | `auth` |
| GET | `/profile` | `profile.edit` | `ProfileController@edit` | `auth` |
| PATCH | `/profile` | `profile.update` | `ProfileController@update` | `auth` |
| DELETE | `/profile` | `profile.destroy` | `ProfileController@destroy` | `auth` |
| GET | `/verify-email` | `verification.notice` | `EmailVerificationPromptController` (`__invoke`) | `auth` |
| GET | `/verify-email/{id}/{hash}` | `verification.verify` | `VerifyEmailController` (`__invoke`) | `auth` |
| POST | `/email/verification-notification` | `verification.send` | `EmailVerificationNotificationController@store` | `auth` |
| GET | `/confirm-password` | `password.confirm` | `ConfirmablePasswordController@show` | `auth` |
| POST | `/confirm-password` | — | `ConfirmablePasswordController@store` | `auth` |
| PUT | `/password` | `password.update` | `PasswordController@update` | `auth` |
| POST | `/logout` | `logout` | `AuthenticatedSessionController@destroy` | `auth` |

**Código muerto detectado:** `EstadoBancoController` (CRUD de `estados_banco`) **no está registrado en ninguna
ruta**, no existe `routes/api.php`, y sus `redirect()->route('estados-banco.index')` apuntan a un nombre de
ruta inexistente. El modelo `DevolucionBancaria` tampoco se usa (clase vacía, sin `$fillable` ni tabla explícita).

## 2. Controladores y Métodos

Sólo se listan los métodos **públicos** realmente presentes en el código (verificado por firma).

### 2.1 `CreditoController` (6 métodos)

| Método | Parámetros | Validación | Modelos | Retorno |
|---|---|---|---|---|
| `index` | `Request` (`cliente_id`, `search`, `sucursal`, `tipo`, `estado`, `has_interes`, `has_entrego`, `has_saldo`) | ninguna | `Credito` (join `clientes`, `with cliente/sucursal/autorizado.cliente`, `withSum` de `valor_base`, `entrego`, `intereses`, `saldo_actual`) + `Sucursal::all()` | `view('creditos.index')` · `paginate(15)→withQueryString()` |
| `show` | `Credito $credito` (route model binding) | ninguna | `Credito` (`load cliente, sucursal, autorizado.cliente, cuotas` ordenadas por `num_cuota`) | `view('creditos.show')` con `$totalValorBase`, `$totalIntereses`, `$totalEntrego`, `$totalSaldo` |
| `pagarCuota` | `Request $request` (`monto`, `medio`), `Cuota $cuota` | **ninguna** ⚠ | `Cuota::update([entrego, fecha_cobro=now(), medio_pago, saldo_actual = valor_base + intereses − monto])` | `back()->with('success', 'Pago registrado correctamente')` ✅ *(corregido: antes usaba la clave `status`, que `<x-notification-modal />` no muestra)* |
| `create` | `Request` (`id_cliente` obligatorio de facto, `id_autorizado` opcional = **ID del registro en `cliente_autorizados`**) | **ninguna** (usa `findOrFail`; el autorizado se resuelve con `ClienteAutorizado::with('cliente')->where('id',…)->where('id_titular', cliente.id)->first()` ✅ corregido) | `Cliente` (con `sucursal`, `creditos.cuotas`, `novedades.user`, `autorizados`), `ClienteAutorizado`, `DB::table('presentaciones')->max('id')` | `view('creditos.create')` con `presentacionActual`, `vencimientos` (4 buckets), `limiteTotal`, `limiteDisponible`, `aFavor`, totales |
| `store` | `Request` | `id_cliente` required\|exists:clientes,id · `id_sucursal` required\|exists:sucursales,id · `id_presentacion` required\|exists:presentaciones,id · `tipo_credito` required\|in:C,D,R · `importe_original` required\|numeric\|min:1 · `cantidad_cuotas` required\|integer\|min:1 · **`id_autorizado` nullable\|integer + `Rule::exists('cliente_autorizados','id')->where('id_titular', id_cliente)`** ✅ *(corregido)* | `SucursalIndice` (`firstOrCreate` por `id_sucursal`+`tipo_credito`, `increment('ultimo_indice')`), `Sucursal`, `Presentacion::findOrFail`, `Credito::create`, `Cuota::create` ×N — todo dentro de `DB::transaction` | `redirect()->route('creditos.index')->with('success')` |
| `imprimirPagare` | `Credito $credito` | ninguna | `Credito` (`load cliente, sucursal, autorizado.cliente, cuotas`) + `\NumberFormatter('es', SPELLOUT)` | `view('creditos.pagare')` con `totalMonto = cantidad_cuotas × valor_cuota_ref`, `concepto` (`R` ⇒ REFINANCIACION, resto ⇒ COMPRAS), `montoEnLetras` |

**Detalle del alta (cálculo de cuotas — `store`):**

1. `$indice->increment('ultimo_indice')` ⇒ correlativo por (sucursal, tipo).
2. `$codigoCredito = $sucursal->codigo_sucursal . $tipo_credito . $nuevoNumeroIndice` (ej. `IC125`) → columna `creditos.credito`.
3. `$interesPorcentaje = $presentacion->tasa_cuotas ?? 0` → interés directo simple. ⚠ *`tasa_cuotas` **no existe** como columna en `presentaciones` (las reales son `interes_credito`/`interes_mora`), por lo que hoy el interés financiado es 0 — pendiente de migrar (§4.2).*
   `$valorCuotaRef = round($importe_original * (1 + $interes/100) / $cantidad_cuotas, 2)`.
4. Cabecera `Credito` con `id_autorizado = $request->input('id_autorizado') ?: null` ✅ *(corregido)*: `Credito::autorizado()` es `belongsTo(ClienteAutorizado::class,'id_autorizado')`, por lo que **antes** se guardaba un `clientes.id` en una FK a `cliente_autorizados` (autorizado inexistente/incoherente); ahora la FK recibe sólo el ID validado de la autorización del titular, o `NULL` si el crédito es del propio titular.
5. Cascada: por cada `i` en `1..cantidad_cuotas` crea `Cuota(num_cuota=i, valor_base=valorCuotaRef, intereses=0, entrego=0, saldo_actual=valorCuotaRef, fecha_ultimo_envio=null, estado_bancario=null)`.
6. No se persiste `fecha_vencimiento` por cuota (pendiente de diseño). El **lote origen no necesita persistirse aparte**: ya vive en la FK `creditos.id_presentacion` (§3.1); no existe la columna `presentacion_origen`.

**Vinculación de autorizados (corregida ✅):** el formulario envía `id_autorizado` = **`cliente_autorizados.id`**
(en `clientes.index` se arma con `$autorizado->pivot->id`; `Cliente::autorizados()` ahora expone ese `pivot->id`);
`create()` lo resuelve con `ClienteAutorizado::with('cliente')->where('id', …)->where('id_titular', $cliente->id)->first()`
(sólo autorizaciones vigentes del titular) y lo muestra en `creditos/create` como `$autorizado->cliente->nombre`;
`store()` lo **persiste** previa validación `Rule::exists('cliente_autorizados','id')->where('id_titular', id_cliente)`.
Un crédito del propio titular se guarda con `id_autorizado = NULL`.

### 2.2 `ClienteController` (8 métodos públicos + 1 privado)

| Método | Parámetros | Validación | Modelos | Retorno |
|---|---|---|---|---|
| `index` | `Request` (`search`, `id_sucursal`, `id_localidad`, `id_provincia`, `suspendido`, `estado_banco`, `has_saldo`) | ninguna | `Cliente` (`with localidad.provincia, sucursal, autorizados, titulares`), `Sucursal`, `Localidad`, `Provincia`, `EstadoBanco` | `view('clientes.index')` · `orderBy('nombre')->paginate(15)->withQueryString()` |
| `create` | — | ninguna | `Sucursal::all()`, `Localidad::all()` | `view('clientes.create')` |
| `store` | `Request` | `dni` required\|unique:clientes,dni\|integer · `nombre` required\|max:191 · `id_sucursal` required\|exists · `medio_pago_preferido` required\|in:0,1 · `cbu` required_if:medio_pago_preferido,1 + size:22 + closure `validarCBU` · `tipo_haber` nullable\|integer · `limite` required\|numeric\|min:0 · `max_cuotas` required\|in:1,2,3,4 · `direccion` required\|max:191 · `id_localidad` required\|exists · `email` nullable\|email · `tel1` required · `tel2` nullable · familiar (`familiar`, `tipo_familiar`, `direccion_familiar`, `id_localidad_familiar`, `email_familiar`, `tel_familiar`) · laborales (`trabajo`, `direccion_trabajo`, `id_localidad_trabajo`, `tel_trabajo`) · `obs` — mensaje custom para `cbu.required_if` | `Cliente::create($request->all() + suspendido + estado_banco=null + fecha_estado_banco=null)` | `redirect()->route('clientes.index')->with('success')` |
| `update` | `Request`, `Cliente $cliente` | idénticas a `store` pero `dni` required\|unique:clientes,dni,**{id}** | `Cliente::update($data)` con `suspendido` normalizado (`== 1 ? 1 : 0`) | `redirect()->route('clientes.index')->with('success')` |
| `edit` | `Cliente $cliente` | ninguna | `Sucursal::all()`, `Localidad::orderBy('nombre')` | `view('clientes.edit')` |
| `destroy` | `Cliente $cliente` | regla de negocio: si `$cliente->creditos()->exists()` **no borra** | `Cliente` (`creditos`) | `redirect()->route('clientes.index')->with('error')` o `->with('success')` |
| `estadoCuenta` | `Cliente $cliente` | ninguna | `Cliente` (`load creditos.cuotas, creditos.sucursal, autorizados`) | `view('clientes.estado-cuenta')` con totales de **créditos abiertos**: `totalValorBase`, `totalIntereses`, `totalEntregado`, `totalAPagar`, `totalRestante = base + intereses − entregado − aPagar` |
| `show` | `Cliente $cliente` | ninguna | `Cliente` (`load autorizados, novedades.user, sucursal`) | `view('clientes.show')` |
| `validarCBU` (**private**) | `string $cbu` | `preg_match('/^[0-9]{22}$/')` + dígitos verificadores BCRA: bloque1 `[7,1,3,9,7,1,3]`, bloque2 `[3,9,7,1,3,9,7,1,3,9,7,1,3]`, DV = `(10 − suma % 10) % 10` | — | `bool` |

Notas: `store`/`update` usan `$request->all()` (mass assignment sin `$validated`); el flag `suspendido` se
normaliza manualmente porque el checkbox no llega cuando está desmarcado. `estado_banco`/`fecha_estado_banco`
se fuerzan a `null` en el alta y **sólo** se cambian fuera de este controlador (hoy: nunca, ver §3.3).

### 2.3 `PresentacionController` (2 métodos)

| Método | Parámetros | Validación | Modelos | Retorno |
|---|---|---|---|---|
| `index` | — | ninguna | `User::with('sucursal')`, `Presentacion::orderBy('numero','desc')->paginate(15)`, `$actual = ...->first()` | `view('sistema.presentaciones.index')` |
| `cambiar` | `Request` (**POST**) | `interes_credito` required\|numeric\|min:0 · `interes_mora` required\|numeric\|min:0 | `Presentacion` (`whereNull('fecha_fin')`), `DB::table('cuotas')` + INNER JOIN `creditos` **+ INNER JOIN `presentaciones` por `creditos.id_presentacion`** ✅, `Carbon` | `redirect()->route('sistema.presentaciones.index')` ✅ *(corregido; antes apuntaba al nombre inexistente `presentaciones.index`)* o `back()->with('error')` |

### 2.4 `EstadoBancoController` (6 métodos, **sin rutas**)

`index` (`Request->search` por `codigo`/`descripcion`, `paginate(15)`), `create`, `store`
(`codigo` required\|max:10\|unique:estados_banco,codigo + `descripcion` required\|max:191, con
`strtoupper(trim())`), `edit`, `update` (misma regla con `unique...,{id}`), `destroy`.
Modelo único: `EstadoBanco` (`$table = 'estados_banco'`). Vistas referenciadas: `estados_banco.*` (no enrutadas).

### 2.5 `SucursalController` (8 métodos)

- `updateGlobal(Request)`: valida `id_sucursal` required\|exists, guarda `session(['sucursal_activa_id' => $id])`,
  `session()->save()`, `return back()`. Es el único método con lógica y su ruta (`POST /cambiar-sucursal`) ahora
  exige `auth` ✅ *(corregido)*.
- `index/create/store/show/edit/update/destroy`: **cuerpos vacíos** (stubs de `Route::resource`), retornan `null`.
- `cambiar`: **no existe**; la ruta `sucursales.cambiar` que lo invocaba fue eliminada ✅.

## 3. Flujos Operativos Críticos

### 3.1 Alta y liquidación de créditos

**Alta (`POST /creditos` → `CreditoController@store`)** — secuencia real:

1. `validate()`: cliente/sucursal/presentación existentes, `tipo_credito ∈ {C,D,R}`, `importe_original ≥ 1`,
   `cantidad_cuotas ≥ 1`. **No** se comparan contra el legajo: no se valida `importe_original ≤ cliente.limite`,
   ni `cantidad_cuotas ≤ cliente.max_cuotas` (1..4), ni el estado `suspendido`, ni la presentación abierta.
2. `DB::transaction`: correlativo `SucursalIndice` (por `id_sucursal` + `tipo_credito`)
   → `increment('ultimo_indice')` → código `codigo_sucursal . tipo . indice` (ej. `IC125`).
3. Tasa: `Presentacion::findOrFail($request->id_presentacion)->tasa_cuotas` (interés directo simple) ⚠ *columna inexistente ⇒ interés 0 (§4.2).*
   `valor_cuota_ref = round(importe_original × (1 + tasa/100) / cantidad_cuotas, 2)`.
4. `Credito::create` + una `Cuota` por mes (`num_cuota` 1..N) con `valor_base = saldo_actual = valor_cuota_ref`,
   `intereses = entrego = 0`, `estado_bancario = null`, `fecha_ultimo_envio = null`.
5. `redirect()->route('creditos.index')->with('success')`.

⚠ **Huecos verificados del alta:** no se guarda fecha de vencimiento por cuota (la exigibilidad futura se deriva
del lote: `presentaciones.numero + cuotas.num_cuota`, ver §3.2/§3.3 — el lote origen ya vive en la FK
`creditos.id_presentacion`, **no** en una columna `presentacion_origen`); la cuota de referencia no contempla mora
ni redondeo de la última cuota (todas iguales ⇒ descuadre de centavos); no se valida `cliente.limite`,
`cliente.max_cuotas` ni `suspendido`.
✅ **Corregido:** `id_autorizado` ya no recibe un `clientes.id` sino el ID validado de `cliente_autorizados`
(§2.1, §3.1).

**Vista previa del alta (`GET /creditos/nuevo?id_cliente=&id_autorizado=` → `create`)**:
`$presentacionActual = session('presentacion_actual') ?? DB::table('presentaciones')->max('id') ?? 102` ⚠ — nadie
escribe `presentacion_actual` (sólo se escribe `sucursal_activa_id` en `SucursalController@updateGlobal`), así que
siempre cae al **max(id)** aunque el resto del sistema numera por `presentaciones.numero` (ej. 500/510). Construye
`$vencimientos[1..4]` agrupando cuotas con saldo por lote
(`presentacionCuota = credito.presentacion.numero + cuota.num_cuota`), descartando las que ya no deben nada
(`saldoPendiente = max(0, valor_base − entrego)`), y calcula `limiteDisponible = max(0, (cliente.limite ?? 800000) − Σ importe_original de créditos abiertos)`.

**Liquidación / cobro (lo único implementado: `POST /creditos/{cuota}/pagar`)**
(`CreditoController@pagarCuota`):

```php
$cuota->update([
    'entrego'      => $request->monto,                                   // ⚠ sin validar (negativos/0 permitidos)
    'fecha_cobro'  => now(),
    'medio_pago'   => $request->medio,
    'saldo_actual' => ($cuota->valor_base + $cuota->intereses) - $request->monto,  // ⚠ puede quedar negativo
]);
return back()->with('success', 'Pago registrado correctamente');  // ✅ clave esperada por <x-notification-modal />
```

Reglas de negocio ausentes: pago parcial acumulativo (sobrescribe `entrego`), imputación a intereses antes que a
capital, cierre del crédito (`creditos.fecha_cancelacion`) al saldar la última cuota, y registro de movimiento
(`referencia_pago` nunca se escribe). ✅ El flash ya usa la clave `success`, por lo que el toast global
(`<x-notification-modal />`, ver `ui-guidelines.md` §9) muestra la confirmación de pago en pantalla.

**Cancelación:** ningún controlador escribe `fecha_cancelacion`; sólo se lee para separar abiertos/cancelados
(`creditos.index`, `clientes.estadoCuenta`). Los totales de estado de cuenta se calculan **en memoria**
(`$creditosAbiertos->flatMap->cuotas`), no en SQL.

### 3.2 Generación de lote bancario (P10→P0, tope y ranking de cuotas)

**Estado: NO IMPLEMENTADO.** Verificado por búsqueda en `app/` y `routes/`: no existen las cadenas `P10`, `R02`
ni `ACE` en código (sólo el comentario de `Cuota::$fillable`), no hay controlador, servicio, comando, job ni
ruta de "lote"/"rendición", no hay `routes/api.php` y no hay exportador. El botón *EXPORTAR EXCEL* de la vista
`creditos/index` es decorativo (sin `wire:`/`@click`/ruta).

**Piezas ya preparadas en el modelo (base para implementarlo):**

| Pieza | Definición real |
|---|---|
| `Presentacion` = **lote** | `numero` ("Número de lote o presentación, ej. 500, 510"), `fecha_inicio`, `fecha_fin`, **`interes_credito`**, **`interes_mora`** (columnas reales, ya presentes en `$fillable` ✅; `tasa_cuotas`/`tasa_mora` no existen como columnas). Lote abierto ⇔ `fecha_fin IS NULL`. |
| Lote de una cuota | `Cuota::getLoteCalculadoAttribute()` ⇒ `credito.presentacion.numero + cuota.num_cuota` (⭐ **definición canónica**, usada por `CreditoController@create`). `Cuota::getLotePresentacionAttribute()` ⇒ ✅ **DEPRECADO**: ahora delega en `lote_calculado` (antes sumaba el objeto relación a un entero ⇒ error fatal) y sólo se conserva por compatibilidad. |
| Código de gestión | `Cuota::getCodigoGestionAttribute()` ⇒ `credito.full_identificador . num_cuota` (ej. `C00001234T1`); `Credito::getFullIdentificadorAttribute()` ⇒ `tipo + pad(credito,8,'0') + sucursal` (ej. `R00001234T`). Se usan como clave de envío/conciliación. |
| Campos bancarios de la cuota | `banco_envio`, `fecha_ultimo_envio`, `estado_bancario` (comentado "ACE, R10, R08, etc."), `obs_devolucion`, `referencia_pago`. Ninguno se escribe hoy salvo `fecha_ultimo_envio = null` / `estado_bancario = null` en el alta. |
| Catálogo de estados | Tabla/modelo `EstadoBanco` (`codigo`, `descripcion`), consumida en `clientes.index`; su CRUD (`EstadoBancoController`) está huérfano. No hay FK ni validación `in:` de los códigos usados en `cuotas.estado_bancario`. |

**Tope de presentación (lo único parametrizado hoy):**

- `clientes.limite` (con fallback duro `800000` en `CreditoController@create`) y
  `limiteDisponible = max(0, limite − Σ importe_original de créditos abiertos)`; se muestra en pantalla pero
  **no se valida** en `store`.
- `clientes.max_cuotas ∈ {1,2,3,4}` (validado en el legajo, **no aplicado** al alta de crédito).
- No existe un tope por lote/presentación (máximo de cuotas o importe a presentar), ni ranking por antigüedad de
  décadas: la única priorización existente es el agrupamiento en 4 buckets de `create()` (vencidas/actual, +1, +2, +3).

**Recomendación de diseño (mínima, respetando lo existente):** derivar el lote origen por la FK
`creditos.id_presentacion` (INNER JOIN con `presentaciones`) junto con `Cuota.num_cuota`, ordenar la presentación
por lote ASC y `num_cuota ASC` (equivalente a P10→P0: décadas = lote/10, la más antigua primero) y topar el lote
con dos columnas nuevas en `presentaciones` (ej. `tope_cuotas`/`tope_importe`). ✅ Ya **no** hace falta persistir
`creditos.presentacion_origen`: la definición de lote quedó unificada en `Cuota::lote_calculado` y
`PresentacionController@cambiar` consulta el lote origen con el JOIN correcto (ver §3.3).

### 3.3 Procesamiento de rendiciones y reversas (ACE / R10 / R02, saldo y suspensión)

**Estado: NO IMPLEMENTADO.** No existe controlador ni ruta de rendición/reversa, `DevolucionBancaria` es una clase
vacía y **ningún** punto del código escribe `cuotas.estado_bancario`, `cuotas.banco_envio`, `cuotas.obs_devolucion`,
`clientes.estado_banco` ni `clientes.fecha_estado_banco`. Por lo tanto los estados ACE/R10/R02 sólo existen como
catálogo (`estados_banco`) y como columna libre en `cuotas`.

**Lo único que hoy modifica saldos es `PresentacionController@cambiar` (cierre de lote), que además tiene fallas
críticas verificadas:**

```php
$presentacionActual = Presentacion::whereNull('fecha_fin')->orderBy('numero','desc')->first();
if (!$presentacionActual) return back()->with('error', 'No hay una presentación abierta actualmente.');

$diasTranscurridos = Carbon::parse($presentacionActual->fecha_inicio)->diffInDays(now());
if ($diasTranscurridos < 7) return back()->with('error', "No se puede cerrar. Han pasado solo {$diasTranscurridos} días...");

DB::beginTransaction();
$ahora = now();
$tasaMora = (float) ($presentacionActual->interes_mora ?? $request->interes_mora);  // ✅ cast a float (antes NULL ⇒ SQL inválido)
$presentacionActual->update(['fecha_fin' => $ahora]);                               // 1) cierra lote
Presentacion::create([... 'interes_credito', 'interes_mora' ...]);                  // 2) abre lote n+1 (columnas ya en $fillable ✅)
DB::table('cuotas')->where('saldo_actual','>',0)->update([                          // 3) mora sobre impagas
    'intereses'    => DB::raw("intereses + (saldo_actual * {$tasaMora})"),
    'saldo_actual' => DB::raw("saldo_actual + (saldo_actual * {$tasaMora})"),
]);
DB::table('cuotas')
    ->join('creditos', 'cuotas.id_credito', '=', 'creditos.id')
    ->join('presentaciones', 'creditos.id_presentacion', '=', 'presentaciones.id')  // ✅ lote ORIGEN por FK relacional
    ->whereRaw('(presentaciones.numero + cuotas.num_cuota) = ?', [$nuevaPresentacion->numero])
    ->where('cuotas.saldo_actual','=',0)                                             // 4) vuelve exigibles las del nuevo ciclo
    ->update(['cuotas.saldo_actual' => DB::raw('cuotas.valor_base')]);
DB::commit();
return redirect()->route('sistema.presentaciones.index')->with('success', ...);     // ✅ nombre real
```

| # | Falla detectada | Estado tras la corrección |
|---|---|---|
| 1 | La ruta `sistema.presentaciones.cambiar` era **inalcanzable**: `Route::resource('presentaciones')` registraba `GET presentaciones/{presentacion}` (show) antes, y `cambiar` quedaba capturado como `{presentacion}` ⇒ llamaba a `PresentacionController@show`, que **no existe**. | ✅ **CORREGIDO**: la ruta es `POST /sistema/presentaciones/cambiar`, declarada **antes** del listado, y el `Route::resource('presentaciones')` fue sustituido por la ruta explícita `GET /sistema/presentaciones`. El cierre de lote es alcanzable. |
| 2 | Estaba declarada como **GET con efectos de escritura** (sin CSRF, sin confirmación server-side). | ✅ **CORREGIDO** (POST + CSRF). La confirmación `onsubmit` de la vista sigue vigente. *Pendiente: autorización server-side por permiso/rol.* |
| 3 | `interes_credito` / `interes_mora` no estaban en `Presentacion::$fillable` ⇒ el `create()` descartaba las tasas y el lote quedaba con tasas `NULL`. | ✅ **CORREGIDO**: ambas columnas reales fueron agregadas a `$fillable`. ⚠ *Pendiente: `CreditoController@store` sigue leyendo `tasa_cuotas` (que no existe) ⇒ interés de financiación = 0; debe migrarse a `interes_credito`.* |
| 4 | `$presentacionActual->interes_mora` devolvía `null` y se interpolaba en `DB::raw("... * {$tasaMora})")` ⇒ `(saldo_actual * )` (SQL inválido) ⇒ excepción y `rollBack()`. | ✅ **CORREGIDO**: `$tasaMora = (float) ($presentacionActual->interes_mora ?? $request->interes_mora)`. La mora se aplica siempre. |
| 5 | La exigibilidad usaba `creditos.presentacion_origen` (**columna inexistente**). | ✅ **CORREGIDO**: INNER JOIN `presentaciones` por `creditos.id_presentacion` + `(presentaciones.numero + cuotas.num_cuota) = ?`. |
| 6 | `redirect()->route('presentaciones.index')` apuntaba a un nombre inexistente ⇒ error 500 **después** del `commit()`. | ✅ **CORREGIDO**: `redirect()->route('sistema.presentaciones.index')`. |
| 7 | `$tasaMora` se toma del lote que se cierra, no del nuevo; los parámetros validados (`interes_mora`) no se usaban. | ⚠ **PENDIENTE (decisión de negocio)**: se mantiene la tasa del lote que se cierra, con respaldo en `$request->interes_mora`. Queda documentado que la mora del período cerrado usa la tasa vigente de ese período. |

**Definición de lote (unificada ✅):** lote = `presentaciones.numero` del **lote origen** (FK relacional
`creditos.id_presentacion`) `+ Cuota.num_cuota`, con cuotas numeradas desde 1. Es exactamente la definición del
accesor oficial `Cuota::lote_calculado`, de modo que el código, la consulta de cierre y las especificaciones
(`models.md` §2.1.1 y §3.4) quedan alineados. **No existe** (ni se requiere) la columna `creditos.presentacion_origen`.

**Reversas y suspensión preventiva — diseño sugerido (no existe código):** al recibir una rendición, mapear el
código devuelto por el banco a `cuotas.estado_bancario` (`codigo` de `estados_banco`); una reversa debería reponer
el saldo (`saldo_actual += entrego`, `entrego = 0`, limpiar `fecha_cobro`/`medio_pago`), guardar el motivo en
`obs_devolucion` y, según política (ej. N reversas en M lotes), setear `clientes.suspendido = 1` junto con
`clientes.estado_banco` + `fecha_estado_banco` — que son exactamente los campos que `ClienteController@store`
fuerza a `null` y el filtro de `clientes.index` ya sabe leer.

## 4. Resumen de Riesgos Priorizados

### 4.1 Corregido en esta revisión (detalle en §0)

- **12 rutas muertas** que respondían 500 (clientes.creditos/movimientos, creditos.edit/update/destroy,
  creditos.cuotas.*, sucursales.cambiar y endpoints del resource de presentaciones) — eliminadas.
- **Cierre de lote operativo** (§3.3): ruta alcanzable (POST con CSRF), tasas en `$fillable`, mora con SQL válido,
  JOIN al lote origen por `id_presentacion` y redirect al nombre correcto.
- **`id_autorizado` con FK coherente** (`cliente_autorizados.id`) y cadena de vinculación completa
  (`clientes.index` → `creditos.create` → `store`).
- **`pagarCuota` con flash `success`**, compatible con `<x-notification-modal />`.
- **`POST /cambiar-sucursal` protegido con `auth`**; `GET /login` lo sirve el controlador Breeze (el closure de
  `web.php` quedó como código muerto).
- **`shadow-2xs` → `shadow-sm`** en `clientes/show.blade.php:226` y `creditos/create.blade.php:214` (Tailwind v3).
- **Definición de lote unificada** (`Cuota::lote_calculado`; `lote_presentacion` deprecado) y documentada en
  `models.md` §2.1.1/§3.4.

### 4.2 Riesgos pendientes priorizados

1. **Alta de crédito sin controles de límite**: no valida `cliente.limite`, `cliente.max_cuotas` (1..4),
   `suspendido`, ni que la presentación esté abierta (`fecha_fin IS NULL`).
2. **`pagarCuota` sin validación**: `monto`/`medio` sin reglas ⇒ admite negativos/0 y deja `saldo_actual` negativo;
   no cierra el crédito (`fecha_cancelacion`) al saldar la última cuota ni escribe `referencia_pago`
   (pago parcial acumulativo e imputación a intereses antes que a capital, ausentes).
3. **Interés de financiación en 0**: `store` lee `$presentacion->tasa_cuotas` (columna inexistente ⇒ `?? 0`);
   debe migrarse a `presentaciones.interes_credito`.
4. **Sin vencimiento explícito por cuota**: la exigibilidad depende sólo del lote
   (`presentaciones.numero + cuotas.num_cuota`).
5. **Autorización**: sólo `auth`; no hay permisos/roles verificados en backend (incluido el cierre de lote, que la
   vista condiciona por permiso pero el servidor no valida).
6. **Código huérfano**: `EstadoBancoController` sin rutas (con `redirect()->route('estados-banco.index')` inexistente),
   `DevolucionBancaria` vacío, `resources/views/sistema/presentaciones/cambiar.blade.php` vacío, botón EXPORTAR EXCEL
   decorativo, `SucursalController` con stubs de resource y closure `/login` muerto en `web.php`.

**Sin implementar (y sin andamiaje):** generación de lote bancario P10→P0, tope por presentación, ranking de cuotas
por décadas, procesamiento de rendiciones y reversas ACE/R10/R02, suspensión preventiva automática y `routes/api.php`.
