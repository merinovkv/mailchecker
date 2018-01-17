<?php

class CheckerClass
{
    /**
     * Каким доменом представимся серверу
     * @var string
     */
    private $ourDomain = "vkvm.ru";
    /**
     * Каким почтовым ящиком представимся серверу
     * @var string
     */
    private $ourEmail = "noreplay@vkvm.ru";
    /**
     * Список доменов с бесплатной почтой, где точно есть MX записи
     * @var array
     */
    private $arrPublicDomains = ['yandex.ru', 'mail.ru', 'ya.ru', 'list.ru', 'bk.ru', 'rambler.ru'];


    /**
     * Проверяет, есть ли MX записи у домена
     * (есть ли принципиальная возможность слать на этот ящик письма).
     * @param $email
     * @return bool|null
     */
    public function checkDomain($email)
    {
        $arrEmail = explode("@", $email);
        if (!empty($arrEmail[1])) {
            if (getmxrr($arrEmail[1], $mxhosts, $weight)) {
                return true;
            }
        }

        return NULL;
    }


    /**
     * Отправляет команду в сокет, и возвращает ответ от сервера.
     * Тут все сильно зависит от скорости ответа.
     * Я бы использовал ее бы не для прогона по большой базе, а для регистрации.
     * @param $socket
     * @param $data
     * @return bool|string
     */
    private function socketWriter($socket, $data)
    {
        fputs($socket, $data);
        // получаем первый байт ответа от сервера
        $answer = fread($socket, 1);
        // узнаем информацию о состоянии потока
        $remains = socket_get_status($socket);
        // и получаем оставшиеся байты ответа от сервера
        $remains--;
        if (empty($remains['unread_bytes'])) {
            if ($remains > 0) $answer .= fread($socket, 1);
        } else {
            if ($remains > 0) $answer .= fread($socket, $remains['unread_bytes']);
        }
        return $answer;
    }

    /**
     * Не всегда понятно, когда вернется "Connection timed out",
     * поэтому проверяю, есть ли у домена вообще MX-записи
     * @param $email
     * @return bool|string
     */
    public function checkEmail($arrRow)
    {
        $arrEmail = explode("@", $arrRow['m_mail']);

        //если домен в списке общедоступных почтовых серверов
        //чтобы не проверять MX записи у тех доменов, которые и так известно, что почтовые (ya, yandex, mail etc)
        if (in_array($arrEmail[1], $this->arrPublicDomains)) {
            $result = $this->checkEmailSocket($arrRow['m_mail'], $arrRow['i_id']);
        } else {
            //если домен корпоративный или экзотический
            //можно ли вообще на этот домен что-то послать
            if ($this->checkDomain($arrRow['m_mail'])) { //есть ли MX
                $result = $this->checkEmailSocket($arrRow['m_mail'], $arrRow['i_id']);
            } else {
                $result = 'MX is not defined in this host';
            }
        }

        return $result;
    }

    /**
     * Проверка для доменов, про которые известно, что у них есть почтовые ящики
     * @param $email
     * @return bool|string
     */
    public function checkEmailSocket($email, $id)
    {

        $response = false;
        $arrEmail = explode("@", $email);

        // получаем данные об MX-записи домена, указанного в email
        $mx = dns_get_record($arrEmail[1], DNS_MX);
        $mx = $mx[0]['target'];
        //отлавливаем ошибки WARNING на случай timeout
        set_error_handler(array($this, "warning_handler"), E_WARNING);
        // открываем сокет и создаем поток
        $socket = fsockopen($mx, 25, $errno, $errstr, 10);
        restore_error_handler();

        if ($socket) { //если получилось открыть сокет
            $this->socketWriter($socket, "");
            $this->socketWriter($socket, "EHLO {$this->ourDomain}\r\n");
            $this->socketWriter($socket, "MAIL FROM: $this->ourEmail\r\n");
            $responseServer = $this->socketWriter($socket, "RCPT TO: $email\r\n");
            $this->socketWriter($socket, "QUIT\r\n");
            fclose($socket);

            $response = $responseServer;
            //теперь проверяем ответ сервера
            if (
                substr_count($responseServer, "550") > 0 || //точно нет
                substr_count($responseServer, "553") > 0 //заблокирован/неактивен
            ) {
                //такого адреса на сервере нет
                $response = $responseServer;
            } else {
                if (substr_count($responseServer, "250") > 0) { //есть адрес
                    //такая почта есть
                    $response = true;
                }
            }
        } else {
            $response = 'Connection failed (port 25)';
        }

        return $response;
    }

    /**
     * @param $errno
     * @param $errstr
     */
    private function warning_handler($errno, $errstr)
    {
//        echo "Connection timed out";
        return false;
    }
}



