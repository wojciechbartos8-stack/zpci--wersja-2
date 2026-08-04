<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SEND.PHP – BEZPIECZNA OBSŁUGA FORMULARZY ZPCI
|--------------------------------------------------------------------------
|
| Plik obsługuje:
| 1. zapis na szkolenie,
| 2. zgłoszenie usługi IT.
|
| Nie dodawaj znacznika zamykającego PHP na końcu pliku.
|
*/

/* ==================================================
   KONFIGURACJA PHP
================================================== */

date_default_timezone_set('Europe/Warsaw');

error_reporting(E_ALL);

/*
 * Użytkownik nie powinien widzieć szczegółowych błędów PHP.
 * Błędy będą zapisywane w logach serwera.
 */
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

/* ==================================================
   USTAWIENIA FORMULARZA
================================================== */

const ODBIORCA = 'biuro@zpci.pl';

const NADAWCA_EMAIL = 'formularz@zpci.pl';

const NADAWCA_NAZWA = 'Formularz ZPCI';

const STRONA_GLOWNA = '/index.html';

/*
 * Maksymalny rozmiar całego żądania formularza.
 * 20 KB jest wystarczające dla formularza tekstowego.
 */
const MAX_REQUEST_SIZE = 20480;

/*
 * Maksymalna liczba wysłań w jednej sesji
 * w ciągu dziesięciu minut.
 */
const MAX_REQUESTS_PER_WINDOW = 5;

const RATE_LIMIT_WINDOW = 600;

const MIN_SECONDS_BETWEEN_REQUESTS = 10;

/* ==================================================
   WYKRYCIE HTTPS
================================================== */

$isHttps = (
    isset($_SERVER['HTTPS']) &&
    $_SERVER['HTTPS'] !== '' &&
    strtolower((string) $_SERVER['HTTPS']) !== 'off'
);

/* ==================================================
   BEZPIECZNA SESJA
================================================== */

session_name('ZPCI_FORM_SESSION');

$sessionStarted = session_start([
    'cookie_lifetime' => 0,
    'cookie_path' => '/',
    'cookie_secure' => $isHttps,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
    'use_only_cookies' => true
]);

/* ==================================================
   NAGŁÓWKI BEZPIECZEŃSTWA ODPOWIEDZI
================================================== */

header('Content-Type: text/html; charset=UTF-8');

header('X-Content-Type-Options: nosniff');

header('X-Frame-Options: DENY');

header('Referrer-Policy: no-referrer');

header(
    'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()'
);

header('Cross-Origin-Opener-Policy: same-origin');

header('X-Permitted-Cross-Domain-Policies: none');

header(
    "Content-Security-Policy: " .
    "default-src 'none'; " .
    "style-src 'unsafe-inline'; " .
    "base-uri 'none'; " .
    "form-action 'none'; " .
    "frame-ancestors 'none'"
);

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);

header('Pragma: no-cache');

if ($isHttps) {
    header(
        'Strict-Transport-Security: max-age=31536000'
    );
}

/* ==================================================
   FUNKCJE POMOCNICZE
================================================== */

/**
 * Bezpieczne kodowanie tekstu wyświetlanego w HTML.
 */
function escapeHtml(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Oblicza długość tekstu UTF-8.
 */
function textLength(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    return strlen($value);
}

/**
 * Wyświetla bezpieczną stronę odpowiedzi i kończy skrypt.
 */
function showPage(
    int $statusCode,
    string $title,
    string $message,
    string $linkLabel = 'Powrót do strony głównej',
    string $linkHref = STRONA_GLOWNA
): void {
    http_response_code($statusCode);

    $safeTitle = escapeHtml($title);
    $safeMessage = escapeHtml($message);
    $safeLinkLabel = escapeHtml($linkLabel);
    $safeLinkHref = escapeHtml($linkHref);

    echo <<<HTML
<!DOCTYPE html>
<html lang="pl">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta name="robots" content="noindex, nofollow">

    <title>{$safeTitle} | ZPCI</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            display: flex;
            padding: 20px;
            align-items: center;
            justify-content: center;
            background: #f4f7fb;
            color: #29333d;
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.7;
        }

        .message-box {
            width: 100%;
            max-width: 680px;
            padding: 38px 30px;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 14px 35px rgba(11, 33, 72, 0.14);
            text-align: center;
        }

        h1 {
            margin-bottom: 18px;
            color: #1f3b73;
            font-size: clamp(1.7rem, 5vw, 2.4rem);
            line-height: 1.2;
        }

        p {
            margin-bottom: 25px;
            font-size: 1.05rem;
        }

        a {
            display: inline-flex;
            min-height: 48px;
            padding: 12px 24px;
            align-items: center;
            justify-content: center;
            border-radius: 7px;
            background: #1f3b73;
            color: #ffffff;
            font-weight: bold;
            text-decoration: none;
        }

        a:hover,
        a:focus {
            background: #16315d;
        }

        a:focus {
            outline: 3px solid #f2b705;
            outline-offset: 3px;
        }

        @media (max-width: 520px) {

            .message-box {
                padding: 28px 18px;
            }

            a {
                width: 100%;
            }
        }

    </style>

</head>

<body>

    <main class="message-box">

        <h1>{$safeTitle}</h1>

        <p>{$safeMessage}</p>

        <a href="{$safeLinkHref}">
            {$safeLinkLabel}
        </a>

    </main>

</body>

</html>
HTML;

    exit;
}

/**
 * Pobiera pojedynczą wartość z formularza.
 *
 * Odrzuca tablice podsunięte zamiast tekstu.
 */
function getPostValue(string $name): string
{
    if (!array_key_exists($name, $_POST)) {
        return '';
    }

    if (!is_string($_POST[$name])) {
        showPage(
            400,
            'Nieprawidłowe dane',
            'Formularz zawiera dane w nieprawidłowym formacie.'
        );
    }

    return trim($_POST[$name]);
}

/**
 * Sprawdza, czy tekst jest prawidłowym UTF-8.
 */
function validateUtf8(string $value): void
{
    if (preg_match('//u', $value) !== 1) {
        showPage(
            422,
            'Nieprawidłowe kodowanie',
            'Formularz zawiera nieprawidłowo zakodowane znaki.'
        );
    }
}

/**
 * Sprawdza tekst przeznaczony do jednej linii.
 *
 * Blokuje CR, LF, NUL i inne znaki sterujące.
 */
function validateSingleLine(
    string $value,
    string $fieldName,
    int $maxLength
): string {
    validateUtf8($value);

    if (
        preg_match('/[\x00-\x1F\x7F]/u', $value) === 1
    ) {
        showPage(
            422,
            'Nieprawidłowe dane',
            'Pole „' . $fieldName .
            '” zawiera niedozwolone znaki.'
        );
    }

    $normalized = preg_replace(
        '/[ ]{2,}/u',
        ' ',
        $value
    );

    if (is_string($normalized)) {
        $value = $normalized;
    }

    if (textLength($value) > $maxLength) {
        showPage(
            422,
            'Zbyt długa wartość',
            'Pole „' . $fieldName .
            '” przekracza dozwoloną długość.'
        );
    }

    return $value;
}

/**
 * Walidacja imienia lub nazwiska.
 */
function validateName(
    string $value,
    string $fieldName,
    bool $required
): string {
    $value = validateSingleLine(
        $value,
        $fieldName,
        100
    );

    if ($value === '') {
        if ($required) {
            showPage(
                422,
                'Brak wymaganych danych',
                'Pole „' . $fieldName . '” jest wymagane.'
            );
        }

        return '';
    }

    if (textLength($value) < 2) {
        showPage(
            422,
            'Nieprawidłowe dane',
            'Pole „' . $fieldName .
            '” zawiera zbyt mało znaków.'
        );
    }

    /*
     * Dopuszczalne są:
     * litery, spacje, kropki, apostrofy i myślniki.
     */
    if (
        preg_match(
            "/^[\p{L}\p{M}][\p{L}\p{M} .'\-]*$/u",
            $value
        ) !== 1
    ) {
        showPage(
            422,
            'Nieprawidłowe dane',
            'Pole „' . $fieldName .
            '” zawiera niedozwolone znaki.'
        );
    }

    return $value;
}

/**
 * Walidacja adresu e-mail.
 */
function validateEmailAddress(string $email): string
{
    $email = validateSingleLine(
        $email,
        'E-mail',
        254
    );

    if ($email === '') {
        showPage(
            422,
            'Brak adresu e-mail',
            'Podanie prawidłowego adresu e-mail jest wymagane.'
        );
    }

    /*
     * Dodatkowa, jawna blokada CRLF,
     * chroniąca nagłówek Reply-To.
     */
    if (
        strpos($email, "\r") !== false ||
        strpos($email, "\n") !== false
    ) {
        showPage(
            422,
            'Nieprawidłowy adres e-mail',
            'Podany adres e-mail jest nieprawidłowy.'
        );
    }

    $validated = filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    );

    if ($validated === false) {
        showPage(
            422,
            'Nieprawidłowy adres e-mail',
            'Sprawdź poprawność wpisanego adresu e-mail.'
        );
    }

    return (string) $validated;
}

/**
 * Walidacja numeru telefonu.
 *
 * Telefon może być pusty, ale jeśli został podany,
 * musi mieć poprawny format.
 */
function validatePhoneNumber(string $phone): string
{
    $phone = validateSingleLine(
        $phone,
        'Telefon',
        25
    );

    if ($phone === '') {
        return '';
    }

    if (
        preg_match(
            '/^[0-9+()\s\-]{6,25}$/u',
            $phone
        ) !== 1
    ) {
        showPage(
            422,
            'Nieprawidłowy numer telefonu',
            'Numer telefonu zawiera niedozwolone znaki.'
        );
    }

    return $phone;
}

/**
 * Walidacja nazwy szkolenia albo usługi.
 */
function validateOfferName(
    string $value,
    string $fieldName
): string {
    $value = validateSingleLine(
        $value,
        $fieldName,
        160
    );

    if (
        $value !== '' &&
        textLength($value) < 2
    ) {
        showPage(
            422,
            'Nieprawidłowe dane',
            'Pole „' . $fieldName .
            '” zawiera zbyt mało znaków.'
        );
    }

    return $value;
}

/**
 * Walidacja wiadomości wielowierszowej.
 */
function validateMessage(string $message): string
{
    validateUtf8($message);

    /*
     * Ujednolicenie końców linii.
     */
    $message = str_replace(
        ["\r\n", "\r"],
        "\n",
        $message
    );

    /*
     * Dozwolone są:
     * - znak nowej linii,
     * - tabulator,
     * - zwykłe znaki tekstowe.
     *
     * Blokowane są pozostałe znaki sterujące.
     */
    if (
        preg_match(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
            $message
        ) === 1
    ) {
        showPage(
            422,
            'Nieprawidłowa wiadomość',
            'Wiadomość zawiera niedozwolone znaki.'
        );
    }

    if (textLength($message) > 3000) {
        showPage(
            422,
            'Wiadomość jest za długa',
            'Wiadomość może zawierać maksymalnie 3000 znaków.'
        );
    }

    return trim($message);
}

/**
 * Kodowanie polskich znaków w temacie wiadomości.
 */
function encodeMimeHeader(string $value): string
{
    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader(
            $value,
            'UTF-8',
            'B',
            "\r\n"
        );
    }

    return '=?UTF-8?B?' .
        base64_encode($value) .
        '?=';
}

/**
 * Sprawdzenie pochodzenia żądania.
 *
 * localhost i 127.0.0.1 są dopuszczone
 * do lokalnego testowania strony.
 */
function requestOriginIsAllowed(): bool
{
    $allowedHosts = [
        'zpci.pl',
        'www.zpci.pl',
        'localhost',
        '127.0.0.1'
    ];

    $headersToCheck = [
        'HTTP_ORIGIN',
        'HTTP_REFERER'
    ];

    foreach ($headersToCheck as $headerName) {
        $headerValue = trim(
            (string) ($_SERVER[$headerName] ?? '')
        );

        /*
         * Niektóre przeglądarki lub ustawienia prywatności
         * nie przesyłają tego nagłówka.
         */
        if ($headerValue === '') {
            continue;
        }

        $host = parse_url(
            $headerValue,
            PHP_URL_HOST
        );

        if (!is_string($host) || $host === '') {
            return false;
        }

        if (
            !in_array(
                strtolower($host),
                $allowedHosts,
                true
            )
        ) {
            return false;
        }
    }

    return true;
}

/* ==================================================
   TYLKO METODA POST
================================================== */

$requestMethod = strtoupper(
    (string) ($_SERVER['REQUEST_METHOD'] ?? '')
);

if ($requestMethod !== 'POST') {
    header('Allow: POST');

    showPage(
        405,
        'Niedozwolona metoda',
        'Formularz można wysłać wyłącznie metodą POST.'
    );
}

/* ==================================================
   OGRANICZENIE ROZMIARU ŻĄDANIA
================================================== */

$contentLength = (int) (
    $_SERVER['CONTENT_LENGTH'] ?? 0
);

if ($contentLength > MAX_REQUEST_SIZE) {
    showPage(
        413,
        'Formularz jest za duży',
        'Przesłane dane przekraczają dozwolony rozmiar.'
    );
}

/* ==================================================
   KONTROLA TYPU DANYCH
================================================== */

$contentTypeHeader = strtolower(
    trim(
        (string) ($_SERVER['CONTENT_TYPE'] ?? '')
    )
);

$contentTypeParts = explode(
    ';',
    $contentTypeHeader,
    2
);

$contentType = trim($contentTypeParts[0]);

$allowedContentTypes = [
    'application/x-www-form-urlencoded',
    'multipart/form-data'
];

if (
    $contentType !== '' &&
    !in_array(
        $contentType,
        $allowedContentTypes,
        true
    )
) {
    showPage(
        415,
        'Nieobsługiwany format',
        'Formularz został wysłany w nieobsługiwanym formacie.'
    );
}

/* ==================================================
   KONTROLA POCHODZENIA
================================================== */

if (!requestOriginIsAllowed()) {
    showPage(
        403,
        'Żądanie zostało zablokowane',
        'Formularz został wysłany z niedozwolonego źródła.'
    );
}

/* ==================================================
   HONEYPOT – PUŁAPKA NA BOTY
================================================== */

/*
 * W obu formularzach HTML dodamy ukryte pole:
 *
 * <input
 *     type="text"
 *     name="website"
 *     tabindex="-1"
 *     autocomplete="off"
 * >
 *
 * Zwykły użytkownik pozostawi je puste.
 * Bot często je automatycznie uzupełni.
 */
$honeypot = getPostValue('website');

if ($honeypot !== '') {
    if (
        $sessionStarted &&
        session_status() === PHP_SESSION_ACTIVE
    ) {
        session_write_close();
    }

    /*
     * Bot otrzymuje zwykły komunikat powodzenia,
     * ale wiadomość nie zostaje wysłana.
     */
    showPage(
        200,
        'Dziękujemy za zgłoszenie',
        'Formularz został przyjęty.'
    );
}

/* ==================================================
   PROSTY LIMIT WYSYŁANIA
================================================== */

if ($sessionStarted) {
    $currentTime = time();

    $windowStart = (int) (
        $_SESSION['form_window_start'] ?? 0
    );

    $requestCount = (int) (
        $_SESSION['form_request_count'] ?? 0
    );

    $lastRequest = (int) (
        $_SESSION['form_last_request'] ?? 0
    );

    if (
        $windowStart === 0 ||
        ($currentTime - $windowStart) > RATE_LIMIT_WINDOW
    ) {
        $windowStart = $currentTime;
        $requestCount = 0;
    }

    if (
        $lastRequest > 0 &&
        ($currentTime - $lastRequest) <
            MIN_SECONDS_BETWEEN_REQUESTS
    ) {
        session_write_close();

        showPage(
            429,
            'Zbyt wiele prób',
            'Odczekaj chwilę przed ponownym wysłaniem formularza.'
        );
    }

    if ($requestCount >= MAX_REQUESTS_PER_WINDOW) {
        session_write_close();

        showPage(
            429,
            'Limit zgłoszeń został przekroczony',
            'Spróbuj ponownie za kilka minut.'
        );
    }

    $_SESSION['form_window_start'] = $windowStart;

    $_SESSION['form_request_count'] =
        $requestCount + 1;

    $_SESSION['form_last_request'] =
        $currentTime;

    session_write_close();
}

/* ==================================================
   POBRANIE I WALIDACJA DANYCH
================================================== */

$imie = validateName(
    getPostValue('imie'),
    'Imię lub imię i nazwisko',
    true
);

$nazwisko = validateName(
    getPostValue('nazwisko'),
    'Nazwisko',
    false
);

$email = validateEmailAddress(
    getPostValue('email')
);

$telefon = validatePhoneNumber(
    getPostValue('telefon')
);

$szkolenie = validateOfferName(
    getPostValue('szkolenie'),
    'Szkolenie'
);

$usluga = validateOfferName(
    getPostValue('usluga'),
    'Usługa'
);

$wiadomosc = validateMessage(
    getPostValue('wiadomosc')
);

/* ==================================================
   ROZPOZNANIE FORMULARZA
================================================== */

$isTrainingForm = $szkolenie !== '';

$isServiceForm = $usluga !== '';

/*
 * Dokładnie jedno z pól:
 * szkolenie albo usługa
 * musi zostać wypełnione.
 */
if ($isTrainingForm === $isServiceForm) {
    showPage(
        422,
        'Nie rozpoznano formularza',
        'Nie wybrano szkolenia lub usługi.'
    );
}

/*
 * Formularz szkolenia korzysta z osobnego pola nazwisko.
 */
if ($isTrainingForm && $nazwisko === '') {
    showPage(
        422,
        'Brak nazwiska',
        'Podanie nazwiska jest wymagane przy zapisie na szkolenie.'
    );
}

/* ==================================================
   IDENTYFIKATOR ZGŁOSZENIA
================================================== */

try {
    $requestId = bin2hex(
        random_bytes(8)
    );
} catch (Throwable $exception) {
    $requestId = substr(
        hash(
            'sha256',
            uniqid('', true)
        ),
        0,
        16
    );
}

$submissionDate = date('Y-m-d H:i:s');

/* ==================================================
   PRZYGOTOWANIE WIADOMOŚCI
================================================== */

$phoneForEmail = $telefon !== ''
    ? $telefon
    : 'Nie podano';

$messageForEmail = $wiadomosc !== ''
    ? $wiadomosc
    : 'Brak dodatkowych informacji.';

if ($isTrainingForm) {
    $subject = 'Zapis na szkolenie ZPCI';

    $messageLines = [
        'Nowy zapis na szkolenie',
        '',
        'Identyfikator zgłoszenia: ' . $requestId,
        'Data zgłoszenia: ' . $submissionDate,
        '',
        'Imię: ' . $imie,
        'Nazwisko: ' . $nazwisko,
        'E-mail: ' . $email,
        'Telefon: ' . $phoneForEmail,
        '',
        'Szkolenie: ' . $szkolenie,
        '',
        'Dodatkowe informacje:',
        $messageForEmail
    ];
} else {
    $subject = 'Zgłoszenie usługi IT ZPCI';

    $fullName = trim(
        $imie . ' ' . $nazwisko
    );

    $messageLines = [
        'Nowe zgłoszenie usługi IT',
        '',
        'Identyfikator zgłoszenia: ' . $requestId,
        'Data zgłoszenia: ' . $submissionDate,
        '',
        'Imię i nazwisko: ' . $fullName,
        'E-mail: ' . $email,
        'Telefon: ' . $phoneForEmail,
        '',
        'Usługa: ' . $usluga,
        '',
        'Opis zgłoszenia:',
        $messageForEmail
    ];
}

$emailBody = implode(
    "\r\n",
    $messageLines
);

/*
 * Ochrona przed problemem z kropką
 * na początku linii w niektórych serwerach SMTP.
 */
$protectedBody = preg_replace(
    '/^\./m',
    '..',
    $emailBody
);

if (is_string($protectedBody)) {
    $emailBody = $protectedBody;
}

/* ==================================================
   NAGŁÓWKI WIADOMOŚCI
================================================== */

/*
 * PHP od wersji 7.2 pozwala przekazać nagłówki
 * jako tablicę. Zmniejsza to ryzyko błędnego
 * sklejenia wielu nagłówków.
 */
$headers = [
    'From' =>
        NADAWCA_NAZWA .
        ' <' .
        NADAWCA_EMAIL .
        '>',

    /*
     * Adres został wcześniej:
     * - ograniczony długością,
     * - sprawdzony pod kątem CRLF,
     * - zweryfikowany przez FILTER_VALIDATE_EMAIL.
     */
    'Reply-To' => $email,

    'MIME-Version' => '1.0',

    'Content-Type' =>
        'text/plain; charset=UTF-8',

    'Content-Transfer-Encoding' => '8bit'
];

$encodedSubject = encodeMimeHeader(
    $subject
);

/* ==================================================
   WYSŁANIE WIADOMOŚCI
================================================== */

$mailAccepted = mail(
    ODBIORCA,
    $encodedSubject,
    $emailBody,
    $headers
);

if (!$mailAccepted) {
    /*
     * Do logów zapisujemy tylko identyfikator.
     * Nie zapisujemy danych osobowych użytkownika.
     */
    error_log(
        'send.php: mail() failed; request_id=' .
        $requestId
    );

    showPage(
        500,
        'Nie udało się wysłać zgłoszenia',
        'Wystąpił chwilowy problem techniczny. Spróbuj ponownie później.'
    );
}

/* ==================================================
   POTWIERDZENIE
================================================== */

showPage(
    200,
    'Dziękujemy za zgłoszenie',
    'Zgłoszenie zostało przyjęte. Skontaktujemy się z Państwem w najbliższym czasie.'
);