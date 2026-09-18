<?php
declare(strict_types=1);
http_response_code(503);
header('Content-Type: application/json');
echo json_encode(['error' => 'Service Unavailable']);
