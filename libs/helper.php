<?php

trait BufferHelper
{
    /**
     * Wert einer Eigenschaft aus den InstanceBuffer lesen.
     *
     * @param string $name Propertyname
     *
     * @return mixed Value of Name
     */
    public function __get($name)
    {
        return unserialize($this->GetBuffer($name));
    }

    /**
     * Wert einer Eigenschaft in den InstanceBuffer schreiben.
     *
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
     * @param string $Message Nachricht für Data.
     * @param mixed  $Data    Daten für die Ausgabe.
     *
     * @return int $Format Ausgabeformat für Strings.
     */
    protected function SendDebug($Message, $Data, $Format)
    {
        if (is_object($Data)) {
            foreach ($Data as $Key => $DebugData) {
                $this->SendDebug($Message . ':' . $Key, $DebugData, 0);
            }
        } elseif (is_array($Data)) {
            foreach ($Data as $Key => $DebugData) {
                $this->SendDebug($Message . ':' . $Key, $DebugData, 0);
            }
        } elseif (is_bool($Data)) {
            parent::SendDebug($Message, ($Data ? 'TRUE' : 'FALSE'), 0);
        } else {
            parent::SendDebug($Message, (string) $Data, $Format);
        }
    }
}

class RemoteKeys
{
    /**
     * Keys for remote Control.
     */
    const UP = "\x01\x00\x00\x00"; // 1
    const DOWN = "\x02\x00\x00\x00"; // 2
    const RIGHT = "\x04\x00\x00\x00"; // 4
    const LEFT = "\x08\x00\x00\x00"; // 8
    const ENTER = "\x10\x00\x00\x00"; // 16
    const BACK = "\x20\x00\x00\x00"; //32
    const OPTION = "\x40\x00\x00\x00"; //64
    const PS = "\x80\x00\x00\x00"; //128
    const KEY_OFF = "\x00\x01\x00\x00"; // 256
    const CANCEL = "\x00\x02\x00\x00"; // 512
    const OPEN_RC = "\x00\x04\x00\x00"; // 1024
    const CLOSE_RC = "\x00\x08\x00\x00"; // 2048
}

/**
 * Trait TCPConnection
 * Helper for tcp connection and packets.
 */
trait TCPConnection
{
    private $socket;

    /**
     * Build and send the hello packet.
     */
    private function _send_hello_request()
    {
        $packet = "\x1c\x00\x00\x00\x70\x63\x63\x6f\x00\x00\x02\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
        $this->_send_msg($packet);
    }

    /**
     * Build and send the handshake packet.
     */
    private function _send_handshake_request()
    {
        $this->SendDebug('Used Seed', $this->Seed, 0);

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
     * Build and send standby kacket.
     */
    private function _send_standby_request()
    {
        $Packet = "\x08\x00\x00\x00";
        $Packet .= "\x1a\x00\x00\x00";
        $dummy = '';
        $dummy = str_pad($dummy, 8, "\x00");
        $Packet .= $dummy;
        $this->ReceiveEncrypted = true;
        $this->_send_msg($Packet, true);
    }

    /**
     * Build and send the login packet, pincode is optional for registration.
     *
     * @param string $pincode
     */
    private function _send_login_request($pincode = '')
    {
        $AccountID = $this->ReadPropertyString('Credentials');
        $AccountID = str_pad($AccountID, 64, "\x00");
        $AppLabel = 'Playstation';
        $AppLabel = str_pad($AppLabel, 256, "\x00");
        $OSVersion = '4.4';
        $OSVersion = str_pad($OSVersion, 16, "\x00");
        $model = 'IP-Symcon';
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
        $this->SendDebug('Login Package', $Login, 0);

        $this->_send_msg($Login, true);
    }

    /**
     * Build and send the boot package, to start game or app.
     *
     * @param $title_id
     */
    private function _send_boot_request($title_id)
    {
        $Package = "\x18\x00\x00\x00";
        $Package .= "\x0a\x00\x00\x00";
        $title_id = str_pad($title_id, 16, "\x00");
        $Package .= $title_id;
        $dummy = '';
        $dummy = str_pad($dummy, 8, "\x00");
        $Package .= $dummy;
        $this->_send_msg($Package, true);
    }

    private function _send_remote_control_request($remote_key, $hold_time = 0)
    {
        $Package = "\x10\x00\x00\x00";
        $Package .= "\x1c\x00\x00\x00";
        $hold_time = "\x00";
        switch ($remote_key) {
            case 'up':
                $remote_key = RemoteKeys::UP;
                break;
            case 'down':
                $remote_key = RemoteKeys::DOWN;
                break;
            case 'right':
                $remote_key = RemoteKeys::RIGHT;
                break;
            case 'left':
                $remote_key = RemoteKeys::LEFT;
                break;
            case 'enter':
                $remote_key = RemoteKeys::ENTER;
                break;
            case 'back':
                $remote_key = RemoteKeys::BACK;
                break;
            case 'option':
                $remote_key = RemoteKeys::OPTION;
                break;
            case 'ps':
                $remote_key = RemoteKeys::PS;
                break;
            case 'key_off':
                $remote_key = RemoteKeys::KEY_OFF;
                break;
            case 'cancel':
                $remote_key = RemoteKeys::CANCEL;
                break;
            case 'open_rc':
                $remote_key = RemoteKeys::OPEN_RC;
                break;
            case 'close_rc':
                $remote_key = RemoteKeys::CLOSE_RC;
                break;
            default:
                $this->SendDebug('Remote Keys', 'Key not available!', 0);
        }
        $Package .= str_pad($remote_key, 4, "\x00");
        $Package .= str_pad($hold_time, 4, "\x00");
        $this->_send_msg($Package, true);
    }

    /**
     * Send message via tcp connection, if encrypted is true, the message is encrypted.
     *
     * @param $msg
     * @param bool $encrypted
     */
    private function _send_msg($msg, $encrypted = false)
    {
        $this->SendDebug('Send Data:', $msg, 1);
        $this->SendDebug('Used Seed to entcrypt:', $this->Seed, 1);

        if ($encrypted) {
            $random_seed = "\x10\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
            $msg = openssl_encrypt($msg, 'aes-128-cbc', $random_seed, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $this->Seed);
            $this->Seed = substr($msg, -16);
            if (false === $msg) {
                $this->SendDebug('Encryption failed!', openssl_error_string(), 0);
            }
            $this->SendDebug('Send encypted:', $msg, 1);
        }

        if ($bytes = socket_send($this->socket, $msg, strlen($msg), 0)) {
            $this->SendDebug(' socket', $bytes . ' bytes sent to ' . $this->ReadPropertyString('IP') . ':' . 997, 0);
        } else {
            $this->SocketErrorHandler();
        }
    }

    /** Socket functions */

    /**
     * Create socket for tcp connection.
     */
    private function CreateSocket()
    {

        /* do nothing, if socket was already created */
        if ($this->socket) {
            $this->SendDebug('socket [instance]', 'already created', 0);
        }
        /* create socket */
        elseif ($this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) {
            $this->SendDebug('socket [instance]', 'created', 0);
        } else {
            /* error handling */
            $this->SocketErrorHandler();
        }
    }

    /**
     * sends a receive timeout to socket.
     *
     * @param int $timeout
     */
    protected function SocketSetTimeout($timeout = 2)
    {
        if (socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $timeout, 'usec' => 0))) {
            $this->SendDebug('socket [settings]', 'set timeout to ' . $timeout . 's', 0);
        } else {
            $this->SocketErrorHandler();
        }
    }

    /**
     * received message via tcp, used to get the first seed.
     */
    private function _receive_msg()
    {
        $buffer = '';
        if ($bytes = @socket_recv($this->socket, $buffer, 4096, 0) !== false) {
            $this->SendDebug('socket [receive]', $buffer, 0);
            //$buffer =  utf8_decode($buffer);
            if ($this->ReceiveEncrypted) { // Hier empfangende Daten entschlüsseln
                $this->SendDebug('Received Encrypted Data', $DataIn, 1); // 1 für default ist Hex-Ansicht
                $random_seed = "\x10\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
                $Data = openssl_decrypt($buffer, 'AES-128-CBC', $random_seed, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $this->Seed); //Decrypt benutzt unser Passwort (random_seed) und als start IV den empfangenen Seed des PS4
                $this->SendDebug('Received Decrypted Data', $Data, 0); // 1 für default ist Hex-Ansicht
            } else { // Unverschlüsselte Daten.
                $this->SendDebug('Received Plain Data', $buffer, 1); // 1 für default ist Hex-Ansicht
                $Data = $buffer;
                $Len = unpack('V', substr($Data, 0, 4))[1];
                //$Data lang genug ?
                if ($Len > strlen($Data)) { // Nein zu kurz, ab in den Buffer.
                    $this->Buffer = $Data;
                    return;
                }
                $this->Buffer = substr($Data, $Len); // Rest in den Buffer
                //Empfangenes Paket parsen
                $Packet = substr($Data, 4, $Len);
                $Type = substr($Packet, 0, 4);
                $Payload = substr($Packet, 4);

                switch ($Type) {
                    case 'pcco':
                        $this->SendDebug('Hello Request Answer', $Payload, 1);
                        $this->Seed = substr($Payload, 12, 16);
                        $this->SendDebug('Seed received', substr($Payload, 12, 16), 1);
                        break;
                    default:
                        $this->SendDebug('unhandled type received', $Type, 0);
                        $this->SendDebug('unhandled payload received', $Payload, 0);
                        break;
                }
            }
        }
    }

    /**
     * handles socket error messages.
     */
    protected function SocketErrorHandler()
    {
        $error_code = socket_last_error();
        $error_msg = socket_strerror($error_code);
        $this->SendDebug('socket [error]', $error_code . ' message: ' . $error_msg, 0);
        exit(-1);
    }

    /**
     * Connect socket to playstation 4.
     *
     * @return bool
     */
    private function Connect()
    {
        $Status = $this->getStatus();

        //Send WakeUP Packet only when the PS4-System is in StandBy
        if (!$Status['Power']) {
            $this->sendWakeup();

            IPS_Sleep($this->ReadPropertyInteger('BootTime') * 1000);
        }
        $this->sendLaunch();
        IPS_Sleep(20);
        $this->CreateSocket();
        $this->SocketSetTimeout();
        socket_connect($this->socket, $this->ReadPropertyString('IP'), 997);

        $this->ReceiveEncrypted = false;
        $this->_send_hello_request();
        //Receive Answer to get the first Seed
        $this->_receive_msg();
        if (!$this->WaitForSeed()) {
            $this->SetStatus(204);
            return false;
        }
        $this->SetStatus(102);
        $this->_send_handshake_request();
    }

    /**
     * Close connection to Playstation 4.
     */
    private function Close()
    {
        $this->Seed = '';
        IPS_Sleep(200);
        socket_close($this->socket);
        $this->SendDebug('Socket', 'Closed', 0);
    }

    /**
     * Get the public key for handshake.
     *
     * @return string
     */
    private function get_Public_Key_RSA()
    {
        $pk = <<<'EOF'
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

    /**
     * Wait for seed.
     *
     * @return bool
     */
    private function WaitForSeed()
    {
        for ($i = 0; $i < 1000; $i++) {
            $ret = $this->Seed;
            if ($ret != '') {
                return true;
            }

            IPS_Sleep(5);
        }
        return false;
    }
}

/**
 * Trait DDPConnection
 * Helper for ddp connection via udp and packets.
 */
trait DDPConnection
{
    /** DDP Connection */
    private function getStatus()
    {
        $packet = 'SRCH * HTTP/1.1\n';
        $packet .= 'device-discovery-protocol-version:00020020\n';

        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 2, 'usec' => 0));
        socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
        socket_sendto($socket, $packet, strlen($packet), 0, '255.255.255.255', 987);
        socket_recvfrom($socket, $result, 1024, 0, $ipaddress, $port);

        $Lines = explode("\n", utf8_decode($result));

        $Request = array_shift($Lines);
        $Header = $this->ParseHeader($Lines);
        // Auf verschiedene Requests prüfen.
        switch ($Request) { // REQUEST
            case 'HTTP/1.1 200 Ok':
                $Header['Power'] = true;
                break;
            default:
                $Header['Power'] = false;
                break;
        }
        $this->SendDebug('Status DDP Request', $Request, 0);
        $this->SendDebug('Status DDP Answer', $Header, 0);
        return $Header;
    }

    /**
     *Generate and Send WakeUP Packet (DDP over UDP).
     */
    private function sendWakeUP()
    {
        $packet = "WAKEUP * HTTP/1.1\n";
        $packet .= "client-type:i\n";
        $packet .= "auth-type:C\n";
        $packet .= 'user-credential:' . $this->ReadPropertyString('Credentials') . "\n";
        $packet .= "device-discovery-protocol-version:00020020\n";

        $this->SendDebug('sendWakeUP', $packet, 0);
        $this->sendDDP($packet);
    }

    /**
     *Generate and send Launch Packet (DDP over UDP)
     *Launch Packet activate TCP Connection on the PS4-System.
     */
    private function sendLaunch()
    {
        $packet = "LAUNCH * HTTP/1.1\n";
        $packet .= "client-type:i\n";
        $packet .= "auth-type:C\n";
        $packet .= 'user-credential:' . $this->ReadPropertyString('Credentials') . "\n";
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
        socket_sendto($socket, $packet, strlen($packet), 0, $this->ReadPropertyString('IP'), 987);
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
}

trait VariableProfile
{
    protected function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
    {
        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, 1);
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] != 1) {
                throw new Exception('Variable profile type does not match for profile ' . $Name);
            }
        }

        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
    }

    protected function RegisterProfileIntegerEx($Name, $Icon, $Prefix, $Suffix, $Associations)
    {
        if (count($Associations) === 0) {
            $MinValue = 0;
            $MaxValue = 0;
        } else {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[count($Associations) - 1][0];
        }

        $this->RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);

        foreach ($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }
    }

    protected function RegisterMediaObject($Ident, $Name, $Typ, $Parent, $Position, $Cached, $Filename)
    {
        if (!IPS_MediaExists(@$this->GetIDForIdent($Ident))) {
            // Image im MedienPool anlegen
            $MediaID = IPS_CreateMedia($Typ);
            // Medienobjekt einsortieren unter Kategorie $catid
            IPS_SetParent($MediaID, $Parent);
            IPS_SetIdent($MediaID, $Ident);
            IPS_SetName($MediaID, $Name);
            IPS_SetPosition($MediaID, $Position);
            IPS_SetMediaCached($MediaID, $Cached);
            $ImageFile = IPS_GetKernelDir() . 'media' . DIRECTORY_SEPARATOR . $Filename;  // Image-Datei
            IPS_SetMediaFile($MediaID, $ImageFile, false);    // Image im MedienPool mit Image-Datei verbinden
        }
        return;
    }
}
