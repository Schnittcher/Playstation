<?

include_once(__DIR__ . "/../libs/helper.php");
include_once(__DIR__ . "/../libs/PSStore.php");


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
        DDPConnection,
        VariableProfile;

    public function Create()
    {
        // Diese Zeile nicht löschen.
        parent::Create();

        //Register Propertys
        $this->RegisterPropertyString("IP", "");
        $this->RegisterPropertyString("Credentials", "");
        $this->RegisterPropertyInteger("BootTime", 40000);
        $this->RegisterPropertyString("Games", "[]");
        $this->RegisterTimer("PS4_UpdateActuallyStatus", 0, "PS4_UpdateActuallyStatus($this->InstanceID);");

        //Register Variablen
        $this->RegisterVariableBoolean("PS4_Power", "Status", "~Switch");
        $this->RegisterVariableString("PS4_Cover","Cover", "~HTMLBox");
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->Buffer = "";
        $this->Seed = "";
        $this->ReceiveEncrypted = false;

        if(!IPS_VariableProfileExists("PS4.Games"))
            $this->RegisterProfileIntegerEx("PS4.Games", "Database", "", "", Array());
        $this->RegisterVariableInteger("PS4_Game", "Games", "PS4.Games");
        $this->EnableAction("PS4_Game");
        $this->EnableAction("PS4_Power");
        $this->UpdateGamelist();
        //$this->SetTimerInterval("PS4_UpdateActuallyStatus",20000);
    }

    /** Public Functions to control PS4-System */

    public function Register($pincode)
    {
        $this->Connect();
        IPS_Sleep(100);
        $this->_send_login_request($pincode);
        IPS_Sleep(500);
        $this->Close();
    }

    public function Login()
    {
        $this->Connect();
        IPS_Sleep(100);
        $this->_send_login_request();
        IPS_Sleep(100);
        $this->Close();
    }

    public function Standby()
    {
        $this->Connect();
        IPS_Sleep(100);
        $this->_send_login_request();
        IPS_Sleep(100);
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

    public function RemoteControl($remote_key, $hold_time = 0)
    {
        $this->Connect();
        IPS_Sleep(100);
        $this->_send_login_request();
        $this->_send_remote_control_request("open_rc",0);
        IPS_Sleep(400);
        $this->_send_remote_control_request($remote_key,0);
        IPS_Sleep(100);
        $this->_send_remote_control_request("key_off",0);
        IPS_Sleep(200);
        $this->_send_remote_control_request("close_rc",0);
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
                            $this->SendDebug("GameID",$Game->GameID,0);
                            $PSGame = new PSStore($Game->GameID);
                            SetValue(IPS_GetObjectIDByIdent("PS4_Game",$this->InstanceID), $key+1);
                            $CoverURL = $PSGame->getPicture();
                            $GameName = $PSGame->getGameName();
                            $ProviderName = $PSGame->getProviderName();
                            $Desc = $PSGame->getLongDesc();
                            $CoverString ="<div align=\"center\">
                            $GameName
                            <br />
                            $ProviderName
                            <br />
                            <img src=$CoverURL>
                            <br />
                            
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
}
