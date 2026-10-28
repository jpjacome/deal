@php($s = \App\Models\Setting::values())
**Versión:** {{ $s['privacy_version'] }}

## Responsable del tratamiento

{{ config('app.name') }}, con domicilio en {{ $s['venue_address'] }}. Contacto para temas de datos personales: {{ $s['contact_email'] ?: 'el correo indicado en nuestra página de contacto' }}.

## Qué datos tratamos

Nombre, correo electrónico, número de celular, empresa (opcional), los datos de tus reservas (espacio, fecha, horario, número de personas, comentarios) y, si lo aceptas, tu preferencia de recibir avisos por WhatsApp.

## Para qué los usamos

1. Gestionar tus reservas: confirmarlas, recordártelas, permitirte cambiarlas o cancelarlas.
2. Enviarte avisos por correo y, solo si lo autorizas, por WhatsApp.
3. Atender tus consultas.
4. Después de tu visita, pedirte tu opinión y, si nos visitas seguido, contarte sobre nuestros planes. Puedes pedir que no te escribamos más en cualquier momento.

## Base legal

Tu consentimiento, que das al aceptar esta política al reservar, y la ejecución de la reserva que solicitas. El aviso por WhatsApp requiere un consentimiento separado y opcional.

## Con quién los compartimos

Solo con proveedores que nos ayudan a prestar el servicio, como encargados del tratamiento: el servicio de alojamiento del sitio, el proveedor de envío de correos y, si aceptas los avisos por WhatsApp, Meta Platforms (WhatsApp Business). Algunos de estos proveedores pueden tratar los datos fuera del Ecuador, con las garantías que exige la ley. No vendemos tus datos.

## Cuánto tiempo los conservamos

Mientras seas cliente y durante el tiempo necesario para cumplir obligaciones legales. Después los eliminamos o anonimizamos.

## Tus derechos

Puedes solicitar el acceso, rectificación, actualización, eliminación, oposición, portabilidad y suspensión del tratamiento de tus datos, así como retirar tu consentimiento, escribiendo a {{ $s['contact_email'] ?: 'nuestro correo de contacto' }}. Responderemos dentro de los plazos de la Ley Orgánica de Protección de Datos Personales. También puedes presentar un reclamo ante la Superintendencia de Protección de Datos Personales.

## Si no entregas tus datos

Sin nombre, correo y celular no podemos gestionar tu reserva en línea. Puedes reservar escribiéndonos directamente.
