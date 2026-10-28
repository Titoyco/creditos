# Tiendas La Marina — Sistema de Gestión Comercial, Facturación y Créditos

Sistema integral desarrollado en **Laravel**, **Blade**, **Tailwind CSS v3** y **Alpine.js** para la administración comercial, facturación en punto de venta (POS), control de stock multiesucursal, gestión de créditos personales y control estricto de cajas y cierres Z.

---

## 🏢 Sucursales y Jurisdicciones Fiscales

El sistema gestiona de forma diferenciada la facturación y los datos fiscales según la sucursal activa:

1. **Sucursal Chubut (Trelew - Centro):**
   - **Nombre de Fantasía:** La Marina
   - **Razón Social:** Tiendas la Marina SAS
   - **CUIT:** 30-71699602-2
   - **Domicilio Comercial:** Rivadavia 249, Trelew, Chubut
   - **Página Web:** [www.tiendaslamarina.com.ar](https://www.tiendaslamarina.com.ar)

2. **Sucursal Tierra del Fuego (Ushuaia):**
   - **Nombre de Fantasía:** La Marina
   - **Razón Social:** BARZINI GUILLERMO ENRIQUE
   - **CUIT:** 20-11474524-4
   - **Domicilio Comercial:** San Martín 1255, Ushuaia, Tierra del Fuego
   - **Página Web:** [www.tiendaslamarina.com.ar](https://www.tiendaslamarina.com.ar)

---

## 🚀 Módulos Principales del Sistema

### 1. Punto de Venta (POS) & Facturación
- Emisión ágil de comprobantes (Factura B, Ticket, etc.) con selección de medios de pago:
  - Efectivo
  - Tarjeta de Débito
  - Tarjeta de Crédito
  - Crédito Personal / Cuenta Corriente
  - Transferencia Bancaria / QR
  - Otros
- **Integración con Caja:** Obliga a que la caja de la sucursal esté abierta en el día. Si no está abierta o quedó una sesión de jornada anterior pendiente, bloquea la venta preventivamente.
- **Prevención de Stock Negativo:** Bloquea ventas de artículos con stock en cero y ofrece ventana de recuento rápido físico en mostrador para regularizar el inventario sin interrumpir la operación.

### 2. Control de Cajas, Turnos y Cierres Z
- **Apertura de Jornada:** Ingreso obligatorio del fondo inicial en efectivo antes de operar.
- **Movimientos Manuales Justificados:**
  - **Retiros / Egresos:** Para pago de fletes, insumos o adelantos con motivo obligatorio y comprobante opcional.
  - **Ingresos Extras:** Para sencillo adicional o cobros directos con motivo obligatorio.
- **Arqueo y Cierre Z:**
  - Cálculo automático:
    $$\text{Efectivo Teórico} = \text{Fondo Inicial} + \text{Ventas Efectivo} + \text{Ingresos} - \text{Retiros}$$
  - Ingreso de dinero contado y determinación en tiempo real del **Fallo de Caja** (sobrante o faltante).
- **Comprobantes Imprimibles:** Emisión de Cierre Z en formato **Ticket Térmico de 80mm** y hoja **A4**.
- **Histórico por Medios de Pago:** Reporte consolidado por rango de fechas y sucursal.

### 3. Créditos Personales & Cuentas Corrientes
- Registro y evaluación crediticia de clientes (titulares y autorizados).
- Control de límites disponibles, cálculo de cuotas e intereses de financiación.
- Cobranza de cuotas con recibos oficiales, compensación de saldos y auto-cancelación.

### 4. Control de Inventario y Stock Multiesucursal
- Catálogo de productos, códigos de barra, categorías y subcategorías.
- Control de stock por sucursal con traslados inter-sucursales, remitos de proveedor y ajustes por recuento.

### 5. Copias de Seguridad (Backup y Restauración)
- Generación de respaldos íntegros de la base de datos descargables en `.zip`.
- **Restauración Protegida:** Requiere permisos de administrador o `superuser` y ejecuta automáticamente un auto-backup preventivo antes de sobrescribir cualquier tabla.

### 6. Seguridad y Despliegue
- **Login Seguro:** Acceso restringido por usuario y contraseña encriptada (Bcrypt) sin listas desplegables expuestas.
- **Superusuario Secreto:** Usuario de rescate `superuser` invisible en listados administrativos.
- **Forzado de HTTPS:** Regla en `AppServiceProvider` para entorno de producción en `https://app.tiendaslamarina.com.ar`.

---

## 🛠️ Especificaciones Técnicas

La documentación técnica detallada se encuentra en la carpeta [`specs/`](specs/):
- [`specs/models.md`](specs/models.md): Modelos Eloquent, esquemas de base de datos, relaciones y reglas de integridad.
- [`specs/routes-and-controllers.md`](specs/routes-and-controllers.md): Tabla completa de rutas, controladores, validaciones y lógica de negocio.
- [`specs/ui-guidelines.md`](specs/ui-guidelines.md): Lineamientos visuales, componentes Blade, diseño responsivo, Alpine.js y formatos de impresión.

---

## 💻 Despliegue en Servidor

Para sincronizar los cambios locales con el servidor de producción:

1. **En la máquina de desarrollo (Local):**
   ```bash
   git add .
   git commit -m "feat: descripción de los cambios"
   git push origin main
   ```

2. **En el servidor de producción (`app.tiendaslamarina.com.ar`):**
   ```bash
   git pull origin main
   php artisan migrate --force
   php artisan optimize:clear
   ```
