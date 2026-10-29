# Regla General de UI: Navegación por Modales y Acceso a Fichas de Entidades (No abrir nuevas pestañas)

## 1. Principio Fundamental
Para no entorpecer la navegación del operador ni interrumpir flujos de trabajo en ejecución (como el Punto de Venta / Facturación, Cobranzas o arqueos de caja), **está prohibido abrir nuevas pestañas o ventanas del navegador (`target="_blank"`) para comprobantes, reportes, previsualizaciones o edición de entidades**.

Cualquier visualización, impresión o detalle secundario debe realizarse a través de un **modal interactivo embebido** (`?embed=1`), permitiendo al usuario revisar o accionar y volver exactamente al mismo punto de su pantalla sin perder el contexto.

---

## 2. Reglas de Interacción por Referencia a Entidades

En todos los módulos del sistema (Ventas, Créditos, Cobranzas, Listados, Reportes, Caja):

1. **Referencia a Cliente (`Nombre`, `DNI` o `Nro. Cuenta`):**
   - Al hacer clic en el nombre o identificación del cliente, debe abrirse un modal interactivo con su **Ficha / Legajo Completo** (`/clientes/{id}?embed=1`).
   - El operador debe poder consultar el estado financiero, historial de cuotas y **editar los datos del legajo** dentro del modal, reflejándose los cambios al cerrarlo sin recargar la página principal ni perder carritos o selecciones.

2. **Referencia a Crédito (`N° Crédito` o `Identificador`):**
   - Al hacer clic en un crédito, debe abrirse un modal con el **Detalle del Crédito** (`/creditos/{id}?embed=1`), mostrando la tabla de cuotas, amortizaciones, recibos aplicados y estado de cancelación.

3. **Referencia a Factura / Venta (`N° Comprobante`, `Factura` o `Ticket`):**
   - Al hacer clic en un comprobante, debe abrirse un modal con el **Detalle de la Factura** (`/ventas/{id}?embed=1`), con acceso directo a la reimpresión térmica o formato A4 en modal.

---

## 3. Reglas de Impresión y Visores de Comprobantes

1. **Visor de Documentos en Modal:**
   - Todo comprobante imprimible (Ticket 80mm, Factura A4, Pagaré de Crédito, Recibo de Cobranza) debe cargarse dentro de un modal con iframe a pantalla completa (`h-[92vh]`).
2. **Controles Obligatorios del Modal:**
   - **Botón de Cierre Visible:** Debe incluir siempre una cruz `✕` destacada en la esquina superior derecha y soporte para tecla <kbd>Escape</kbd>.
   - **Botón de Impresión Directa:** Debe incluir un botón `🖨️ Mandar a Imprimir` que envíe la orden de impresión directamente a la impresora del sistema (`iframe.contentWindow.print()`).
   - **Opción Secundaria / Fallback:** Enlace opcional discreto para "Abrir en nueva ventana" solo en caso de requerimiento excepcional del hardware.
