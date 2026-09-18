<?php
// Manual 1 (UF1846), actividad guiada 1.31: leer JSON sin conexión todavía.
// El JSON está escrito directamente en el archivo; en el Manual 2 se sustituirá
// por una respuesta obtenida mediante cURL a un servicio real.
declare(strict_types=1);

$respuesta = '{
    "ciudad": "Barcelona",
    "temperatura": 27,
    "pais": "España",
    "confirmado": true,
    "lluvia": true
}';

$datos = json_decode($respuesta, true);

echo $datos['ciudad'];
echo $datos['temperatura'];
echo $datos['pais'];

// Nota de la actividad: "lluvia" también existe en $datos, pero todavía no la
// mostramos con echo. El ejercicio pide observar que el dato está disponible
// en el array aunque no se utilice todavía.
