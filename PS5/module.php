<?php

declare(strict_types=1);

include_once __DIR__ . '/../libs/helper.php';
include_once __DIR__ . '/../libs/PS5helper.php';

class PS5 extends IPSModule
{
    use DebugHelper;
    public function Create()
    {
        // Diese Zeile nicht löschen.
        parent::Create();

        //Register Propertys
        $this->RegisterPropertyString('IP', '');
        $this->RegisterPropertyString('Credentials', '');
        $this->RegisterPropertyInteger('UpdateTimerInterval', 10);
        $this->RegisterTimer('PS5_getState', 0, 'PS5_getState($_IPS[\'TARGET\']);');

        //Register Variablen
        $this->RegisterVariableBoolean('State', $this->Translate('State'), '~Switch', 1);
        $this->EnableAction('State');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->SetTimerInterval('PS5_getState', $this->ReadPropertyInteger('UpdateTimerInterval') * 1000);
    }

    /** Public Functions to control PS4-System */
    public function WakeUp()
    {
        $packet = "WAKEUP * HTTP/1.1\n";
        $packet .= "client-type:vr\n";
        $packet .= "auth-type:R\n";
        $packet .= "model:w\n";
        $packet .= "app-type:r\n";
        $packet .= 'user-credential:' . $this->ReadPropertyString('Credentials') . "\n";
        $packet .= "device-discovery-protocol-version:00030010\n";
        $this->sendDDP($packet);
    }

    /** IPS Functions */
    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'State':
                    $this->WakeUp();
                break;
        }
    }
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
}
