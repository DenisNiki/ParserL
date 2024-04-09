<?php

function loadPage(string $url): DOMDocument
{
    $html = file_get_contents($url);
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

function writeDataToTable(int $counter, array $data): void
{
    echo $counter . '. Имя: ' . $data['name'] . ' | Адрес: ' . $data['address'] . ' | <br> Номер телефона: ' . $data['phone'] . ' | Ссылка на объявление: ' . $data['adLink'] . ' | e-mail: ' . $data['email'] . "<br><br>";
}

function scrapeData(string $url): void
{
    $currentPage = 1;
    $totalPages = 1;
    $counter = 1;

    while ($currentPage <= $totalPages) {
        $currentUrl = $url . '?page=' . $currentPage;

        $dom = loadPage($currentUrl);
        $combinedData = parseData($dom);

        foreach ($combinedData as $index => $data) {
            writeDataToTable($counter, $data);
            $counter++;
        }

        // Получение общего количества страниц
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
}

$url = 'https://gohome.by/rent/flat/one-day';
scrapeData($url);
