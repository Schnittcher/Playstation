<?

include_once(__DIR__ . "/../libs/helper.php");

/**
 * @property bool $ReceiveEncrypted
 * @property string $Buffer
 * @property string $Seed
 */
class PS4 extends IPSModule
{

    use BufferHelper,
        DebugHelper,
        TCPConnection,
        DDPConnection;

    public function Create()
    {
        // Diese Zeile nicht löschen.
        parent::Create();
        //Always create our own Client Socket I/O, when no parent is already available
        $this->RequireParent("{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}");

        //Register Propertys
        $this->RegisterPropertyString("IP", "");
        $this->RegisterPropertyString("Credentials", "");
        $this->RegisterPropertyString("Games", "");
        $this->RegisterTimer("PS4_UpdateActuallyStatus", 5000, "PS4_UpdateActuallyStatus($this->InstanceID);");

        //Register Variablen
        $this->RegisterVariableBoolean("PS4_Power", "Status", "~Switch");
        $this->RegisterVariableString("PS4_Cover","Cover", "~HTMLBox");
    }

    public function ApplyChanges()
    {
        $this->Buffer = "";
        $this->Seed = "";
        $this->ReceiveEncrypted = false;

        if(!IPS_VariableProfileExists("PS4.Games"))
            $this->RegisterProfileIntegerEx("PS4.Games", "Database", "", "", Array());
        $this->RegisterVariableInteger("PS4_Game", "Games", "PS4.Games");
        $this->EnableAction("PS4_Game");
        $this->EnableAction("PS4_Power");
        $this->UpdateGamelist();
        $this->SetTimerInterval("PS4_UpdateActuallyStatus",20000);
    }

    public function ReceiveData($JSONString)
    {
        $ReceiveData = json_decode($JSONString);
        $DataIn = utf8_decode($ReceiveData->Buffer);
        if ($this->ReceiveEncrypted) { // Hier empfangende Daten entschlüsseln
            $this->SendDebug("Received Encrypted Data", $DataIn, 1); // 1 für default ist Hex-Ansicht
            $random_seed = "\x10\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
            $Data = openssl_decrypt($DataIn, "AES-128-CBC", $random_seed, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $this->Seed); //Decrypt benutzt unser Passwort (random_seed) und als start IV den empfangenen Seed des PS4
            $this->SendDebug("Received Decrypted Data", $Data, 0); // 1 für default ist Hex-Ansicht
        } else { // Unverschlüsselte Daten.
            $this->SendDebug("Received Plain Data", $DataIn, 1); // 1 für default ist Hex-Ansicht
            $Data = $this->Buffer . $DataIn;
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
                case "pcco":
                    $this->SendDebug("Hello Request Answer", $Payload, 1);
                    $this->Seed = substr($Payload, 12, 16);
                    $this->SendDebug("Seed received", substr($Payload, 12, 16), 1);
                    break;
                default:
                    $this->SendDebug("unhandled type received", $Type, 0);
                    $this->SendDebug("unhandled payload received", $Payload, 0);
                    break;
            }
        }
    }

    /** Public Functions to control PS4-System */

    public function Register($pincode)
    {
        $this->Connect();
        IPS_Sleep(10);
        $this->_send_login_request($pincode);
        IPS_Sleep(500);
        $this->Close();
    }

    public function Login()
    {
        $this->Connect();
        IPS_Sleep(10);
        $this->_send_login_request();
        IPS_Sleep(500);
        $this->Close();
    }

    public function Standby()
    {
        $this->Connect();
        IPS_Sleep(100);
        $this->_send_login_request();
        IPS_Sleep(20);
        $this->_send_standby_request();
        $this->Close();
    }

    public function StartTitle($title_id)
    {
        $this->Connect();
        IPS_Sleep(100);
        $this->_send_login_request();
        $this->_send_boot_request($title_id);
        $this->Close();
    }

    public function UpdateActuallyStatus() {
        $PS4Status = $this->getStatus();

        //Actually PS4 Power Status
        if ($PS4Status["Power"]) {
            SetValue(IPS_GetObjectIDByIdent("PS4_Power",$this->InstanceID), true);

            //Actually Game
            if (array_key_exists("RUNNING-APP-TITLEID",$PS4Status)) {
            $GamesListString = $this->ReadPropertyString("Games");
                if ($GamesListString != "") {
                    $Games = json_decode($GamesListString);
                    foreach($Games as $key=>$Game) {
                        if ($Game->GameID == $PS4Status["RUNNING-APP-TITLEID"]) {
                            SetValue(IPS_GetObjectIDByIdent("PS4_Game",$this->InstanceID), $key+1);
                            $CoverURL = $this->getCover($Game->GameID);
                            $CoverString ="<div align=\"right\">
<img src=$CoverURL>
</div>";
                            SetValue(IPS_GetObjectIDByIdent("PS4_Cover",$this->InstanceID), $CoverString);
                        }
                    }
                }
            } else {
                SetValue(IPS_GetObjectIDByIdent("PS4_Game",$this->InstanceID), 0);
            }
        } else {
            SetValue(IPS_GetObjectIDByIdent("PS4_Power",$this->InstanceID), false);
        }
    }


    /** internal private Functions */

    //IPS Functions

    private function UpdateGamelist()
    {
        $GamesListString = $this->ReadPropertyString("Games");
            If ($GamesListString != "") {
            if (IPS_VariableProfileExists("PS4.Games"))
                IPS_DeleteVariableProfile("PS4.Games");

            $Associations = Array();
            $Value = 1;

            $Games = json_decode($GamesListString);
            foreach ($Games as $Game) {
                $Associations[] = Array($Value++, $Game->GameName, "", -1);
                // associations only support up to 32 variables
                if( $Value === 33 ) break;
            }
            $this->RegisterProfileIntegerEx("PS4.Games", "Database", "", "", $Associations);
        }
    }

    private function getCover($title_id) {
        $cover = file_get_contents(__DIR__ . "/../libs/cover.json");
        $cover_data = json_decode($cover,true);
        IPS_LogMessage("Cover","test");
        if (array_key_exists($title_id,$cover_data)) {
            return $cover_data[$title_id];
        }
        return false;
    }

    /** IPS Functions */

    public function RequestAction($Ident, $Value) {
        switch($Ident) {
            case "PS4_Game":
                $GamesListString = $this->ReadPropertyString("Games");
                $Games = json_decode($GamesListString);
                $Game = $Games[$Value-1];
                $this->StartTitle($Game->GameID);
                break;
            case "PS4_Power":
                If ($Value) {
                    $this->Login();
                    SetValue(IPS_GetObjectIDByIdent($Ident,$this->InstanceID), true);
                } else {
                    $this->Standby();
                    SetValue(IPS_GetObjectIDByIdent($Ident,$this->InstanceID), false);
                }
                break;
        }
    }

    protected function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize) {

        if(!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, 1);
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if($profile['ProfileType'] != 1)
                throw new Exception("Variable profile type does not match for profile ".$Name);
        }

        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);

    }

    protected function RegisterProfileIntegerEx($Name, $Icon, $Prefix, $Suffix, $Associations) {
        if ( sizeof($Associations) === 0 ){
            $MinValue = 0;
            $MaxValue = 0;
        } else {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[sizeof($Associations)-1][0];
        }

        $this->RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);

        foreach($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }

    }

}
