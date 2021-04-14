<?php

declare(strict_types=1);
trait DDPConnection
{
    public function getState()
    {
        $packet = 'SRCH * HTTP/1.1\n';
        $packet .= 'device-discovery-protocol-version:00030010\n';

        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 0]);
        socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
        socket_sendto($socket, $packet, strlen($packet), 0, $this->ReadPropertyString('IP'), 9302);
        socket_recvfrom($socket, $result, 1024, 0, $ipaddress, $port);

        if ($result == null) {
            $this->SetValue('State', false);
            return;
        }

        $Lines = explode("\n", utf8_decode($result));

        $Request = array_shift($Lines);
        $Header = $this->ParseHeader($Lines);
        // Auf verschiedene Requests prüfen.
            switch ($Request) { // REQUEST
                case 'HTTP/1.1 200 Ok':
                    $this->SetValue('State', true);
                    break;
                default:
                    $this->SetValue('State', false);
                    break;
            }
        $this->SendDebug('DDP Request', $Request, 0);
        $this->SendDebug('DDP Answer', print_r($Header, true), 0);
        return;
    }
    protected function sendDDP($packet)
    {
        IPS_LogMessage('Packet', $packet);

        $this->SendDebug('DDP Packet', $packet, 0);
        $this->SendDebug('DDP Packet Length', strlen($packet), 0);

        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
        socket_sendto($socket, $packet, strlen($packet), 0, $this->ReadPropertyString('IP'), 9302);
    }

    protected function ParseHeader($Lines)
    {
        $Header = [];
        foreach ($Lines as $Line) {
            $pair = explode(':', $Line);
            $Key = array_shift($pair);
            $Header[strtoupper($Key)] = trim(implode(':', $pair));
        }
        return $Header;
    }
}