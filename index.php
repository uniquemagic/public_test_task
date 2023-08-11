<?php

/**
 * Код на чистом PHP без абстракций / уровней и пр.
 */

/**
 * Параметры подключения к БД
 * Структура БД используется такая же, как описана в файле "Слои абстракции"
 * В качестве СУБД используется MYSQL
 */

const DATABASE_SETTINGS = [
    'hostname' => 'localhost',
    'username' => 'root',
    'password' => 'password',
    'database' => 'test'
];

global $mysql;

const CHANNEL_NAMES = [
    'sms',
    'email',
    'telegram'
];

/**
 * Получаем id пользователя из глобального массива $_SESSION (в предположении, что он авторизован)
 */

$userId = $_SESSION['user']['id'];

/**
 * Допустим, запрос на изменение настройки принимаем из формы запросом $_POST с указанием метода подтверждения
 * 
 * Несмотря на то, что фронтэнд-часть может блокировать запросы на попытку изменения, 
 * все равно необходимо проверять, что такая настройка с данным значением не имеется в БД (чтобы не использовать ресурсы понапрасну)
 * 
 * Кроме того, необходимо проверить валидность существования канала подтверждения (смс / email / telegram)
 */

if (isset($_POST['setting']['name']) && $_POST['setting']['value'] && $_POST['channel']['name']) {

    $settingName  = clearString($_POST['setting']['name']);
    $settingValue = clearString($_POST['setting']['value']);
    $channelName  = clearString($_POST['channel']['name']);

    // Если канала не существует, стоп скрипт

    if (!in_array($channelName, CHANNEL_NAMES)) {
        die('Invalid channel');
    }

    connect();

    // Находим настройку пользователя

    $sql = "SELECT 
                user_id, setting_name 
            FROM 
                user_settings 
            WHERE 
                user_id = $user_id AND 
                setting_name = $settingName
            ";
    $result = query($sql);

    // Если ее в базе нет - стоп скрипт
    if (!mysqli_num_rows($result)) {
        die('Setting does not exist');
    }

    $row = mysqli_fetch_assoc($result);

    // Если значение найденной настройки совпадает с запрошенной - стоп скрипт
    if ($row['value'] == $settingValue) {
        die('Attempt to change an existing setting');
    }

    // Генерируем код, добавляем запись в историю на изменение настройки в таблицу историй изменений с записью времени окончания подтверждения

    $code = generateCode();

    $userSettingId = $row['id'];
    $codeExpiredAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
    $sql = "INSERT INTO 
                user_settings_requests (id, user_setting_id, channel_name, new_value, code, code_expired_at)
            VALUES
                (null, $userSettingId, $channelName, $settingValue, $code, $codeExpiredAt))
            ";

    query($sql);

    close();
}

/**
 * Форма из фронтэнд-части содержит инпут ввода кода подтверждения, а также информацию об id-настройки и канале
 */

if (isset($_POST['code']) && $_POST['user']['setting']['id'] && $_POST['channel']['name']) {

    $requestedCode = clearString($_POST['code']);
    $userSettingId = clearString($_POST['user']['setting']['id']);
    $channelName   = clearString($_POST['channel']['name']);

    connect();

    $now = date('Y-m-d H:i:s');

    /**
     * Проверяем правильность кода подтверждения
     */

    $sql = "SELECT 
                * 
            FROM 
                user_settings_requests 
            WHERE
                user_setting_id = $userSettingId AND
                channel_name    = $channelName AND
                code            = $requestedCode AND
                code_expired_at < $now
            ";
    
    $result = query($sql);

    if (!mysqli_num_rows($result)) {
        die('Validation error');
    }

    $row = mysqli_fetch_assoc($result);

    $newSettingValue = $row['new_setting_value'];

    $sql = "UPDATE 
                user_setting 
            SET 
                setting_value = $newSettingValue 
            WHERE 
                id = $userSettingId
            ";

    query($sql);

    close();
}

function connect() 
{
    global $mysql;
    $mysql = mysqli_connect(extract(DATABASE_SETTINGS));
    if (!$mysql) {
        die("Couldn't connect to database");
    }
}

function close()
{
    global $mysql;
    mysqli_close($mysql);
}

function query($sql) 
{
    global $mysql;
    return mysqli_query($mysql, $sql);
}

function clearString($string): string
{
    return strip_tags(htmlentities($string));
}

function generateCode(): string
{
    return '';
}

function sendCodeViaChannel($channel): bool
{
    return true;
}