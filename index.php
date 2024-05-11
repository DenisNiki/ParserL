<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * @throws Exception
 */
function loadPage(string $url): DOMDocument
{
    echo "Загружаем страницу: " . $url . "\n";

    $html = file_get_contents($url);

    if ($html === false) {
        throw new Exception('Не удалось загрузить HTML-контент по указанному URL.');
    }

    $dom = new DOMDocument();
    @$dom->loadHTML($html);

    return $dom;
}

function parseData(DOMDocument $dom): array
{
    echo "Извлекаем данные из DOM \n";

    $xpath = new DOMXPath($dom);
    $divsWithDataObjectID = $xpath->query('//div[@class="col col-main-info"]');
    $phoneLinks = $xpath->query('//a[@class="phone__link"]');
    $adsLinks = $xpath->query('//div[@class="col col-main-info"]//a[@class="name__link"]');
    $agentInfos = $xpath->query('//div[@class="col-xl-9 col-lg-9 col-12"]');
    $combinedData = [];

    foreach ($divsWithDataObjectID as $index => $div) {
        $name = '';
        $address = '';
        $phone = '';
        $adLink = '';

        if (isset($agentInfos[$index])) {
            $name = trim($agentInfos[$index]->textContent);
        }

        if (isset($adsLinks[$index])) {
            $address = $adsLinks[$index]->textContent;
            $adLink = 'https://gohome.by' . $adsLinks[$index]->getAttribute('href');
        }

        if (isset($phoneLinks[$index])) {
            $phone = $phoneLinks[$index]->textContent;
        }

        $email = '';

        try {
            if ($adLink !== '') {
                $adDom = loadPage($adLink);
                $adXpath = new DOMXPath($adDom);
                $emailElement = $adXpath->query('//div[@class="w-phone"]//a[@class="phone__link email"]');
                $email = $emailElement->length > 0 ? $emailElement[0]->textContent : '';
            }
        } catch (Exception $e) {
            echo 'Произошла ошибка при загрузке страницы объявления: ' . $e->getMessage() . "\n";
        }

        $combinedData[] = [
            'name' => $name,
            'address' => $address,
            'phone' => $phone,
            'adLink' => $adLink,
            'email' => $email,
        ];
    }

    return $combinedData;
}

function writeToCsv(string $filename, array $data): void
{
    echo "Записываем данные в CSV-файл: " . $filename . "\n";

    $file = fopen($filename, 'w', false, stream_context_create(['context' => ['encoding' => 'UTF-8']]));
    $headers = ['Имя', 'Адрес', 'Номер телефона', 'Ссылка на объявление', 'E-mail'];
    fputcsv($file, $headers);

    foreach ($data as $row) {
        $rowData = [
            $row['name'],
            $row['address'],
            $row['phone'],
            $row['adLink'],
            $row['email'],
        ];
        fputcsv($file, $rowData);
    }

    fclose($file);
}

/**
 * @param string $url
 * @param int $endPagination
 * @throws Exception
 */
function scrapeData(string $url, int $endPagination): void
{
    echo "Начинаем сбор данных \n";

    $currentPage = 1;
    $totalPages = 1;
    $allData = [];

    while ($currentPage <= $totalPages) {
        $currentUrl = $url . '?page=' . $currentPage;

        try {
            $dom = loadPage($currentUrl);
        } catch (Exception $e) {
            echo 'Произошла ошибка при загрузке страницы: ' . $e->getMessage() . "\n";
            $currentPage++;
            continue;
        }

        $combinedData = parseData($dom);
        // Фрагмент кода для разделения на части по 50 записей
        $chunkedData = array_chunk($combinedData, 50);

        foreach ($chunkedData as $chunk) {
            foreach ($chunk as $item) {
                // Ваш код обработки каждой записи
                $allData[] = $item;
            }
        }

        // Запись данных илидругие операции

        if ($currentPage === 1) {
            $xpath = new DOMXPath($dom);
            $startPagination = $xpath->query('//div[contains(@class, "row-pagination")]/div[contains(@class, "col-sm-auto")]/a[contains(@class, "__link")]');

            if ($startPagination->length > 0) {
                $totalPages = $endPagination;
            }
        }

        sleep(2);

        $filename = 'data.csv'; // Записываем данные в файл после каждой итерации
        writeToCsv($filename, $allData);

        $currentPage++;
    } $filename = 'data.csv';
    writeToCsv($filename, $allData);
}
set_time_limit(0);

$url = 'https://gohome.by/rent/flat/one-day';
$endPagination = 108; // Укажите конечное значение пагинации здесь

try {
    scrapeData($url, $endPagination);
} catch (Exception $e) {
    echo 'Произошла ошибка: ' . $e->getMessage();
}