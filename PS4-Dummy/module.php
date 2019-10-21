<?php

include_once __DIR__ . '/../libs/helper.php';

class PS4_Dummy extends IPSModule
{
    use BufferHelper,
        DebugHelper;

    public function Create()
    {
        // Diese Zeile nicht löschen.
        parent::Create();
        //Always create our own MultiCast I/O, when no parent is already available
        $this->RequireParent('{BAB408E0-0A0F-48C3-B14E-9FB2FA81F66A}');
        $this->RegisterVariableString('PS4_Credentials', 'Credentials');
        $this->RegisterPropertyBoolean('DummyStatus', true);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $Instanz = IPS_GetInstance($this->InstanceID);
        $ConnectionID = $Instanz['ConnectionID'];
        $this->SendDebug('Objekt', $ConnectionID, 0);
        if ($ConnectionID != 0) {
            if ($this->ReadPropertyBoolean('DummyStatus')) {
                IPS_SetProperty($ConnectionID, 'Open', true);
                IPS_ApplyChanges($ConnectionID);
            } else {
                IPS_SetProperty($ConnectionID, 'Open', false);
                IPS_ApplyChanges($ConnectionID);
            }
        }
        $this->SetStatus(102);
    }

    public function SendSearchResponse(string $Host, int $Port)
    {
        $Header[] = 'HTTP/1.1 620 Server Standby';
        $Header[] = 'host-id:1234567890AB';
        $Header[] = 'host-type:PS4';
        $Header[] = 'host-name:IP-Symcon';
        $Header[] = 'host-request-port:997';
        $Header[] = 'device-discovery-protocol-version:00020020';
        $Payload = implode("\n", $Header);
        $this->SendDebug('SendSearchResponse', $Payload, 0);
        $SendData = array('DataID' => '{C8792760-65CF-4C53-B5C7-A30FCC84FEFE}', 'Buffer' => utf8_encode($Payload), 'ClientIP' => $Host, 'ClientPort' => $Port);
        //$this->SendDebug("SendToParent", $SendData, 0);
        $this->SendDataToParent(json_encode($SendData));
    }

    private function ParseHeader($Lines)
    {
        $Header = array();
        foreach ($Lines as $Line) {
            $pair = explode(':', $Line);
            $Key = array_shift($pair);
            $Header[strtoupper($Key)] = trim(implode(':', $pair));
        }
        return $Header;
    }

    public function ReceiveData($JSONString)
    {
        $ReceiveData = json_decode($JSONString);
        $this->SendDebug('Receive JSONString', $JSONString, 0);
        //$this->SendDebug("Receive", $ReceiveData, 0);
        $data = $this->Buffer . utf8_decode($ReceiveData->Buffer);

        $Lines = explode("\n", utf8_decode($ReceiveData->Buffer));
        //$this->SendDebug("Receive Lines", $Lines, 0);

        $Request = array_shift($Lines);
        $Header = $this->ParseHeader($Lines);
        // Auf verschiedene Requests prüfen.
        switch ($Request) { // REQUEST
            case 'HTTP/1.1 200 Ok':
                // hier Sucht ein Gerät.
                // Sucht es nach uns ?
                $this->SendDebug('Receive REQUEST', $Request, 0);
                $this->SendDebug('Receive HEADER', $Header, 0);
                $this->SendSearchResponse($ReceiveData->ClientIP, $ReceiveData->ClientPort);
                return;
                break;
            case 'SRCH * HTTP/1.1':
                $this->SendDebug('Receive REQUEST', $Request, 0);
                $this->SendDebug('Receive HEADER', $Header, 0);
                $this->SendSearchResponse($ReceiveData->ClientIP, $ReceiveData->ClientPort);
                break;
            case 'WAKEUP * HTTP/1.1':
                if (array_key_exists('USER-CREDENTIAL', $Header)) {
                    $this->SendDebug('Credentials', $Header['USER-CREDENTIAL'], 0);
                    SetValue(IPS_GetObjectIDByIdent('PS4_Credentials', $this->InstanceID), $Header['USER-CREDENTIAL']);
                }
                break;
            default:
                // Alles andere wollen wir nicht
                return;
        }
        return;
    }

    public function GetConfigurationForParent()
    {
        $jsonarr['BindPort'] = 987;
        $jsonarr['EnableBroadcast'] = 1;
        $jsonarr['EnableLoopback'] = 1;
        $jsonarr['EnableReuseAddress'] = 1;
        $jsonarr['Host'] = '239.255.255.250';
        $jsonarr['MulticastIP'] = '239.255.255.250';
        $jsonarr['Port'] = 987;
        $json = json_encode($jsonarr);
        return $json;
    }
}
