<?php

function loadPage($url)
{
    $html = file_get_contents($url);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    return $dom;
}

function parseData($dom)
{
    $xpath = new DOMXPath($dom);
    $divsWithDataObjectID = $xpath->query('//div[@class="col col-main-info"]');
    $phoneLinks = $xpath->query('//a[@class="phone__link"]');
    $adsLinks = $xpath->query('//div[@class="col col-main-info"]//a[@class="name__link"]');
    $agentInfos = $xpath->query('//div[@class="col-xl-9 col-lg-9 col-12"]');

    $combinedData = array();

    foreach ($divsWithDataObjectID as $index => $div) {
        $name = isset($agentInfos[$index]) ? trim($agentInfos[$index]->textContent) : '';
        $address = isset($adsLinks[$index]) ? $adsLinks[$index]->textContent : '';
        $phone = isset($phoneLinks[$index]) ? $phoneLinks[$index]->textContent : '';
        $adLink = isset($adsLinks[$index]) ? 'https://gohome.by' . $adsLinks[$index]->getAttribute('href') : '';

        $email = '';
        if (isset($phoneLinks[$index])) {
            $phoneLink = $phoneLinks[$index]->getAttribute('href');
            $emailElement = $xpath->query(
                './/div[@class="w-phone"]//a[@class="phone__link email"]',
                $phoneLinks[$index]
            );
            if ($emailElement->length > 0) {
                $email = $emailElement[0]->textContent;
            }
        }

        $combinedData[] = array(
            'name' => $name,
            'address' => $address,
            'phone' => $phone,
            'adLink' => $adLink,
            'email' => $email
        );
    }

    return $combinedData;
}

// Основной код
$url = 'https://gohome.by/rent/flat/one-day'; // URL страницы с объявлениями на сутки
$currentPage = 1; // текущая страница
$totalPages = 1; // общее количество страниц (по умолчанию 1)
$counter = 1; // счетчик для нумерации

while ($currentPage <= $totalPages) {
    $currentUrl = $url . '?page=' . $currentPage; // формирование URL с номером страницы

    $dom = loadPage($currentUrl);
    $combinedData = parseData($dom);

    foreach ($combinedData as $data) {
        echo $counter . '. Имя: ' . $data['name'] . ' | Адрес: ' . $data['address'] . ' | <br> Номер телефона: ' . $data['phone'] . ' | Ссылка на объявление: ' . $data['adLink'] . ' | e-mail: ' . $data['email'] . "<br><br>";
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
}
?>