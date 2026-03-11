<?php
class Smtp {
    private $socket;
    private $host;
    private $port;
    private $user;
    private $pass;
    private $debug = false;

    public function __construct($host, $port, $user, $pass) {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
    }

    public function send($to, $subject, $body) {
        $this->socket = fsockopen(($this->port == 465 ? "ssl://" : "") . $this->host, $this->port, $errno, $errstr, 10);
        if (!$this->socket) return false;

        $this->cmd(null); // Read initial greeting
        $this->cmd("EHLO " . $this->host);
        
        // Auth
        $this->cmd("AUTH LOGIN");
        $this->cmd(base64_encode($this->user));
        $this->cmd(base64_encode($this->pass));

        // Mail
        $this->cmd("MAIL FROM: <" . $this->user . ">");
        $this->cmd("RCPT TO: <" . $to . ">");
        $this->cmd("DATA");

        // Headers & Body
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";
        $headers .= "From: Android Monitor <" . $this->user . ">\r\n";
        $headers .= "To: <" . $to . ">\r\n";
        $headers .= "Subject: " . $subject . "\r\n";
        
        fputs($this->socket, $headers . "\r\n" . $body . "\r\n.\r\n");
        $this->read(); // Read response after DATA

        $this->cmd("QUIT");
        fclose($this->socket);
        return true;
    }

    private function cmd($cmd) {
        if ($cmd) {
            fputs($this->socket, $cmd . "\r\n");
        }
        return $this->read();
    }

    private function read() {
        $response = "";
        while ($str = fgets($this->socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == " ") break;
        }
        return $response;
    }
}
?>
