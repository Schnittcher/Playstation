<?

trait BufferHelper
{

/**
* Wert einer Eigenschaft aus den InstanceBuffer lesen.
*
* @access public
* @param string $name Propertyname
* @return mixed Value of Name
*/
public function __get($name)
{
return unserialize($this->GetBuffer($name));
}

/**
* Wert einer Eigenschaft in den InstanceBuffer schreiben.
*
* @access public
* @param string $name Propertyname
* @param mixed Value of Name
*/
public function __set($name, $value)
{
$this->SetBuffer($name, serialize($value));
}

}

trait DebugHelper
{

/**
* Ergänzt SendDebug um Möglichkeit Objekte und Array auszugeben.
*
* @access protected
* @param string $Message Nachricht für Data.
* @param mixed $Data Daten für die Ausgabe.
* @return int $Format Ausgabeformat für Strings.
*/
protected function SendDebug($Message, $Data, $Format)
{
if (is_object($Data))
{
foreach ($Data as $Key => $DebugData)
{

$this->SendDebug($Message . ":" . $Key, $DebugData, 0);
}
}
else if (is_array($Data))
{
foreach ($Data as $Key => $DebugData)
{
$this->SendDebug($Message . ":" . $Key, $DebugData, 0);
}
}
else if (is_bool($Data))
{
parent::SendDebug($Message, ($Data ? 'TRUE' : 'FALSE'), 0);
}
else
{
parent::SendDebug($Message, (string) $Data, $Format);
}
}

}

trait TCPConnection
{
    private function _send_hello_request()
    {
        $packet = "\x1c\x00\x00\x00\x70\x63\x63\x6f\x00\x00\x02\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
        $this->_send_msg($packet);
    }

    private function _send_handshake_request()
    {
        $this->SendDebug("Used Seed", $this->Seed, 0);

        $random_seed = "\x10\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
        openssl_public_encrypt($random_seed, $cryptedKey, $this->get_Public_Key_RSA(), OPENSSL_PKCS1_OAEP_PADDING);

        $Packet = "\x18\x01\x00\x00";
        $Packet .= "\x20\x00\x00\x00";
        $Packet .= $cryptedKey;
        $Packet .= $this->Seed;
        $this->_send_msg($Packet);
        $this->ReceiveEncrypted = true;
    }

    /**
     *
     */
    private function _send_standby_request()
    {
        $Packet = "\x08\x00\x00\x00";
        $Packet .= "\x1a\x00\x00\x00";
        $dummy = "";
        $dummy = str_pad($dummy, 8,"\x00");
        $Packet .= $dummy;
        $this->ReceiveEncrypted = true;
        $this->_send_msg($Packet, true);
    }

    private function _send_login_request($pincode = "")
    {
        $AccountID = $this->ReadPropertyString("Credentials");
        $AccountID = str_pad($AccountID, 64, "\x00");
        $AppLabel = "Playstation";
        $AppLabel = str_pad($AppLabel, 256, "\x00");
        $OSVersion = "4.4";
        $OSVersion = str_pad($OSVersion, 16, "\x00");
        $model = "IP-Symcon";
        $model = str_pad($model, 16, "\x00");
        $pincode = str_pad($pincode, 16, "\x00");

        $Login = "\x80\x01\x00\x00";
        $Login .= "\x1e\x00\x00\x00";
        $Login .= "\x00\x00\x00\x00";
        $Login .= "\x01\x02\x00\x00";
        $Login .= $AccountID;
        $Login .= $AppLabel;
        $Login .= $OSVersion;
        $Login .= $model;
        $Login .= $pincode;
        $this->ReceiveEncrypted = true;
        $this->SendDebug("Login Package", $Login, 0);

        $this->_send_msg($Login, true);
    }

    private function _send_boot_request($title_id)
    {
        $Package = "\x18\x00\x00\x00";
        $Package .= "\x0a\x00\x00\x00";
        $title_id = str_pad($title_id, 16, "\x00");
        $Package .= $title_id;
        $dummy = "";
        $dummy = str_pad($dummy, 8,"\x00");
        $Package .= $dummy;
        $this->_send_msg($Package, true);
    }

    private function _send_msg($msg, $encrypted = false)
    {
        $this->SendDebug("Send Data:", $msg, 1);
        $this->SendDebug("Used Seed to entcrypt:",  $this->Seed, 1);

        if ($encrypted) {
            $random_seed = "\x10\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
            $msg = openssl_encrypt($msg, "aes-128-cbc", $random_seed, OPENSSL_RAW_DATA|OPENSSL_NO_PADDING, $this->Seed);
            $this->Seed = substr($msg, -16);
            if (false === $msg) {
                $this->SendDebug("Encryption failed!", openssl_error_string (), 0);
            }
            $this->SendDebug("Send encypted:", $msg, 1);
        }

        $JSON['DataID'] = '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}';
        $JSON['Buffer'] = utf8_encode($msg);
        $SendData = json_encode($JSON);
        //Send Data to Client Socket
        $this->SendDataToParent($SendData);
    }


    private function Connect()
    {
        $Status = $this->getStatus();

        //Send WakeUP Packet only when the PS4-System is in StandBy
        if (!$Status["Power"]) {
            $this->sendWakeup();

            IPS_Sleep(40000);
        }
        $this->sendLaunch();
        IPS_Sleep(20);
        //Open the TCP Socket
        $ParentID = IPS_GetInstance($this->InstanceID)["ConnectionID"];
        IPS_SetProperty($ParentID, "Open", false);
        IPS_Sleep(100);
        IPS_SetProperty($ParentID, "Open", true);
        IPS_ApplyChanges($ParentID);
        IPS_Sleep(100);
        $this->ReceiveEncrypted = false;
        $this->_send_hello_request();
        if (!$this->WaitForSeed()) {
            $this->SetStatus(204);
            return false;
        }
        $this->SetStatus(102);
        $this->_send_handshake_request();
    }

    private function Close()
    {
        $this->Seed = "";
        //Cole the TCP Socket
        $ParentID = IPS_GetInstance($this->InstanceID)["ConnectionID"];
        IPS_SetProperty($ParentID, "Open", false);
        IPS_Sleep(100);
        @IPS_ApplyChanges($ParentID);
    }

    private function get_Public_Key_RSA()
    {
        $pk = <<<EOF
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAxfAO/MDk5ovZpp7xlG9J
JKc4Sg4ztAz+BbOt6Gbhub02tF9bryklpTIyzM0v817pwQ3TCoigpxEcWdTykhDL
cGhAbcp6E7Xh8aHEsqgtQ/c+wY1zIl3fU//uddlB1XuipXthDv6emXsyyU/tJWqc
zy9HCJncLJeYo7MJvf2TE9nnlVm1x4flmD0k1zrvb3MONqoZbKb/TQVuVhBv7SM+
U5PSi3diXIx1Nnj4vQ8clRNUJ5X1tT9XfVmKQS1J513XNZ0uYHYRDzQYujpLWucu
ob7v50wCpUm3iKP1fYCixMP6xFm0jPYz1YQaMV35VkYwc40qgk3av0PDS+1G0dCm
swIDAQAB
-----END PUBLIC KEY-----
EOF;
        return $pk;
    }

    private function WaitForSeed()
    {
        for ($i = 0; $i < 1000; $i++) {
            $ret = $this->Seed;
            if ($ret != "") {
                return true;
            }

            IPS_Sleep(5);
        }
        return false;
    }

}

trait DDPConnection
{
    /** DDP Connection */

    private function getStatus()
    {
        $packet = 'SRCH * HTTP/1.1\n';
        $packet .= 'device-discovery-protocol-version:00020020\n';

        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
        socket_sendto($socket, $packet, strlen($packet), 0, '255.255.255.255', 987);
        socket_recvfrom($socket, $result, 1024, 0, $ipaddress, $port);

        $Lines = explode("\n", utf8_decode($result));

        $Request = array_shift($Lines);
        $Header = $this->ParseHeader($Lines);
        // Auf verschiedene Requests prüfen.
        switch ($Request) // REQUEST
        {
            case "HTTP/1.1 200 Ok":
                $Header["Power"] = true;
                break;
            default:
                $Header["Power"] = false;
                break;
        }
        $this->SendDebug("Status DDP Request", $Request, 0);
        $this->SendDebug("Status DDP Answer", $Header, 0);
        return $Header;
    }

    /**
     *Generate and Send WakeUP Packet (DDP over UDP)
     */
    private function sendWakeUP()
    {
        $packet = "WAKEUP * HTTP/1.1\n";
        $packet .= "client-type:i\n";
        $packet .= "auth-type:C\n";
        $packet .= "user-credential:" . $this->ReadPropertyString("Credentials") . "\n";
        $packet .= "device-discovery-protocol-version:00020020\n";
        $this->sendDDP($packet);
    }


    /**
     *Generate and send Launch Packet (DDP over UDP)
     *Launch Packet activate TCP Connection on the PS4-System
     */
    private function sendLaunch()
    {
        $packet = "LAUNCH * HTTP/1.1\n";
        $packet .= "client-type:i\n";
        $packet .= "auth-type:C\n";
        $packet .= "user-credential:" . $this->ReadPropertyString("Credentials") . "\n";
        $packet .= "device-discovery-protocol-version:00020020\n";
        $this->sendDDP($packet);
    }

    /**
     * @param $packet
     * Send DDP Packet over UDP
     */
    private function sendDDP($packet)
    {
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
        socket_sendto($socket, $packet, strlen($packet), 0, $this->ReadPropertyString("IP"), 987);
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
}