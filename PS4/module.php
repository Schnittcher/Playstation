<?
include_once(__DIR__ . "/../libs/helper.php");

class PS4 extends IPSModule {

    use BufferHelper,
        DebugHelper;


    public function Create()
    {
        // Diese Zeile nicht löschen.
        parent::Create();
        //Always create our own Client Socket I/O, when no parent is already available
        $this->RequireParent("{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}");

    }

    public function ApplyChanges()
    {

    }

    public function ReceiveData($JSONString)
    {
        $ReceiveData = json_decode($JSONString);
        $this->SendDebug("Receive JSONString", $JSONString, 0);
        //$this->SendDebug("Receive", $ReceiveData, 0);
        $Packet = utf8_decode($ReceiveData->Buffer);

        //Empfangenes Paket parsen, hier habe ich mit unserem Request nur das zerschneiden des Paketes geübt :)

        $Len = unpack('V',substr($Packet,0,4));
        $Type = substr($Packet,4,4);
        $Payload = substr($Packet,8);

        switch ($Type)
        {
            case "pcco": // oder "\x70\x63\x63\x6f" => Ist Hello Request
                // War nur zum testen, weil wir ja unseren Request nicht verarbeiten wollen, sondern die Antwort
                $this->SendDebug("Hello Request Answer", $Type, 0);

                $Seed = substr($Payload,12,16);
                $this->SendDebug("Seed", $Seed, 0);
                $this->SendDebug("Seed Length", strlen($Seed), 0);
                $this->SetBuffer("Seed", $Seed);

                break;
            default: // Hello Response, leider nicht dokumentiert. Das pyhton Script prüft auch die Empfangen Daten gar nicht, es schneidet nur den Seed raus.
                // Wenn das reicht, dann braucht man das hier alles nicht :)
                //$this->SendDebug("Seed default", $Seed, 0);
                break;
        }
    }

    public function connect() {
        $random_seed = "\x10\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
        $this->_send_hello_request();


        sleep(1);
        $seed = $this->GetBuffer("Seed");
        $this->SendDebug("Connect Seed", $seed, 0);
        //str_pad($seed, 16,"\x00");
        $this->_send_handshake_request($this->GetBuffer("Seed"));
    }

    public function _send_hello_request() {
        $packet = "\x1c\x00\x00\x00\x70\x63\x63\x6f\x00\x00\x02\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
        $this->_send_msg($packet);
    }

    public function _send_handshake_request($seed){

        $random_seed = "\x10\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
        openssl_public_encrypt($random_seed,$cryptedKey,$this->get_Public_Key_RSA(),OPENSSL_PKCS1_OAEP_PADDING);

        $Packet = "\x18\x01\x00\x00";
        $Packet .= "\x20\x00\x00\x00";
        $Packet .= $cryptedKey;
        $Packet .= $seed;

        $this->_send_msg($Packet);
    }

    public function _send_standby_request() {
        $Packet = "\x08\x00\x00\x00";
        $Packet .= "\x08\x00\x00\x00";
        $dummy = "";
        str_pad($dummy, 8);
        $Packet .= $dummy;
        $this->_send_msg($Packet,true);
    }

    public function _send_login_request(){
        $AccountID = "XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX";
        $AccountID = str_pad($AccountID, 64, "\x00");
        $AppLabel ="Playstation";
        $AppLabel = str_pad($AppLabel, 256,"\x00");
        $OSVersion = "4.4";
        $OSVersion = str_pad($OSVersion, 16,"\x00");
        $model ="PS4 Waker";
        $model = str_pad($model, 16,"\x00");
        $pincode = "";
        $pincode = str_pad($pincode, 16,"\x00");

        $Login = "\x80\x01\x00\x00";
        $Login .= "\x1e\x00\x00\x00";
        $Login .= "\x00\x00\x00\x00";
        $Login .="\x01\x02\x00\x00";
        $Login .= $AccountID;
        $Login .= $AppLabel;
        $Login .= $OSVersion;
        $Login .= $model;
        $Login .= $pincode;

        $this->SendDebug("Login Package", $Login, 0);

        $this->_send_msg($Login,true);


    }

    private function _send_msg($msg, $encrypted=false) {
        $this->SendDebug("TX MSG Length:", strlen($msg), 0);
        $this->SendDebug("TX MSG:", $msg, 0); //evtl. bin2hex($msg)

        if ($encrypted) {
            $random_seed = "\x10\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
            $cipher = "AES-128-CBC";
            $iv = $this->GetBuffer("Seed");
            str_pad($iv, 16,"\x00");
            $this->SendDebug("IV:", $iv, 0); //evtl. bin2hex($msg)
            $msg = openssl_encrypt($msg, $cipher,$random_seed, $options=0, $iv);


        }
        $this->SendDebug("TX MSG crypted Length:", strlen($msg), 0);
        $this->SendDebug("TX MSG crypted:", $msg, 0);


        $JSON['DataID'] = '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}';
        $JSON['Buffer'] = utf8_encode($msg);
        $SendData = json_encode($JSON);
        //Send Data to Client Socket
        $this->SendDataToParent($SendData);
    }

    private function get_Public_Key_RSA() {
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
}
