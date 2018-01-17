<?php

/*
 * Скрипт для запуска из консоли
 * Там просто красиво выделены валидные и не валидные почты =)
 * Плюс можно указать лимит на один запрос
 *
 * */


if (empty($argv)) {
    exit('Скрипт предназначен для работы в консоли.<br>
            Параметры:<br>
            <b>start.php</b> &mdash; без параметров обработает всю таблицу<br>
            <b>start.php 100</b> &mdash; обработает всю таблицу пачками по 100 адресов<br>
            <b>start.php help</b> &mdash; выведет это сообщение в консоли<br>');
}

if (!empty($argv[1]) && strtolower($argv[1]) == 'help') {
    exit("Скрипт предназначен для работы в консоли.\r\n
            Параметры:\r\n
            start.php без параметров обработает всю таблицу\r\n
            start.php 100 обработает всю таблицу пачками по 100 адресов\r\n
            start.php help выведет это сообщение в консоли\r\n");
}

if (!empty($argv[1]) && $argv[1] * 1 > 0) {
    $limit = $argv[1] * 1;
} else {
    if (!empty($argv[1]) && $argv[1] * 1 <= 0) {
        exit  ("Не верно задан параметр limit.");
    }
}

spl_autoload_register(function ($class_name) {
    include $class_name . '.php';
});

define('SERVER', 'localhost');
define('USER', 'yii2testvkvmru');
define('PASS', 'd496abe5-02f7-4cea-849f-54b76ceef8c9');
define('DBNAME', 'yii2testvkvmru');
$mc = new CheckerClass();
$db = new DBClass(SERVER, USER, PASS, DBNAME);


//берем все, что без пробелов.
//Яндекс с Майлом, вроде как, уже умеют создавать кириллические ящики,
//но можно вместо  NOT LIKE '% %'
//поставить REGEX [^а-яА-Я] AND  NOT LIKE '% %' (не работает PCRE \s в MySQL)

if (empty($limit)) {
    $arrEmails = $db->select(['id', 'i_id', 'm_mail'], 'tbl_donor', "m_mail NOT LIKE '% %'");
    checker($arrEmails);
} else {
    //Вариант с LIMIT для очень большой таблицы
    $count = $db->count('tbl_donor', "m_mail NOT LIKE '% %'");
    //берем первую тысячу
    $offset = 0;
    while ($offset < $count) {
        $arrEmails = $db->select(['id', 'i_id', 'm_mail'], 'tbl_donor', "m_mail NOT LIKE '% %'", 'i_id ASC', $limit, $offset);
        $offset = $offset + $limit; // Увеличение OFFSET
        checker($arrEmails);
    }
}


/**
 * Функция, которая запускает проверку и выводит результаты для набора записей
 * @param array $arrEmails
 */
function checker($arrEmails = [])
{
    global $db, $mc;
    $green = array(32);
    $red = array(31);
    foreach ($arrEmails as $arrRow) {
        $status = $mc->checkEmail($arrRow);
        $text = "{$arrRow['i_id']}: {$arrRow['m_mail']} > Status: {$status}";
        if (is_bool($status)) {
            $db->insert('tbl_valid', [$arrRow['i_id'], $arrRow['m_mail']], ['i_id', 'm_mail']);
            echo "\033[" . implode(';', $green) . 'm' . $text . "\033[0m" . "\r\n";
        } else {
            $db->insert('tbl_fail', [$arrRow['i_id'], $arrRow['m_mail'], $status], ['i_id', 'm_mail', 'err']);
            echo "\033[" . implode(';', $red) . 'm' . $text . "\033[0m" . "\r\n";
        }
    }
}