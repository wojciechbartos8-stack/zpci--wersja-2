<?php

ob_start();

/* =========================================
   SEND.PHP – OBSŁUGA SZKOLEŃ I USŁUG
========================================= */


/* =========================================
   FUNKCJA PRZEKIEROWANIA DO CS.HTML
========================================= */

function redirectToCs($status, $type = '')
{
    $url = 'cs.html?status=' . urlencode($status);

    if ($type !== '') {
        $url .= '&type=' . urlencode($type);
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header(
        'Location: ' . $url,
        true,
        303
    );

    exit;
}


/* =========================================
   NIE MOŻNA WYWOŁAĆ PLIKU BEZPOŚREDNIO
========================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    redirectToCs('form');

}


/* =========================================
   ADRES ODBIORCY
========================================= */

$recipientEmail = 'biuro@zpci.pl';


/* =========================================
   HONEYPOT – OCHRONA PRZED BOTAMI
========================================= */

$website = isset($_POST['website'])
    ? trim($_POST['website'])
    : '';

if ($website !== '') {

    /* Bot – udajemy poprawne wysłanie */

    redirectToCs('success');

}


/* =========================================
   POBRANIE DANYCH Z FORMULARZA
========================================= */

$rodzajZgloszenia = isset($_POST['rodzaj_zgloszenia'])
    ? trim($_POST['rodzaj_zgloszenia'])
    : '';

$szkolenie = isset($_POST['szkolenie'])
    ? trim($_POST['szkolenie'])
    : '';

$usluga = isset($_POST['usluga'])
    ? trim($_POST['usluga'])
    : '';

$imie = isset($_POST['imie'])
    ? trim($_POST['imie'])
    : '';

$nazwisko = isset($_POST['nazwisko'])
    ? trim($_POST['nazwisko'])
    : '';

$email = isset($_POST['email'])
    ? trim($_POST['email'])
    : '';

$telefon = isset($_POST['telefon'])
    ? trim($_POST['telefon'])
    : '';

$firma = isset($_POST['firma'])
    ? trim($_POST['firma'])
    : '';

$wiadomosc = isset($_POST['wiadomosc'])
    ? trim($_POST['wiadomosc'])
    : '';

$zgoda = isset($_POST['zgoda'])
    ? trim($_POST['zgoda'])
    : '';


/* =========================================
   WALIDACJA RODZAJU ZGŁOSZENIA
========================================= */

if (
    $rodzajZgloszenia !== 'szkolenie'
    &&
    $rodzajZgloszenia !== 'usluga'
) {

    redirectToCs('error');

}


/* =========================================
   WALIDACJA PODSTAWOWYCH DANYCH
========================================= */

if (
    $imie === ''
    ||
    $nazwisko === ''
    ||
    $email === ''
    ||
    $zgoda !== 'Tak'
) {

    redirectToCs(
        'error',
        $rodzajZgloszenia
    );

}


/* =========================================
   WALIDACJA ADRESU E-MAIL
========================================= */

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    redirectToCs(
        'error',
        $rodzajZgloszenia
    );

}


/* =========================================
   WALIDACJA SZKOLENIA
========================================= */

if (
    $rodzajZgloszenia === 'szkolenie'
    &&
    $szkolenie === ''
) {

    redirectToCs(
        'error',
        'szkolenie'
    );

}


/* =========================================
   WALIDACJA USŁUGI
========================================= */

if (
    $rodzajZgloszenia === 'usluga'
    &&
    $usluga === ''
) {

    redirectToCs(
        'error',
        'usluga'
    );

}


/* =========================================
   ZABEZPIECZENIE DANYCH DO E-MAILA
========================================= */

$email = str_replace(
    array("\r", "\n"),
    '',
    $email
);

$imie = str_replace(
    array("\r", "\n"),
    ' ',
    $imie
);

$nazwisko = str_replace(
    array("\r", "\n"),
    ' ',
    $nazwisko
);

$telefon = str_replace(
    array("\r", "\n"),
    ' ',
    $telefon
);

$firma = str_replace(
    array("\r", "\n"),
    ' ',
    $firma
);


/* =========================================
   USTALENIE RODZAJU WIADOMOŚCI
========================================= */

if ($rodzajZgloszenia === 'szkolenie') {

    $subject =
        'Nowy zapis na szkolenie - ' .
        $szkolenie;

    $typeLabel =
        'ZAPIS NA SZKOLENIE';

    $selectedItem =
        $szkolenie;

    $selectedLabel =
        'WYBRANE SZKOLENIE';

} else {

    $subject =
        'Nowe zgłoszenie usługi IT - ' .
        $usluga;

    $typeLabel =
        'ZGŁOSZENIE USŁUGI IT';

    $selectedItem =
        $usluga;

    $selectedLabel =
        'WYBRANA USŁUGA';
}


/* =========================================
   TREŚĆ WIADOMOŚCI
========================================= */

$message = '';

$message .= $typeLabel . "\n";

$message .=
    "========================================\n\n";


$message .=
    $selectedLabel .
    ":\n";

$message .=
    $selectedItem .
    "\n\n";


$message .=
    "DANE KONTAKTOWE:\n";

$message .=
    "Imię: " .
    $imie .
    "\n";

$message .=
    "Nazwisko: " .
    $nazwisko .
    "\n";

$message .=
    "E-mail: " .
    $email .
    "\n";

$message .=
    "Telefon: " .
    $telefon .
    "\n";

$message .=
    "Firma / instytucja: " .
    $firma .
    "\n\n";


$message .=
    "WIADOMOŚĆ:\n";

$message .=
    "----------------------------------------\n";

$message .=
    $wiadomosc .
    "\n\n";


$message .=
    "========================================\n";

$message .=
    "Formularz: CS - Centrum Zgłoszeń ZPCI\n";


/* =========================================
   KODOWANIE TEMATU WIADOMOŚCI
========================================= */

$encodedSubject =
    '=?UTF-8?B?' .
    base64_encode($subject) .
    '?=';


/* =========================================
   NAGŁÓWKI WIADOMOŚCI
========================================= */

$headers =
    "MIME-Version: 1.0\r\n";

$headers .=
    "Content-Type: text/plain; charset=UTF-8\r\n";

$headers .=
    "Content-Transfer-Encoding: 8bit\r\n";

$headers .=
    "From: ZPCI <no-reply@zpci.pl>\r\n";

$headers .=
    "Reply-To: " .
    $email .
    "\r\n";


/* =========================================
   WYSŁANIE WIADOMOŚCI
========================================= */

$mailSent = mail(
    $recipientEmail,
    $encodedSubject,
    $message,
    $headers
);


/* =========================================
   PRZEKIEROWANIE PO WYSŁANIU
========================================= */

if ($mailSent) {

    redirectToCs(
        'success',
        $rodzajZgloszenia
    );

} else {

    redirectToCs(
        'error',
        $rodzajZgloszenia
    );

}