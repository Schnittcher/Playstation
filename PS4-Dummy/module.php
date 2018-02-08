<?

include_once(__DIR__ . "/../libs/helper.php");

class PS4_Dummy extends IPSModule {

    use BufferHelper,
        DebugHelper;

    public function Create()
    {
        // Diese Zeile nicht löschen.
        parent::Create();
        //Always create our own MultiCast I/O, when no parent is already available
        $this->RequireParent("{BAB408E0-0A0F-48C3-B14E-9FB2FA81F66A}");

    }

    public function ApplyChanges()
    {

    }

    public function SendSearchResponse(string $Host, int $Port)
    {
        $Header[] = "HTTP/1.1 620 Server Standby";
        $Header[] = "host-id:1234567890AB";
        $Header[] = "host-type:PS4";
        $Header[] = "host-name:PS4-Symcon";
        $Header[] = "host-request-port:997";
        $Header[] = "device-discovery-protocol-version:00020020";
        //$Header[] = "";
        //$Header[] = "";
        $Payload = implode("\n", $Header);
        $this->SendDebug("SendSearchResponse", $Payload, 0);
        $SendData = Array("DataID" => "{C8792760-65CF-4C53-B5C7-A30FCC84FEFE}", "Buffer" => utf8_encode($Payload), "ClientIP" => $Host, "ClientPort" => $Port);
        //$this->SendDebug("SendToParent", $SendData, 0);
        $this->SendDataToParent(json_encode($SendData));
    }

    private function ParseHeader($Lines)
    {
        $Header = array();
        foreach ($Lines as $Line)
        {
            $pair = explode(':', $Line);
            $Key = array_shift($pair);
            $Header[strtoupper($Key)] = trim(implode(':', $pair));
        }
        return $Header;
    }


    public function ReceiveData($JSONString)
    {
        $ReceiveData = json_decode($JSONString);
        $this->SendDebug("Receive JSONString", $JSONString, 0);
        //$this->SendDebug("Receive", $ReceiveData, 0);
        $data = $this->Buffer . utf8_decode($ReceiveData->Buffer);

        $Lines = explode("\n", utf8_decode($ReceiveData->Buffer));
        $this->SendDebug("Receive Lines", $Lines, 0);
        // die letzten zwei wech.
        //array_pop($Lines);
        //array_pop($Lines);

        $Request = array_shift($Lines);
        $Header = $this->ParseHeader($Lines);
        // Auf verschiedene Requests prüfen.
        switch ($Request) // REQUEST
        {
            case "SRCH * HTTP/1.1":
                // hier Sucht ein Gerät.
                // Sucht es nach uns ?
                $this->SendDebug("Receive REQUEST", $Request, 0);
                $this->SendDebug("Receive HEADER", $Header, 0);
                $this->SendSearchResponse($ReceiveData->ClientIP, $ReceiveData->ClientPort);
                return;
                break;
            default:
                // Alles andere wollen wir nicht
                return;
        }
        return;
    }

}