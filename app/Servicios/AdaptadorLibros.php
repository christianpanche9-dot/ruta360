<?php
declare(strict_types=1);

require_once __DIR__ . '/ProveedorExterno.php';
require_once __DIR__ . '/ResultadoExterno.php';

/**
 * Proyecto final (Manual 4, 4.18-4.19): integración con una API externa
 * distinta a las ya usadas por Ruta360 (Open Library, buscador de
 * libros). Sigue el mismo contrato ProveedorExterno que meteorología y
 * transporte, y el mismo principio de fallar con claridad: cualquier
 * problema (timeout, respuesta inválida, sin resultados) se traduce en
 * un ResultadoExterno con disponible=false y un aviso legible, nunca en
 * una excepción no controlada ni en una página en blanco.
 */
final class AdaptadorLibros implements ProveedorExterno
{
    public function __construct(private array $config)
    {
    }

    public function nombre(): string
    {
        return 'libros';
    }

    /**
     * Contexto esperado: ['q' => string] (término de búsqueda).
     */
    public function consultar(array $contexto): ResultadoExterno
    {
        $inicio = hrtime(true);
        $termino = trim((string) ($contexto['q'] ?? ''));

        if ($termino === '') {
            return new ResultadoExterno(
                false,
                $this->nombre(),
                [],
                'servicio',
                'Escribe un título o autor para buscar.',
                0
            );
        }

        $limite = (int) ($this->config['limite_resultados'] ?? 10);
        $url = $this->config['url'] . '?' . http_build_query([
            'q' => $termino,
            'limit' => $limite,
        ]);

        [$cuerpo, $errorCurl, $estadoHttp] = $this->peticion($url);
        $duracion = (int) ((hrtime(true) - $inicio) / 1_000_000);

        if ($cuerpo === false || $errorCurl !== '') {
            error_log('AdaptadorLibros (búsqueda): ' . $errorCurl);
            return new ResultadoExterno(
                false, $this->nombre(), [], 'servicio',
                'No se ha podido contactar con el servicio de libros.', $duracion
            );
        }

        if ($estadoHttp !== 200) {
            return new ResultadoExterno(
                false, $this->nombre(), [], 'servicio',
                'El servicio de libros ha devuelto un error (código ' . $estadoHttp . ').', $duracion
            );
        }

        $contenido = json_decode((string) $cuerpo, true);
        if (!is_array($contenido) || !isset($contenido['docs']) || !is_array($contenido['docs'])) {
            return new ResultadoExterno(
                false, $this->nombre(), [], 'servicio',
                'El servicio de libros ha devuelto una respuesta no válida.', $duracion
            );
        }

        $libros = [];
        foreach (array_slice($contenido['docs'], 0, $limite) as $doc) {
            $libros[] = [
                'clave' => (string) ($doc['key'] ?? ''),
                'titulo' => (string) ($doc['title'] ?? 'Sin título'),
                'autores' => is_array($doc['author_name'] ?? null) ? $doc['author_name'] : [],
                'anio' => $doc['first_publish_year'] ?? null,
                'portada_id' => $doc['cover_i'] ?? null,
            ];
        }

        if ($libros === []) {
            return new ResultadoExterno(
                true, $this->nombre(), [], 'servicio',
                'No se han encontrado libros para "' . $termino . '".', $duracion
            );
        }

        return new ResultadoExterno(true, $this->nombre(), $libros, 'servicio', null, $duracion);
    }

    /**
     * Ficha de detalle de un libro a partir de su clave de obra
     * (formato "/works/OL..W"). Se valida el formato antes de construir
     * la URL para no convertir este adaptador en un proxy abierto hacia
     * cualquier ruta de openlibrary.org.
     */
    public function detalle(string $clave): ResultadoExterno
    {
        $inicio = hrtime(true);

        if (!preg_match('#^/works/OL[0-9]+W$#', $clave)) {
            return new ResultadoExterno(
                false, $this->nombre(), [], 'servicio',
                'Identificador de libro no válido.', 0
            );
        }

        $baseSitio = $this->config['sitio_url'] ?? 'https://openlibrary.org';
        $url = rtrim($baseSitio, '/') . $clave . '.json';

        [$cuerpo, $errorCurl, $estadoHttp] = $this->peticion($url);
        $duracion = (int) ((hrtime(true) - $inicio) / 1_000_000);

        if ($cuerpo === false || $errorCurl !== '') {
            error_log('AdaptadorLibros (detalle): ' . $errorCurl);
            return new ResultadoExterno(
                false, $this->nombre(), [], 'servicio',
                'No se ha podido contactar con el servicio de libros.', $duracion
            );
        }

        if ($estadoHttp !== 200) {
            return new ResultadoExterno(
                false, $this->nombre(), [], 'servicio',
                'No se ha encontrado el libro solicitado (código ' . $estadoHttp . ').', $duracion
            );
        }

        $contenido = json_decode((string) $cuerpo, true);
        if (!is_array($contenido)) {
            return new ResultadoExterno(
                false, $this->nombre(), [], 'servicio',
                'El servicio de libros ha devuelto una respuesta no válida.', $duracion
            );
        }

        $descripcion = $contenido['description'] ?? null;
        if (is_array($descripcion)) {
            $descripcion = $descripcion['value'] ?? null;
        }

        $datos = [
            'titulo' => (string) ($contenido['title'] ?? 'Sin título'),
            'descripcion' => is_string($descripcion) ? $descripcion : null,
            'materias' => is_array($contenido['subjects'] ?? null)
                ? array_slice($contenido['subjects'], 0, 8)
                : [],
            'portada_id' => $contenido['covers'][0] ?? null,
        ];

        return new ResultadoExterno(true, $this->nombre(), $datos, 'servicio', null, $duracion);
    }

    /**
     * @return array{0: string|false, 1: string, 2: int}
     */
    private function peticion(string $url): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => (int) ($this->config['connect_timeout'] ?? 3),
            CURLOPT_TIMEOUT => (int) ($this->config['timeout'] ?? 6),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: Ruta360-ProyectoFinal/1.0 (uso educativo, contacto: ' . ($this->config['contacto'] ?? 'demo@ruta360.local') . ')',
            ],
        ]);

        $cuerpo = curl_exec($curl);
        $errorCurl = curl_error($curl);
        $estadoHttp = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return [$cuerpo, $errorCurl, $estadoHttp];
    }
}
