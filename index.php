<?php
function loadPage(string $url): DOMDocument
{
    $html = file_get_contents($url);

    if (empty($html)) {
        throw new Exception('Не удалось загрузить HTML-контент по указанному URL.');
    }

    $dom = new DOMDocument();
    @$dom->loadHTML($html);

    return $dom;
}

function parseData(DOMDocument $dom): array
{
    $xpath = new DOMXPath($dom);
    $divsWithDataObjectID = $xpath->query('//div[@class="col col-main-info"]');
    $phoneLinks = $xpath->query('//a[@class="phone__link"]');
    $adsLinks = $xpath->query('//div[@class="col col-main-info"]//a[@class="name__link"]');
    $agentInfos = $xpath->query('//div[@class="col-xl-9 col-lg-9 col-12"]');
    $combinedData = [];

    foreach ($divsWithDataObjectID as $index => $div) {
        $name = isset($agentInfos[$index]) ? trim($agentInfos[$index]->textContent) : '';
        $address = isset($adsLinks[$index]) ? $adsLinks[$index]->textContent : '';
        $phone = isset($phoneLinks[$index]) ? $phoneLinks[$index]->textContent : '';
        $adLink = isset($adsLinks[$index]) ? 'https://gohome.by' . $adsLinks[$index]->getAttribute('href') : '';

        $email = '';

        if (isset($phoneLinks[$index])) {
            $emailElement = $xpath->query(
                './/div[@class="w-phone"]//a[@class="phone__link email"]',
                $phoneLinks[$index]
            );

            $email = $emailElement->length > 0 ? $emailElement[0]->textContent : '';
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
    $file = fopen($filename, 'w');
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

function scrapeData(string $url): void
{
    $currentPage = 1;
    $totalPages = 1;
    $counter = 1;
    $allData = [];

    while ($currentPage <= $totalPages) {
        $currentUrl = $url . '?page=' . $currentPage;

        $dom = loadPage($currentUrl);
        $combinedData = parseData($dom);

        foreach ($combinedData as $item) {
            $adDom = loadPage($item['adLink']);
            $adXpath = new DOMXPath($adDom);
            $emailElement = $adXpath->query('//div[@class="w-phone"]//a[@class="phone__link email"]');
            $email = $emailElement->length > 0 ? $emailElement[0]->textContent : '';
            $item['email'] = $email;

            $allData[] = $item;
            $counter++;
        }

        if ($currentPage === 1) {
            $paginationLinks = $dom->getElementsByTagName('a');

            foreach ($paginationLinks as $link) {
                if (is_numeric($link->textContent)) {
                    $pageNumber = intval($link->textContent);
                    $totalPages = max($totalPages, $pageNumber);
                }
            }
        }

        $currentPage++;
        sleep(2);
    }

    $filename = 'data.csv';
    writeToCsv($filename, $allData);
}

set_time_limit(3600);

$url = 'https://gohome.by/rent/flat/one-day';
scrapeData($url);