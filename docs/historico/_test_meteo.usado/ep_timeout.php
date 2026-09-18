<?php
declare(strict_types=1);
sleep(7);
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['current' => ['temperature_2m' => 99, 'weather_code' => 0]]);
