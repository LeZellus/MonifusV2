<?php
// Script simple pour tester l'endpoint

$url = 'http://localhost/sales-history/datatable?page=1&length=25&search=&sortColumn=0&sortDirection=desc';

echo "🔍 Test de l'endpoint: $url\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "📡 Code HTTP: $httpCode\n\n";
echo "📄 Réponse:\n";
echo $response;